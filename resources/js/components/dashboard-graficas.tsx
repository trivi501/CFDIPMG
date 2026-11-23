import { router } from '@inertiajs/react';
import { Building2, FileText, Landmark, Users } from 'lucide-react';
import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type Resumen = { facturas: number; monto: number };

type Fila = Resumen & { id: number; nombre: string };

export type Graficas = {
    periodo: 'mes' | 'anio' | '12m' | 'todo';
    totales: Resumen;
    porArea: Fila[];
    porUsuario: (Fila & { usuarios: Fila[] })[];
    porMes: (Resumen & { mes: string })[];
};

type Medida = 'monto' | 'facturas';

const PERIODOS: { value: Graficas['periodo']; label: string }[] = [
    { value: 'mes', label: 'Este mes' },
    { value: 'anio', label: 'Este año' },
    { value: '12m', label: '12 meses' },
    { value: 'todo', label: 'Todo' },
];

const moneda = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

const monedaCorta = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    notation: 'compact',
    maximumFractionDigits: 1,
});

const nombreMes = new Intl.DateTimeFormat('es-MX', { month: 'short' });
const nombreMesLargo = new Intl.DateTimeFormat('es-MX', {
    month: 'long',
    year: 'numeric',
});

function fechaDeMes(mes: string): Date {
    const [anio, numero] = mes.split('-').map(Number);

    return new Date(anio, numero - 1, 1);
}

function facturasTexto(n: number): string {
    return `${n} ${n === 1 ? 'factura' : 'facturas'}`;
}

function valor(fila: Resumen, medida: Medida): number {
    return medida === 'monto' ? fila.monto : fila.facturas;
}

/** Barras horizontales: una fila por área o usuario, con su número a la derecha. */
function Barras({ filas, medida }: { filas: Fila[]; medida: Medida }) {
    const maximo = Math.max(...filas.map((fila) => valor(fila, medida)), 0);

    if (filas.length === 0) {
        return (
            <p className="py-8 text-center text-sm text-muted-foreground">
                No hay facturas en este periodo.
            </p>
        );
    }

    return (
        <ul className="space-y-3">
            {filas.map((fila) => {
                const ancho =
                    maximo > 0 ? (valor(fila, medida) / maximo) * 100 : 0;

                return (
                    <li
                        key={fila.id}
                        className="group rounded-md px-2 py-1.5 transition-colors hover:bg-muted/60"
                        title={`${fila.nombre}: ${facturasTexto(fila.facturas)} · ${moneda.format(fila.monto)}`}
                    >
                        <div className="mb-1.5 flex items-baseline justify-between gap-3 text-sm">
                            <span className="truncate font-medium">
                                {fila.nombre}
                            </span>
                            <span className="shrink-0 text-muted-foreground tabular-nums">
                                {medida === 'monto' ? (
                                    <>
                                        <span className="font-medium text-foreground">
                                            {moneda.format(fila.monto)}
                                        </span>
                                        {' · '}
                                        {facturasTexto(fila.facturas)}
                                    </>
                                ) : (
                                    <>
                                        <span className="font-medium text-foreground">
                                            {facturasTexto(fila.facturas)}
                                        </span>
                                        {' · '}
                                        {moneda.format(fila.monto)}
                                    </>
                                )}
                            </span>
                        </div>
                        <div className="h-2.5 w-full overflow-hidden rounded-full bg-grafica-suave">
                            <div
                                className="h-full rounded-full bg-grafica transition-[width] duration-500 ease-out"
                                style={{ width: `${Math.max(ancho, 1.5)}%` }}
                            />
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}

/** Columnas por mes (últimos 12), con el detalle al pasar el mouse. */
function Meses({
    meses,
    medida,
}: {
    meses: Graficas['porMes'];
    medida: Medida;
}) {
    const [activo, setActivo] = useState<number | null>(null);
    const maximo = Math.max(...meses.map((mes) => valor(mes, medida)), 0);
    const marcas = [1, 0.5, 0];

    return (
        <div className="flex gap-3">
            <div className="flex h-48 flex-col justify-between pb-6 text-right text-xs text-muted-foreground tabular-nums">
                {marcas.map((marca) => (
                    <span key={marca} className="-translate-y-1/2 leading-none">
                        {medida === 'monto'
                            ? monedaCorta.format(maximo * marca)
                            : Math.round(maximo * marca)}
                    </span>
                ))}
            </div>
            <div className="relative flex-1">
                <div className="pointer-events-none absolute inset-x-0 top-0 bottom-6 flex flex-col justify-between">
                    {marcas.map((marca) => (
                        <div
                            key={marca}
                            className={cn(
                                'border-t',
                                marca === 0
                                    ? 'border-border'
                                    : 'border-dashed border-border/60',
                            )}
                        />
                    ))}
                </div>
                <div
                    className="relative flex h-48 items-end gap-1 sm:gap-2"
                    onMouseLeave={() => setActivo(null)}
                >
                    {meses.map((mes, indice) => {
                        const alto =
                            maximo > 0
                                ? (valor(mes, medida) / maximo) * 100
                                : 0;
                        const fecha = fechaDeMes(mes.mes);

                        return (
                            <div
                                key={mes.mes}
                                className="relative flex h-full flex-1 flex-col justify-end"
                                onMouseEnter={() => setActivo(indice)}
                                onFocus={() => setActivo(indice)}
                                onBlur={() => setActivo(null)}
                                tabIndex={0}
                                aria-label={`${nombreMesLargo.format(fecha)}: ${facturasTexto(mes.facturas)}, ${moneda.format(mes.monto)}`}
                            >
                                <div className="flex flex-1 items-end pb-6">
                                    <div
                                        className={cn(
                                            'mx-auto w-full max-w-10 rounded-t-[4px] bg-grafica transition-all duration-500 ease-out',
                                            activo !== null &&
                                                activo !== indice &&
                                                'opacity-40',
                                        )}
                                        style={{
                                            height: `${alto}%`,
                                            minHeight:
                                                valor(mes, medida) > 0 ? 3 : 0,
                                        }}
                                    />
                                </div>
                                <span className="absolute inset-x-0 bottom-0 text-center text-[11px] text-muted-foreground capitalize">
                                    {nombreMes.format(fecha).replace('.', '')}
                                </span>
                                {activo === indice && (
                                    <div
                                        className={cn(
                                            'pointer-events-none absolute bottom-full z-10 mb-1 rounded-md border bg-popover px-3 py-2 text-xs whitespace-nowrap text-popover-foreground shadow-md',
                                            indice < 2
                                                ? 'left-0'
                                                : indice > meses.length - 3
                                                  ? 'right-0'
                                                  : 'left-1/2 -translate-x-1/2',
                                        )}
                                    >
                                        <p className="font-medium capitalize">
                                            {nombreMesLargo.format(fecha)}
                                        </p>
                                        <p className="text-muted-foreground tabular-nums">
                                            {facturasTexto(mes.facturas)} ·{' '}
                                            {moneda.format(mes.monto)}
                                        </p>
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}

function Indicador({
    icono: Icono,
    titulo,
    valor,
}: {
    icono: typeof FileText;
    titulo: string;
    valor: string;
}) {
    return (
        <div className="flex items-center gap-3 rounded-lg border bg-card p-4">
            <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-grafica-suave text-grafica">
                <Icono className="size-5" />
            </div>
            <div className="min-w-0">
                <p className="text-sm text-muted-foreground">{titulo}</p>
                <p className="truncate text-xl font-semibold tabular-nums">
                    {valor}
                </p>
            </div>
        </div>
    );
}

export function DashboardGraficas({ graficas }: { graficas: Graficas }) {
    const [medida, setMedida] = useState<Medida>('monto');

    const cambiarPeriodo = (periodo: string) => {
        if (!periodo || periodo === graficas.periodo) {
            return;
        }

        router.get(
            dashboard.url({ query: { periodo } }),
            {},
            { preserveScroll: true, preserveState: true, only: ['graficas'] },
        );
    };

    return (
        <section className="space-y-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 className="text-lg font-semibold">
                        Facturas de apoyos
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Facturas vigentes por departamento y, en las áreas
                        privadas como Cabildo, por usuario.
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        size="sm"
                        value={graficas.periodo}
                        onValueChange={cambiarPeriodo}
                        aria-label="Periodo"
                    >
                        {PERIODOS.map((periodo) => (
                            <ToggleGroupItem
                                key={periodo.value}
                                value={periodo.value}
                                className="px-3"
                            >
                                {periodo.label}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        size="sm"
                        value={medida}
                        onValueChange={(nueva) =>
                            nueva && setMedida(nueva as Medida)
                        }
                        aria-label="Medir por"
                    >
                        <ToggleGroupItem value="monto" className="px-3">
                            Monto
                        </ToggleGroupItem>
                        <ToggleGroupItem value="facturas" className="px-3">
                            Facturas
                        </ToggleGroupItem>
                    </ToggleGroup>
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <Indicador
                    icono={FileText}
                    titulo="Facturas en el periodo"
                    valor={String(graficas.totales.facturas)}
                />
                <Indicador
                    icono={Landmark}
                    titulo="Monto facturado"
                    valor={moneda.format(graficas.totales.monto)}
                />
                <Indicador
                    icono={Building2}
                    titulo="Departamentos con facturas"
                    valor={String(graficas.porArea.length)}
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Building2 className="size-4 text-muted-foreground" />
                            Por departamento
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Barras filas={graficas.porArea} medida={medida} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Últimos 12 meses
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Meses meses={graficas.porMes} medida={medida} />
                    </CardContent>
                </Card>

                {graficas.porUsuario.map((area) => (
                    <Card key={area.id}>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Users className="size-4 text-muted-foreground" />
                                {area.nombre}: por usuario
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Barras filas={area.usuarios} medida={medida} />
                        </CardContent>
                    </Card>
                ))}
            </div>
        </section>
    );
}
