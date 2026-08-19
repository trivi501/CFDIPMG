import { Form, Head } from '@inertiajs/react';
import CustomerController from '@/actions/App/Http/Controllers/CustomerController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { TAX_SYSTEMS } from '@/lib/sat-catalogs';
import { dashboard } from '@/routes';
import customers from '@/routes/customers';

export default function CustomerCreate() {
    return (
        <>
            <Head title="Nuevo cliente" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title="Nuevo cliente"
                    description="Datos fiscales para timbrar CFDI."
                />

                <Form
                    {...CustomerController.store.form()}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="legal_name">Razón social</Label>
                                <Input
                                    id="legal_name"
                                    name="legal_name"
                                    required
                                />
                                <InputError message={errors.legal_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="rfc">RFC</Label>
                                <Input
                                    id="rfc"
                                    name="rfc"
                                    required
                                    maxLength={13}
                                    className="uppercase"
                                />
                                <InputError message={errors.rfc} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="tax_system">
                                    Régimen fiscal
                                </Label>
                                <NativeSelect
                                    id="tax_system"
                                    name="tax_system"
                                    required
                                    defaultValue=""
                                >
                                    <option value="" disabled>
                                        Selecciona un régimen
                                    </option>
                                    {TAX_SYSTEMS.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                                <InputError message={errors.tax_system} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input id="email" type="email" name="email" />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="zip">
                                    Código postal fiscal
                                </Label>
                                <Input
                                    id="zip"
                                    name="zip"
                                    required
                                    maxLength={5}
                                />
                                <InputError message={errors.zip} />
                            </div>

                            <div className="flex gap-2">
                                <Button disabled={processing}>Guardar</Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

CustomerCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Clientes', href: customers.index() },
        { title: 'Nuevo cliente', href: customers.create() },
    ],
};
