import { router } from '@inertiajs/react';
import type { ApexOptions } from 'apexcharts';
import { Building2, FileText, Landmark } from 'lucide-react';
import { useEffect, useState } from 'react';
import Chart from 'react-apexcharts';
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

const MEDIDAS: { value: Medida; label: string }[] = [
    { value: 'monto', label: 'Monto' },
    { value: 'facturas', label: 'Facturas' },
];

// Misma paleta que el panel de siemg: azul de la paleta categórica validada para cada tema.
const PALETA = {
    claro: {
        serie: '#2a78d6',
        rejilla: '#ececea',
        texto: '#6b6a66',
        fuerte: '#0b0b0b',
    },
    oscuro: {
        serie: '#3987e5',
        rejilla: '#2a2a28',
        texto: '#a3a29b',
        fuerte: '#f0efec',
    },
};

const dinero = (v: number) =>
    '$' + v.toLocaleString('es-MX', { maximumFractionDigits: 0 });

const dineroCorto = (v: number) => {
    const abs = Math.abs(v);

    if (abs >= 1_000_000) {
        return (
            '$' +
            (v / 1_000_000).toLocaleString('es-MX', {
                maximumFractionDigits: 1,
            }) +
            ' M'
        );
    }

    if (abs >= 1_000) {
        return (
            '$' +
            (v / 1_000).toLocaleString('es-MX', {
                maximumFractionDigits: abs < 10_000 ? 1 : 0,
            }) +
            ' mil'
        );
    }

    return dinero(v);
};

const facturasTexto = (n: number) =>
    `${n.toLocaleString('es-MX')} ${n === 1 ? 'factura' : 'facturas'}`;

const nombreMes = new Intl.DateTimeFormat('es-MX', { month: 'short' });
const nombreMesLargo = new Intl.DateTimeFormat('es-MX', {
    month: 'long',
    year: 'numeric',
});

function fechaDeMes(mes: string): Date {
    const [anio, numero] = mes.split('-').map(Number);

    return new Date(anio, numero - 1, 1);
}

/** true cuando la app está en tema oscuro (clase `dark` en <html>); se actualiza al cambiar el tema. */
function useEsOscuro() {
    const [oscuro, setOscuro] = useState(
        () =>
            typeof document !== 'undefined' &&
            document.documentElement.classList.contains('dark'),
    );

    useEffect(() => {
        const observador = new MutationObserver(() =>
            setOscuro(document.documentElement.classList.contains('dark')),
        );
        observador.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class'],
        });

        return () => observador.disconnect();
    }, []);

    return oscuro;
}

/** Opciones comunes de ApexCharts para todas las gráficas del panel (igual que en siemg). */
function opcionesBase(oscuro: boolean, medida: Medida): ApexOptions {
    const p = oscuro ? PALETA.oscuro : PALETA.claro;

    return {
        chart: {
            fontFamily: 'inherit',
            foreColor: p.texto,
            background: 'transparent',
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: { enabled: true, speed: 450 },
            parentHeightOffset: 0,
        },
        theme: { mode: oscuro ? 'dark' : 'light' },
        colors: [p.serie],
        grid: {
            borderColor: p.rejilla,
            strokeDashArray: 0,
            xaxis: { lines: { show: false } },
            padding: { left: 6, right: 6 },
        },
        dataLabels: { enabled: false },
        legend: { show: false },
        tooltip: {
            theme: oscuro ? 'dark' : 'light',
            style: { fontSize: '12px' },
        },
        yaxis: {
            labels: {
                formatter: (v: number) =>
                    medida === 'monto'
                        ? dineroCorto(v)
                        : Math.round(v).toLocaleString('es-MX'),
            },
        },
        xaxis: { axisBorder: { show: false }, axisTicks: { show: false } },
        states: { active: { filter: { type: 'none' } } },
    };
}

/** Texto del tooltip: la medida elegida primero y la otra como contexto. */
function detalle(fila: Resumen, medida: Medida): string {
    return medida === 'monto'
        ? `${dinero(fila.monto)} · ${facturasTexto(fila.facturas)}`
        : `${facturasTexto(fila.facturas)} · ${dinero(fila.monto)}`;
}

function Tarjeta({
    children,
    className = '',
}: {
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'rounded-xl border bg-card text-card-foreground shadow-xs',
                className,
            )}
        >
            {children}
        </div>
    );
}

function Grafica({
    titulo,
    subtitulo,
    children,
    className = '',
}: {
    titulo: string;
    subtitulo?: string;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <Tarjeta className={cn('p-4', className)}>
            <div className="mb-2">
                <h3 className="text-sm font-semibold">{titulo}</h3>
                {subtitulo && (
                    <p className="text-xs text-muted-foreground">{subtitulo}</p>
                )}
            </div>
            {children}
        </Tarjeta>
    );
}

function Indicador({
    icono: Icono,
    titulo,
    valor,
    nota,
}: {
    icono: typeof FileText;
    titulo: string;
    valor: string;
    nota?: string;
}) {
    return (
        <Tarjeta className="p-4">
            <p className="text-xs font-medium text-muted-foreground">
                {titulo}
            </p>
            <p className="mt-1 flex items-center gap-2 text-2xl font-semibold tabular-nums">
                <Icono className="h-5 w-5 text-muted-foreground" />
                {valor}
            </p>
            {nota && (
                <p className="mt-2 text-xs text-muted-foreground">{nota}</p>
            )}
        </Tarjeta>
    );
}

function SinDatos({ texto, alto }: { texto: string; alto: number }) {
    return (
        <div
            className="flex items-center justify-center rounded-lg border border-dashed text-sm text-muted-foreground"
            style={{ height: alto }}
        >
            {texto}
        </div>
    );
}

/** Selector segmentado con el mismo estilo que el panel de siemg. */
function Segmentos<T extends string>({
    opciones,
    valor,
    onCambio,
    etiqueta,
}: {
    opciones: { value: T; label: string }[];
    valor: T;
    onCambio: (valor: T) => void;
    etiqueta: string;
}) {
    return (
        <div
            role="group"
            aria-label={etiqueta}
            className="inline-flex rounded-md border p-0.5 text-xs"
        >
            {opciones.map((opcion) => (
                <button
                    key={opcion.value}
                    type="button"
                    aria-pressed={valor === opcion.value}
                    onClick={() => onCambio(opcion.value)}
                    className={cn(
                        'rounded px-2 py-1',
                        valor === opcion.value
                            ? 'bg-foreground text-background'
                            : 'text-muted-foreground hover:text-foreground',
                    )}
                >
                    {opcion.label}
                </button>
            ))}
        </div>
    );
}

/** Barras horizontales: una por área o usuario, con su valor a la derecha. */
function Barras({
    filas,
    medida,
    oscuro,
    clave,
}: {
    filas: Fila[];
    medida: Medida;
    oscuro: boolean;
    clave: string;
}) {
    if (filas.length === 0) {
        return <SinDatos texto="No hay facturas en este periodo." alto={160} />;
    }

    const p = oscuro ? PALETA.oscuro : PALETA.claro;
    const base = opcionesBase(oscuro, medida);
    const opciones: ApexOptions = {
        ...base,
        chart: { ...base.chart, type: 'bar' },
        plotOptions: {
            bar: {
                horizontal: true,
                barHeight: '62%',
                borderRadius: 4,
                borderRadiusApplication: 'end',
                dataLabels: { position: 'top' },
            },
        },
        dataLabels: {
            enabled: true,
            formatter: (v: number) =>
                medida === 'monto' ? dineroCorto(v) : v.toLocaleString('es-MX'),
            offsetX: 34,
            style: { fontSize: '11px', fontWeight: 600, colors: [p.fuerte] },
        },
        xaxis: {
            ...base.xaxis,
            categories: filas.map((fila) => fila.nombre),
            labels: {
                formatter: (v: string) =>
                    medida === 'monto'
                        ? dineroCorto(Number(v))
                        : Number(v).toLocaleString('es-MX'),
            },
        },
        yaxis: { labels: { maxWidth: 180 } },
        grid: {
            ...base.grid,
            xaxis: { lines: { show: true } },
            yaxis: { lines: { show: false } },
            padding: { right: 44 },
        },
        tooltip: {
            ...base.tooltip,
            y: {
                formatter: (_v: number, opts) =>
                    detalle(filas[opts?.dataPointIndex ?? 0], medida),
                title: { formatter: () => '' },
            },
        },
    };

    return (
        <Chart
            key={`${clave}-${oscuro}-${medida}`}
            type="bar"
            height={Math.max(160, filas.length * 44 + 40)}
            options={opciones}
            series={[
                {
                    name: medida === 'monto' ? 'Monto' : 'Facturas',
                    data: filas.map((fila) => fila[medida]),
                },
            ]}
        />
    );
}

/** Área con degradado de los últimos 12 meses. */
function Meses({
    meses,
    medida,
    oscuro,
}: {
    meses: Graficas['porMes'];
    medida: Medida;
    oscuro: boolean;
}) {
    if (meses.every((mes) => mes.facturas === 0)) {
        return (
            <SinDatos
                texto="No hay facturas en los últimos 12 meses."
                alto={290}
            />
        );
    }

    const base = opcionesBase(oscuro, medida);
    const opciones: ApexOptions = {
        ...base,
        chart: { ...base.chart, type: 'area' },
        stroke: { curve: 'smooth', width: 2 },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.45,
                opacityTo: 0.05,
                stops: [0, 95],
            },
        },
        markers: { size: 0, hover: { size: 5 } },
        xaxis: {
            ...base.xaxis,
            categories: meses.map((mes) =>
                nombreMes.format(fechaDeMes(mes.mes)).replace('.', ''),
            ),
            tooltip: { enabled: false },
        },
        tooltip: {
            ...base.tooltip,
            x: {
                formatter: (_v: number, opts) =>
                    nombreMesLargo.format(
                        fechaDeMes(meses[opts?.dataPointIndex ?? 0].mes),
                    ),
            },
            y: {
                formatter: (_v: number, opts) =>
                    detalle(meses[opts?.dataPointIndex ?? 0], medida),
                title: { formatter: () => '' },
            },
        },
    };

    return (
        <Chart
            key={`meses-${oscuro}-${medida}`}
            type="area"
            height={290}
            options={opciones}
            series={[
                {
                    name: medida === 'monto' ? 'Monto' : 'Facturas',
                    data: meses.map((mes) => mes[medida]),
                },
            ]}
        />
    );
}

export function DashboardGraficas({ graficas }: { graficas: Graficas }) {
    const [medida, setMedida] = useState<Medida>('monto');
    const oscuro = useEsOscuro();

    const cambiarPeriodo = (periodo: Graficas['periodo']) => {
        if (periodo === graficas.periodo) {
            return;
        }

        router.get(
            dashboard.url({ query: { periodo } }),
            {},
            { preserveScroll: true, preserveState: true, only: ['graficas'] },
        );
    };

    const totalMeses = graficas.porMes.reduce(
        (suma, mes) => suma + mes.monto,
        0,
    );
    const periodoTexto =
        PERIODOS.find((p) => p.value === graficas.periodo)?.label ?? '';

    return (
        <section className="space-y-4">
            <div className="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h2 className="text-xl font-semibold tracking-tight">
                        Facturas de apoyos
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Facturas vigentes por departamento y, en las áreas
                        privadas como Cabildo, por usuario.
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Segmentos
                        etiqueta="Periodo"
                        opciones={PERIODOS}
                        valor={graficas.periodo}
                        onCambio={cambiarPeriodo}
                    />
                    <Segmentos
                        etiqueta="Medir por"
                        opciones={MEDIDAS}
                        valor={medida}
                        onCambio={setMedida}
                    />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <Indicador
                    icono={FileText}
                    titulo="Facturas en el periodo"
                    valor={graficas.totales.facturas.toLocaleString('es-MX')}
                    nota={periodoTexto}
                />
                <Indicador
                    icono={Landmark}
                    titulo="Monto facturado"
                    valor={dinero(graficas.totales.monto)}
                    nota={periodoTexto}
                />
                <Indicador
                    icono={Building2}
                    titulo="Departamentos con facturas"
                    valor={graficas.porArea.length.toLocaleString('es-MX')}
                    nota={periodoTexto}
                />
            </div>

            <div className="grid gap-4 xl:grid-cols-3">
                <Grafica
                    titulo="Últimos 12 meses"
                    subtitulo={`${dinero(totalMeses)} facturados en el periodo`}
                    className="xl:col-span-2"
                >
                    <Meses
                        meses={graficas.porMes}
                        medida={medida}
                        oscuro={oscuro}
                    />
                </Grafica>

                <Grafica titulo="Por departamento" subtitulo={periodoTexto}>
                    <Barras
                        clave="areas"
                        filas={graficas.porArea}
                        medida={medida}
                        oscuro={oscuro}
                    />
                </Grafica>
            </div>

            {graficas.porUsuario.length > 0 && (
                <div className="grid gap-4 lg:grid-cols-2">
                    {graficas.porUsuario.map((area) => (
                        <Grafica
                            key={area.id}
                            titulo={`${area.nombre}: por usuario`}
                            subtitulo={periodoTexto}
                        >
                            <Barras
                                clave={`usuarios-${area.id}`}
                                filas={area.usuarios}
                                medida={medida}
                                oscuro={oscuro}
                            />
                        </Grafica>
                    ))}
                </div>
            )}
        </section>
    );
}
