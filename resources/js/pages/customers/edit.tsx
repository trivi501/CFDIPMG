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

type Customer = {
    id: number;
    legal_name: string;
    rfc: string;
    tax_system: string;
    email: string | null;
    zip: string;
};

export default function CustomerEdit({ customer }: { customer: Customer }) {
    return (
        <>
            <Head title={`Editar ${customer.legal_name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title="Editar cliente"
                    description={customer.legal_name}
                />

                <Form
                    {...CustomerController.update.form(customer)}
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
                                    defaultValue={customer.legal_name}
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
                                    defaultValue={customer.rfc}
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
                                    defaultValue={customer.tax_system}
                                >
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
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    defaultValue={customer.email ?? ''}
                                />
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
                                    defaultValue={customer.zip}
                                />
                                <InputError message={errors.zip} />
                            </div>

                            <div className="flex gap-2">
                                <Button disabled={processing}>
                                    Guardar cambios
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

CustomerEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Clientes', href: customers.index() },
        { title: 'Editar', href: '#' },
    ],
};
