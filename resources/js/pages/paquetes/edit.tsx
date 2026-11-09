import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { PaqueteForm } from '@/components/paquete-form';
import type { FacturaConEmisor } from '@/components/paquete-form';
import type { Paquete } from '@/lib/paquetes';
import { dashboard } from '@/routes';
import paquetes from '@/routes/paquetes';

export default function PaqueteEdit({
    paquete,
    facturas,
}: {
    paquete: Paquete;
    facturas: FacturaConEmisor[];
}) {
    return (
        <>
            <Head title="Editar paquete" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title="Editar paquete"
                    description="Los cambios aplican a los próximos apoyos; los ya registrados no se modifican."
                />
                <PaqueteForm paquete={paquete} facturas={facturas} />
            </div>
        </>
    );
}

PaqueteEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Paquetes', href: paquetes.index() },
        { title: 'Editar', href: '#' },
    ],
};
