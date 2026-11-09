import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { PaqueteForm } from '@/components/paquete-form';
import type { FacturaConEmisor } from '@/components/paquete-form';
import { dashboard } from '@/routes';
import paquetes from '@/routes/paquetes';

export default function PaqueteCreate({
    facturas,
}: {
    facturas: FacturaConEmisor[];
}) {
    return (
        <>
            <Head title="Nuevo paquete" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title="Nuevo paquete"
                    description="Define qué artículos lleva cada paquete, por ejemplo 2 sopas y 1 aceite."
                />
                <PaqueteForm facturas={facturas} />
            </div>
        </>
    );
}

PaqueteCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Paquetes', href: paquetes.index() },
        { title: 'Nuevo paquete', href: paquetes.create() },
    ],
};
