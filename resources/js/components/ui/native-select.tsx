import * as PopoverPrimitive from '@radix-ui/react-popover';
import { Check, ChevronDown } from 'lucide-react';
import * as React from 'react';
import { cn } from '@/lib/utils';

type Opcion = {
    value: string;
    label: string;
    description?: string;
    disabled: boolean;
    group?: string;
};

function textoDe(node: React.ReactNode): string {
    if (node === null || node === undefined || typeof node === 'boolean') {
        return '';
    }

    if (typeof node === 'string' || typeof node === 'number') {
        return String(node);
    }

    if (Array.isArray(node)) {
        return node.map(textoDe).join('');
    }

    if (React.isValidElement<{ children?: React.ReactNode }>(node)) {
        return textoDe(node.props.children);
    }

    return '';
}

function leerOpciones(children: React.ReactNode, group?: string): Opcion[] {
    return React.Children.toArray(children).flatMap((child): Opcion[] => {
        if (!React.isValidElement<Record<string, unknown>>(child)) {
            return [];
        }

        const props = child.props;

        if (child.type === 'optgroup') {
            return leerOpciones(
                props.children as React.ReactNode,
                String(props.label ?? ''),
            );
        }

        if (child.type === React.Fragment) {
            return leerOpciones(props.children as React.ReactNode, group);
        }

        if (child.type !== 'option') {
            return [];
        }

        const label = textoDe(props.children as React.ReactNode)
            .replace(/\s+/g, ' ')
            .trim();

        return [
            {
                value: String(props.value ?? label),
                label,
                description: props['data-description']
                    ? String(props['data-description'])
                    : undefined,
                disabled: Boolean(props.disabled),
                group,
            },
        ];
    });
}

function normalizar(texto: string): string {
    return texto
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();
}

function etiqueta(opcion: Opcion): string {
    return opcion.description
        ? `${opcion.label} — ${opcion.description}`
        : opcion.label;
}

/**
 * Select con búsqueda. Se usa igual que un <select> nativo (value/defaultValue, onChange con
 * e.target.value, name, required, <option>/<optgroup> como hijos): por dentro conserva un <select>
 * oculto que es el que envía el formulario y dispara onChange, y encima muestra un campo para
 * filtrar las opciones. Un <option data-description="..."> muestra ese texto como dato secundario
 * (p. ej. el RFC de un cliente) y también se puede buscar por él.
 */
function NativeSelect({
    className,
    children,
    id,
    value,
    defaultValue,
    disabled,
    'aria-label': ariaLabel,
    'aria-invalid': ariaInvalid,
    ...props
}: React.ComponentProps<'select'>) {
    const selectRef = React.useRef<HTMLSelectElement>(null);
    const inputRef = React.useRef<HTMLInputElement>(null);
    const listaRef = React.useRef<HTMLDivElement>(null);
    const listaId = React.useId();

    const opciones = React.useMemo(() => leerOpciones(children), [children]);
    const controlado = value !== undefined;

    const [interno, setInterno] = React.useState<string>(() =>
        defaultValue !== undefined
            ? String(defaultValue)
            : (opciones.find((o) => !o.disabled)?.value ?? ''),
    );
    const actual = controlado ? String(value) : interno;
    const seleccionada = opciones.find((o) => o.value === actual);

    const [abierto, setAbierto] = React.useState(false);
    const [busqueda, setBusqueda] = React.useState('');
    const [activo, setActivo] = React.useState(0);

    // Si el formulario se reinicia (p. ej. resetOnSuccess de Inertia), el select oculto vuelve a
    // su valor por defecto y hay que reflejarlo.
    React.useEffect(() => {
        const form = selectRef.current?.form;

        if (!form || controlado) {
            return;
        }

        const alReiniciar = () =>
            setTimeout(() => setInterno(selectRef.current?.value ?? ''));
        form.addEventListener('reset', alReiniciar);

        return () => form.removeEventListener('reset', alReiniciar);
    }, [controlado]);

    const termino = normalizar(busqueda.trim());
    const visibles = opciones.filter(
        (o) =>
            !(o.disabled && o.value === '') &&
            (!termino ||
                normalizar(
                    `${o.label} ${o.description ?? ''} ${o.group ?? ''}`,
                ).includes(
                    termino,
                )),
    );
    const elegibles = visibles.filter((o) => !o.disabled);

    const abrir = () => {
        if (disabled) {
            return;
        }

        setBusqueda('');
        setActivo(
            Math.max(
                0,
                opciones
                    .filter((o) => !o.disabled)
                    .findIndex((o) => o.value === actual),
            ),
        );
        setAbierto(true);
    };

    const elegir = (opcion: Opcion) => {
        const select = selectRef.current;

        setAbierto(false);
        setBusqueda('');

        if (!select || opcion.disabled) {
            return;
        }

        if (!controlado) {
            setInterno(opcion.value);
        }

        if (select.value === opcion.value) {
            return;
        }

        // Se cambia el <select> real y se dispara su evento para que el onChange del componente
        // padre reciba un evento normal con e.target.value.
        Object.getOwnPropertyDescriptor(
            HTMLSelectElement.prototype,
            'value',
        )?.set?.call(select, opcion.value);
        select.dispatchEvent(new Event('change', { bubbles: true }));
    };

    React.useEffect(() => {
        if (!abierto) {
            return;
        }

        listaRef.current
            ?.querySelector('[data-activo="true"]')
            ?.scrollIntoView({ block: 'nearest' });
    }, [activo, abierto]);

    const alTeclear = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();

            if (!abierto) {
                abrir();

                return;
            }

            const paso = e.key === 'ArrowDown' ? 1 : -1;
            setActivo((i) =>
                elegibles.length === 0
                    ? 0
                    : (i + paso + elegibles.length) % elegibles.length,
            );
        } else if (e.key === 'Enter') {
            if (abierto) {
                e.preventDefault();

                if (elegibles[activo]) {
                    elegir(elegibles[activo]);
                }
            }
        } else if (e.key === 'Escape') {
            if (abierto) {
                e.preventDefault();
                e.stopPropagation();
                setAbierto(false);
                setBusqueda('');
            }
        } else if (e.key === 'Tab') {
            setAbierto(false);
        }
    };

    const muestraSeleccion = seleccionada && !seleccionada.disabled;
    const textoSeleccion = muestraSeleccion ? etiqueta(seleccionada) : '';
    const placeholder = muestraSeleccion
        ? textoSeleccion
        : (seleccionada?.label ??
          opciones.find((o) => o.value === '')?.label ??
          'Selecciona…');

    let grupoAnterior: string | undefined;

    return (
        <PopoverPrimitive.Root open={abierto} onOpenChange={setAbierto}>
            <PopoverPrimitive.Anchor asChild>
                <div
                    data-slot="native-select"
                    className={cn('relative w-full min-w-0', className)}
                >
                    <select
                        ref={selectRef}
                        value={value}
                        defaultValue={defaultValue}
                        disabled={disabled}
                        tabIndex={-1}
                        aria-hidden
                        onFocus={() => inputRef.current?.focus()}
                        className="sr-only"
                        {...props}
                    >
                        {children}
                    </select>

                    <input
                        ref={inputRef}
                        id={id}
                        type="text"
                        role="combobox"
                        aria-label={ariaLabel}
                        aria-invalid={ariaInvalid}
                        aria-expanded={abierto}
                        aria-controls={listaId}
                        aria-autocomplete="list"
                        autoComplete="off"
                        disabled={disabled}
                        value={abierto ? busqueda : textoSeleccion}
                        placeholder={placeholder}
                        onChange={(e) => {
                            setBusqueda(e.target.value);
                            setActivo(0);

                            if (!abierto) {
                                setAbierto(true);
                            }
                        }}
                        onClick={() => !abierto && abrir()}
                        onFocus={(e) => e.target.select()}
                        onBlur={() => {
                            setAbierto(false);
                            setBusqueda('');
                        }}
                        onKeyDown={alTeclear}
                        className={cn(
                            'flex h-9 w-full min-w-0 truncate rounded-md border border-input bg-transparent py-1 pr-8 pl-3 text-base text-foreground shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground md:text-sm',
                            'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                            'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
                            'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
                            !abierto && 'cursor-pointer',
                        )}
                    />
                    <ChevronDown
                        aria-hidden
                        className="pointer-events-none absolute top-1/2 right-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                </div>
            </PopoverPrimitive.Anchor>

            <PopoverPrimitive.Portal>
                <PopoverPrimitive.Content
                    align="start"
                    sideOffset={4}
                    onOpenAutoFocus={(e) => e.preventDefault()}
                    onCloseAutoFocus={(e) => e.preventDefault()}
                    onInteractOutside={(e) => {
                        if (inputRef.current?.contains(e.target as Node)) {
                            e.preventDefault();
                        }
                    }}
                    className="z-50 max-w-[calc(100vw-2rem)] min-w-[var(--radix-popover-trigger-width)] rounded-md border bg-popover p-1 text-popover-foreground shadow-md"
                >
                    <div
                        ref={listaRef}
                        id={listaId}
                        role="listbox"
                        className="max-h-72 overflow-y-auto"
                    >
                        {visibles.length === 0 && (
                            <p className="px-2 py-1.5 text-sm text-muted-foreground">
                                Sin resultados.
                            </p>
                        )}

                        {visibles.map((opcion) => {
                            const encabezado =
                                opcion.group && opcion.group !== grupoAnterior
                                    ? opcion.group
                                    : null;
                            grupoAnterior = opcion.group;
                            const esActivo =
                                !opcion.disabled &&
                                elegibles[activo]?.value === opcion.value;
                            const esSeleccionada = opcion.value === actual;

                            return (
                                <React.Fragment
                                    key={`${opcion.group ?? ''}:${opcion.value}`}
                                >
                                    {encabezado && (
                                        <div className="px-2 pt-2 pb-1 text-xs font-semibold text-muted-foreground">
                                            {encabezado}
                                        </div>
                                    )}
                                    <div
                                        role="option"
                                        aria-selected={esSeleccionada}
                                        aria-disabled={opcion.disabled}
                                        data-activo={esActivo}
                                        onMouseDown={(e) => e.preventDefault()}
                                        onMouseEnter={() => {
                                            const i = elegibles.findIndex(
                                                (o) => o.value === opcion.value,
                                            );

                                            if (i >= 0) {
                                                setActivo(i);
                                            }
                                        }}
                                        onClick={() => elegir(opcion)}
                                        className={cn(
                                            'flex w-full cursor-pointer items-start gap-2 rounded-sm px-2 py-1.5 text-sm',
                                            esActivo &&
                                                'bg-accent text-accent-foreground',
                                            esSeleccionada && 'font-medium',
                                            opcion.disabled &&
                                                'cursor-not-allowed opacity-50',
                                        )}
                                    >
                                        <Check
                                            className={cn(
                                                'mt-0.5 size-4 shrink-0',
                                                esSeleccionada
                                                    ? 'opacity-100'
                                                    : 'opacity-0',
                                            )}
                                        />
                                        <span className="min-w-0 flex-1">
                                            {opcion.label}
                                        </span>
                                        {opcion.description && (
                                            <span className="shrink-0 font-mono text-xs text-muted-foreground">
                                                {opcion.description}
                                            </span>
                                        )}
                                    </div>
                                </React.Fragment>
                            );
                        })}
                    </div>
                </PopoverPrimitive.Content>
            </PopoverPrimitive.Portal>
        </PopoverPrimitive.Root>
    );
}

export { NativeSelect };
