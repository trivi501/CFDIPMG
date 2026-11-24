import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/** Un concepto de catálogo (Productos o Claves SAT) que se puede elegir al escribir la descripción. */
export type SugerenciaConcepto = {
    clave: string;
    descripcion: string;
    claveSat: string;
    unidad: string;
    /** Solo los productos traen precio. */
    precio?: string;
    origen: 'Producto' | 'Clave SAT';
};

const MAX_SUGERENCIAS = 8;

function normalizar(texto: string): string {
    return texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

const moneda = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

/**
 * Campo de descripción de un concepto de factura que sugiere conceptos de los catálogos mientras
 * se escribe (por descripción o por clave SAT). Se puede escribir cualquier texto; al elegir una
 * sugerencia, onElegir llena los demás campos.
 */
export function BuscadorConcepto({
    value,
    onChange,
    onElegir,
    sugerencias,
    required,
}: {
    value: string;
    onChange: (texto: string) => void;
    onElegir: (sugerencia: SugerenciaConcepto) => void;
    sugerencias: SugerenciaConcepto[];
    required?: boolean;
}) {
    const [abierto, setAbierto] = useState(false);
    const [activo, setActivo] = useState(0);

    const termino = normalizar(value.trim());
    const coincidencias = termino
        ? sugerencias
              .filter((s) =>
                  normalizar(`${s.descripcion} ${s.claveSat}`).includes(
                      termino,
                  ),
              )
              // Si ya está escrita tal cual, no hace falta sugerirla.
              .filter((s) => normalizar(s.descripcion) !== termino)
              .slice(0, MAX_SUGERENCIAS)
        : [];
    const visible = abierto && coincidencias.length > 0;

    const elegir = (sugerencia: SugerenciaConcepto) => {
        onElegir(sugerencia);
        setAbierto(false);
    };

    return (
        <div className="relative">
            <Input
                value={value}
                onChange={(e) => {
                    onChange(e.target.value);
                    setActivo(0);
                    setAbierto(true);
                }}
                onFocus={() => setAbierto(true)}
                onBlur={() => setAbierto(false)}
                onKeyDown={(e) => {
                    if (!visible) {
                        return;
                    }

                    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                        e.preventDefault();
                        const paso = e.key === 'ArrowDown' ? 1 : -1;
                        setActivo(
                            (i) =>
                                (i + paso + coincidencias.length) %
                                coincidencias.length,
                        );
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        elegir(coincidencias[activo]);
                    } else if (e.key === 'Escape') {
                        e.preventDefault();
                        setAbierto(false);
                    }
                }}
                placeholder="Escribe para buscar en Productos y Claves SAT, o escribe libre"
                autoComplete="off"
                role="combobox"
                aria-expanded={visible}
                aria-autocomplete="list"
                required={required}
            />

            {visible && (
                <div
                    role="listbox"
                    className="absolute z-50 mt-1 max-h-72 w-full overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md"
                >
                    {coincidencias.map((s, i) => (
                        <div
                            key={s.clave}
                            role="option"
                            aria-selected={i === activo}
                            // mousedown para elegir antes de que el campo pierda el foco.
                            onMouseDown={(e) => {
                                e.preventDefault();
                                elegir(s);
                            }}
                            onMouseEnter={() => setActivo(i)}
                            className={cn(
                                'flex cursor-pointer items-start justify-between gap-3 rounded-sm px-2 py-1.5 text-sm',
                                i === activo &&
                                    'bg-accent text-accent-foreground',
                            )}
                        >
                            <div className="min-w-0">
                                <div className="truncate">{s.descripcion}</div>
                                <div className="font-mono text-xs text-muted-foreground">
                                    {s.claveSat} · {s.unidad}
                                    {s.precio !== undefined &&
                                        ` · ${moneda.format(Number(s.precio))}`}
                                </div>
                            </div>
                            <span className="shrink-0 text-xs text-muted-foreground">
                                {s.origen}
                            </span>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
