import { Head, Link, router, useForm } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import {
    CheckCheck,
    CircleCheck,
    CircleX,
    Eye,
    Pencil,
    Plus,
    Receipt,
    Trash2,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { ImpuestosFields } from '@/components/impuestos-fields';
import { Pagination } from '@/components/pagination';
import type { Paginator } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { usePermissions } from '@/hooks/use-permissions';
import { fechaMx } from '@/lib/fechas';
import { PAYMENT_FORMS } from '@/lib/sat-catalogs';
import { dashboard } from '@/routes';
import apoyos from '@/routes/apoyos';
import facturasCompra from '@/routes/facturas-compra';

type Estatus = 'preparado' | 'aprobado' | 'facturado' | 'rechazado';

type Apoyo = {
    id: number;
    fecha: string;
    sub_total: string;
    iva: string;
    monto_total: string;
    estatus: Estatus;
    beneficiario: { nombre: string } | null;
    persona_apoya: { nombre: string } | null;
    area: { nombre: string } | null;
    facturas_compra: {
        id: number;
        uuid: string | null;
        emisor: string | null;
    }[];
};

type PageProps = {
    apoyos: Paginator<Apoyo>;
    filters: { estatus?: string };
    pendientes: { preparados: number; aprobados: number };
};

/** Una acción sobre un apoyo: se muestra como icono en la fila y en su menú de clic derecho. */
type Accion = {
    clave: string;
    etiqueta: string;
    icono: LucideIcon;
    destructiva?: boolean;
} & (
    | { href: NonNullable<InertiaLinkProps['href']>; ejecutar?: never }
    | { ejecutar: () => void; href?: never }
);

type Confirmacion = {
    titulo: string;
    descripcion: string;
    boton: string;
    destructiva: boolean;
    alConfirmar: () => void;
};

const currency = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

const estatusLabels: Record<Estatus, string> = {
    preparado: 'Preparado',
    aprobado: 'Aprobado',
    facturado: 'Facturado',
    rechazado: 'Rechazado',
};

const estatusVariants: Record<
    Estatus,
    'outline' | 'secondary' | 'default' | 'destructive'
> = {
    preparado: 'outline',
    aprobado: 'secondary',
    facturado: 'default',
    rechazado: 'destructive',
};

export default function ApoyosIndex({
    apoyos: paginator,
    filters,
    pendientes,
}: PageProps) {
    const { can } = usePermissions();
    const puedeVerTodo = can('apoyos.view-all');

    const [confirmacion, setConfirmacion] = useState<Confirmacion | null>(null);
    const [menu, setMenu] = useState<{
        apoyo: Apoyo;
        x: number;
        y: number;
    } | null>(null);

    const nombre = (apoyo: Apoyo) =>
        apoyo.beneficiario?.nombre ?? 'Sin beneficiario';

    const acciones = (apoyo: Apoyo): Accion[] => {
        const lista: Accion[] = [
            {
                clave: 'ver',
                etiqueta: 'Ver',
                icono: Eye,
                href: apoyos.show(apoyo),
            },
        ];

        if (apoyo.estatus === 'preparado' && can('apoyos.approve')) {
            lista.push(
                {
                    clave: 'aprobar',
                    etiqueta: 'Aprobar',
                    icono: CircleCheck,
                    ejecutar: () =>
                        router.patch(
                            apoyos.aprobar(apoyo).url,
                            {},
                            { preserveScroll: true },
                        ),
                },
                {
                    clave: 'rechazar',
                    etiqueta: 'Rechazar',
                    icono: CircleX,
                    destructiva: true,
                    ejecutar: () =>
                        setConfirmacion({
                            titulo: 'Rechazar apoyo',
                            descripcion: `¿Rechazar el apoyo de "${nombre(apoyo)}"? Los artículos que tenía apartados regresan al inventario.`,
                            boton: 'Rechazar',
                            destructiva: true,
                            alConfirmar: () =>
                                router.patch(
                                    apoyos.rechazar(apoyo).url,
                                    {},
                                    { preserveScroll: true },
                                ),
                        }),
                },
            );
        }

        if (apoyo.estatus === 'aprobado' && can('apoyos.facturar')) {
            lista.push({
                clave: 'facturar',
                etiqueta: 'Facturar',
                icono: Receipt,
                href: apoyos.show(apoyo),
            });
        }

        if (can('apoyos.manage')) {
            lista.push(
                {
                    clave: 'editar',
                    etiqueta: 'Editar',
                    icono: Pencil,
                    href: apoyos.edit(apoyo),
                },
                {
                    clave: 'eliminar',
                    etiqueta: 'Eliminar',
                    icono: Trash2,
                    destructiva: true,
                    ejecutar: () =>
                        setConfirmacion({
                            titulo: 'Confirmar eliminación',
                            descripcion: `¿Eliminar el apoyo de "${nombre(apoyo)}"? Esta acción no se puede deshacer.`,
                            boton: 'Eliminar',
                            destructiva: true,
                            alConfirmar: () =>
                                router.delete(apoyos.destroy(apoyo).url, {
                                    preserveScroll: true,
                                }),
                        }),
                },
            );
        }

        return lista;
    };

    // Casillas para elegir cuáles aprobar o facturar. Solo se pueden marcar los que el usuario
    // puede mover de estatus: preparados (si aprueba) y aprobados (si factura).
    const seleccionable = (apoyo: Apoyo) =>
        (apoyo.estatus === 'preparado' && can('apoyos.approve')) ||
        (apoyo.estatus === 'aprobado' && can('apoyos.facturar'));
    const conCasillas = can('apoyos.approve') || can('apoyos.facturar');
    const [seleccion, setSeleccion] = useState<number[]>([]);
    const marcables = paginator.data.filter(seleccionable);
    const marcados = marcables.filter((apoyo) => seleccion.includes(apoyo.id));
    const preparadosMarcados = marcados.filter(
        (apoyo) => apoyo.estatus === 'preparado',
    );
    const aprobadosMarcados = marcados.filter(
        (apoyo) => apoyo.estatus === 'aprobado',
    );
    const haySeleccion = marcados.length > 0;
    const todosMarcados =
        marcables.length > 0 && marcados.length === marcables.length;

    const alternar = (apoyo: Apoyo, marcado: boolean) =>
        setSeleccion((actual) =>
            marcado
                ? [...actual, apoyo.id]
                : actual.filter((id) => id !== apoyo.id),
        );

    const aprobarTodo = () => {
        const cuantos = haySeleccion
            ? preparadosMarcados.length
            : pendientes.preparados;

        setConfirmacion({
            titulo: haySeleccion
                ? 'Aprobar los seleccionados'
                : 'Aprobar todos los preparados',
            descripcion: haySeleccion
                ? `Se aprobarán los ${cuantos} apoyo(s) preparados que marcaste.`
                : `Se aprobarán ${cuantos} apoyo(s) en estatus "Preparado"${puedeVerTodo ? '' : ' de los que puedes ver'}, no solo los de esta página.`,
            boton: haySeleccion ? `Aprobar ${cuantos}` : 'Aprobar todo',
            destructiva: false,
            alConfirmar: () =>
                router.post(
                    apoyos.aprobarTodo().url,
                    haySeleccion
                        ? { ids: preparadosMarcados.map((apoyo) => apoyo.id) }
                        : {},
                    {
                        preserveScroll: true,
                        onSuccess: () => setSeleccion([]),
                    },
                ),
        });
    };

    const [facturarTodoOpen, setFacturarTodoOpen] = useState(false);
    const facturarTodoForm = useForm({
        payment_form: '01',
        iva: false,
        isr_rate: '',
    });

    const facturarTodo = (e: React.FormEvent) => {
        e.preventDefault();
        facturarTodoForm.transform((data) => ({
            ...data,
            ...(haySeleccion
                ? { ids: aprobadosMarcados.map((apoyo) => apoyo.id) }
                : {}),
        }));
        facturarTodoForm.post(apoyos.facturarTodo().url, {
            preserveScroll: true,
            onSuccess: () => {
                setFacturarTodoOpen(false);
                setSeleccion([]);
            },
        });
    };

    return (
        <>
            <Head title="Apoyos" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        title="Apoyos"
                        description={
                            puedeVerTodo
                                ? 'Apoyos económicos entregados a beneficiarios.'
                                : 'Apoyos económicos que has registrado.'
                        }
                    />
                    <div className="flex flex-wrap gap-2">
                        {puedeVerTodo && (
                            <Button variant="outline" asChild>
                                <a href={apoyos.descargarXmls().url}>
                                    Descargar XMLs facturados
                                </a>
                            </Button>
                        )}
                        {can('apoyos.approve') && (
                            <Button
                                variant="outline"
                                onClick={aprobarTodo}
                                disabled={
                                    haySeleccion
                                        ? preparadosMarcados.length === 0
                                        : pendientes.preparados === 0
                                }
                            >
                                <CheckCheck />
                                {haySeleccion
                                    ? 'Aprobar seleccionados'
                                    : 'Aprobar todo'}
                                <Badge variant="secondary">
                                    {haySeleccion
                                        ? preparadosMarcados.length
                                        : pendientes.preparados}
                                </Badge>
                            </Button>
                        )}
                        {can('apoyos.facturar') && (
                            <Button
                                variant="outline"
                                onClick={() => setFacturarTodoOpen(true)}
                                disabled={
                                    haySeleccion
                                        ? aprobadosMarcados.length === 0
                                        : pendientes.aprobados === 0
                                }
                            >
                                <Receipt />
                                {haySeleccion
                                    ? 'Facturar seleccionados'
                                    : 'Facturar todo'}
                                <Badge variant="secondary">
                                    {haySeleccion
                                        ? aprobadosMarcados.length
                                        : pendientes.aprobados}
                                </Badge>
                            </Button>
                        )}
                        {can('apoyos.manage') && (
                            <Button asChild>
                                <Link href={apoyos.create()}>
                                    <Plus /> Nuevo apoyo
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-1">
                    {[
                        { label: 'Todos', value: undefined },
                        { label: 'Preparado', value: 'preparado' },
                        { label: 'Aprobado', value: 'aprobado' },
                        { label: 'Facturado', value: 'facturado' },
                        { label: 'Rechazado', value: 'rechazado' },
                    ].map((option) => (
                        <Button
                            key={option.label}
                            variant={
                                filters.estatus === option.value
                                    ? 'default'
                                    : 'outline'
                            }
                            size="sm"
                            onClick={() =>
                                router.get(
                                    apoyos.index().url,
                                    option.value
                                        ? { estatus: option.value }
                                        : {},
                                    { preserveState: true },
                                )
                            }
                        >
                            {option.label}
                        </Button>
                    ))}
                    {haySeleccion && (
                        <div className="ml-auto flex items-center gap-2 text-sm text-muted-foreground">
                            {marcados.length} seleccionado(s)
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setSeleccion([])}
                            >
                                Quitar selección
                            </Button>
                        </div>
                    )}
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {conCasillas && (
                                    <TableHead className="w-10">
                                        <Checkbox
                                            aria-label="Seleccionar todos los de esta página"
                                            disabled={marcables.length === 0}
                                            checked={
                                                todosMarcados
                                                    ? true
                                                    : haySeleccion
                                                      ? 'indeterminate'
                                                      : false
                                            }
                                            onCheckedChange={(marcado) =>
                                                setSeleccion(
                                                    marcado === true
                                                        ? marcables.map(
                                                              (apoyo) =>
                                                                  apoyo.id,
                                                          )
                                                        : [],
                                                )
                                            }
                                        />
                                    </TableHead>
                                )}
                                <TableHead>Fecha</TableHead>
                                <TableHead>Beneficiario</TableHead>
                                <TableHead>Persona Apoya</TableHead>
                                <TableHead>Área</TableHead>
                                <TableHead>Factura de compra</TableHead>
                                <TableHead className="text-right">
                                    Sub Total
                                </TableHead>
                                <TableHead className="text-right">
                                    IVA
                                </TableHead>
                                <TableHead className="text-right">
                                    Monto Total
                                </TableHead>
                                <TableHead>Estatus</TableHead>
                                <TableHead className="text-right">
                                    Acciones
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {paginator.data.length === 0 && (
                                <TableEmpty colSpan={conCasillas ? 11 : 10}>
                                    No hay apoyos registrados.
                                </TableEmpty>
                            )}
                            {paginator.data.map((apoyo) => (
                                <TableRow
                                    key={apoyo.id}
                                    data-state={
                                        menu?.apoyo.id === apoyo.id ||
                                        marcados.includes(apoyo)
                                            ? 'selected'
                                            : undefined
                                    }
                                    onContextMenu={(e) => {
                                        // Sobre un enlace (p. ej. el XML) se deja el menú del navegador.
                                        if (
                                            (e.target as HTMLElement).closest(
                                                'a',
                                            )
                                        ) {
                                            return;
                                        }

                                        e.preventDefault();
                                        setMenu({
                                            apoyo,
                                            x: e.clientX,
                                            y: e.clientY,
                                        });
                                    }}
                                >
                                    {conCasillas && (
                                        <TableCell>
                                            {seleccionable(apoyo) && (
                                                <Checkbox
                                                    aria-label={`Seleccionar el apoyo de ${nombre(apoyo)}`}
                                                    checked={seleccion.includes(
                                                        apoyo.id,
                                                    )}
                                                    onCheckedChange={(
                                                        marcado,
                                                    ) =>
                                                        alternar(
                                                            apoyo,
                                                            marcado === true,
                                                        )
                                                    }
                                                />
                                            )}
                                        </TableCell>
                                    )}
                                    <TableCell>
                                        {fechaMx(apoyo.fecha)}
                                    </TableCell>
                                    <TableCell className="font-medium">
                                        {apoyo.beneficiario?.nombre ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        {apoyo.persona_apoya?.nombre ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        {apoyo.area?.nombre ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        {apoyo.facturas_compra.length === 0
                                            ? '—'
                                            : apoyo.facturas_compra.map(
                                                  (factura) => (
                                                      <div key={factura.id}>
                                                          {factura.emisor ??
                                                              'Factura de compra'}
                                                          {factura.uuid && (
                                                              <a
                                                                  href={
                                                                      facturasCompra.xml(
                                                                          factura.id,
                                                                      ).url
                                                                  }
                                                                  title={`Descargar XML · ${factura.uuid}`}
                                                                  className="block font-mono text-xs text-muted-foreground hover:underline"
                                                              >
                                                                  {factura.uuid.slice(
                                                                      0,
                                                                      8,
                                                                  )}
                                                                  …
                                                              </a>
                                                          )}
                                                      </div>
                                                  ),
                                              )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {currency.format(
                                            Number(apoyo.sub_total),
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {currency.format(Number(apoyo.iva))}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {currency.format(
                                            Number(apoyo.monto_total),
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                estatusVariants[apoyo.estatus]
                                            }
                                        >
                                            {estatusLabels[apoyo.estatus]}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-0.5">
                                            {acciones(apoyo).map((accion) => (
                                                <BotonAccion
                                                    key={accion.clave}
                                                    accion={accion}
                                                />
                                            ))}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-xs text-muted-foreground">
                        Clic derecho sobre un apoyo para ver sus acciones.
                    </p>
                    <Pagination paginator={paginator} />
                </div>
            </div>

            {/* Menú de clic derecho: se ancla a un punto invisible en la posición del cursor. */}
            <DropdownMenu
                key={menu ? `${menu.apoyo.id}-${menu.x}-${menu.y}` : 'cerrado'}
                open={menu !== null}
                onOpenChange={(abierto) => !abierto && setMenu(null)}
                modal={false}
            >
                <DropdownMenuTrigger asChild>
                    <span
                        aria-hidden
                        className="pointer-events-none fixed size-0"
                        style={{ left: menu?.x ?? 0, top: menu?.y ?? 0 }}
                    />
                </DropdownMenuTrigger>
                {menu && (
                    <DropdownMenuContent align="start" className="w-52">
                        <DropdownMenuLabel className="truncate">
                            {nombre(menu.apoyo)}
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        {acciones(menu.apoyo).map((accion) => {
                            const Icono = accion.icono;
                            const clase = accion.destructiva
                                ? 'text-destructive focus:text-destructive'
                                : undefined;

                            return accion.href ? (
                                <DropdownMenuItem
                                    key={accion.clave}
                                    asChild
                                    className={clase}
                                >
                                    <Link href={accion.href}>
                                        <Icono className="text-current" />
                                        {accion.etiqueta}
                                    </Link>
                                </DropdownMenuItem>
                            ) : (
                                <DropdownMenuItem
                                    key={accion.clave}
                                    className={clase}
                                    onSelect={accion.ejecutar}
                                >
                                    <Icono className="text-current" />
                                    {accion.etiqueta}
                                </DropdownMenuItem>
                            );
                        })}
                    </DropdownMenuContent>
                )}
            </DropdownMenu>

            <Dialog
                open={confirmacion !== null}
                onOpenChange={(abierto) => !abierto && setConfirmacion(null)}
            >
                <DialogContent>
                    <DialogTitle>{confirmacion?.titulo}</DialogTitle>
                    <DialogDescription>
                        {confirmacion?.descripcion}
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button
                            type="button"
                            variant={
                                confirmacion?.destructiva
                                    ? 'destructive'
                                    : 'default'
                            }
                            onClick={() => {
                                confirmacion?.alConfirmar();
                                setConfirmacion(null);
                            }}
                        >
                            {confirmacion?.boton}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={facturarTodoOpen} onOpenChange={setFacturarTodoOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {haySeleccion
                                ? `Facturar ${aprobadosMarcados.length} apoyo(s) seleccionado(s)`
                                : 'Facturar todos los aprobados'}
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={facturarTodo} className="grid gap-4">
                        <p className="text-sm text-muted-foreground">
                            Se generará una factura real para cada apoyo
                            {haySeleccion
                                ? ' aprobado que marcaste'
                                : ' aprobado visible'}
                            , usando el RFC capturado en cada uno (o "Público en
                            general" si no tiene) con estos mismos datos
                            fiscales. Se factura como Egreso, PUE, clave
                            93161700 y unidad C62, con uso G03 (Gastos en
                            general) cuando el régimen del cliente lo permite y
                            S01 (Sin efectos fiscales) cuando no, como en
                            Público en general.
                        </p>
                        <div className="grid gap-2">
                            <Label htmlFor="bulk_payment_form">
                                Forma de pago
                            </Label>
                            <NativeSelect
                                id="bulk_payment_form"
                                value={facturarTodoForm.data.payment_form}
                                onChange={(e) =>
                                    facturarTodoForm.setData(
                                        'payment_form',
                                        e.target.value,
                                    )
                                }
                            >
                                {PAYMENT_FORMS.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        </div>
                        <ImpuestosFields
                            idPrefix="bulk_"
                            data={facturarTodoForm.data}
                            onChange={(impuestos) =>
                                facturarTodoForm.setData({
                                    ...facturarTodoForm.data,
                                    ...impuestos,
                                })
                            }
                            errors={facturarTodoForm.errors}
                        />
                        <DialogFooter>
                            <Button
                                type="submit"
                                disabled={facturarTodoForm.processing}
                            >
                                Facturar todo
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function BotonAccion({ accion }: { accion: Accion }) {
    const Icono = accion.icono;
    const clase = accion.destructiva
        ? 'text-destructive hover:text-destructive'
        : undefined;

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                {accion.href ? (
                    <Button
                        variant="ghost"
                        size="icon"
                        className={clase}
                        asChild
                    >
                        <Link href={accion.href} aria-label={accion.etiqueta}>
                            <Icono className="size-4" />
                        </Link>
                    </Button>
                ) : (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className={clase}
                        aria-label={accion.etiqueta}
                        onClick={accion.ejecutar}
                    >
                        <Icono className="size-4" />
                    </Button>
                )}
            </TooltipTrigger>
            <TooltipContent>{accion.etiqueta}</TooltipContent>
        </Tooltip>
    );
}

ApoyosIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Apoyos', href: apoyos.index() },
    ],
};
