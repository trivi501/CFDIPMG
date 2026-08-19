import { Form, Head } from '@inertiajs/react';
import { Search } from 'lucide-react';
import ReceiptLookupController from '@/actions/App/Http/Controllers/ReceiptLookupController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import receipts from '@/routes/receipts';

export default function ReceiptLookup() {
    return (
        <>
            <Head title="Buscar recibo" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title="Checar recibo para facturar"
                    description="Busca un pago por su folio en el sistema de pagos municipales para facturarlo."
                />

                <Card className="max-w-lg">
                    <CardHeader>
                        <CardTitle>Folio del pago</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...ReceiptLookupController.store.form()}
                            className="flex items-end gap-2"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid flex-1 gap-2">
                                        <Label htmlFor="folio">Folio</Label>
                                        <Input
                                            id="folio"
                                            name="folio"
                                            required
                                            placeholder="PAG-000098"
                                            autoFocus
                                        />
                                        <InputError message={errors.folio} />
                                    </div>
                                    <Button disabled={processing}>
                                        <Search /> Buscar
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ReceiptLookup.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recibos', href: receipts.index() },
        { title: 'Checar folio', href: receipts.lookup.create() },
    ],
};
