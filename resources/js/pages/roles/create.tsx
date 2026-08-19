import { Form, Head } from '@inertiajs/react';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import roles from '@/routes/roles';

export default function RoleCreate({ permissions }: { permissions: string[] }) {
    return (
        <>
            <Head title="Nuevo rol" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title="Nuevo rol"
                    description="Define un nombre y los permisos que incluye."
                />

                <Form
                    {...RoleController.store.form()}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nombre del rol</Label>
                                <Input id="name" name="name" required />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label>Permisos</Label>
                                <div className="grid grid-cols-2 gap-2 rounded-lg border p-3">
                                    {permissions.map((permission) => (
                                        <div
                                            key={permission}
                                            className="flex items-center gap-2"
                                        >
                                            <Checkbox
                                                id={`perm-${permission}`}
                                                name="permissions[]"
                                                value={permission}
                                            />
                                            <Label
                                                htmlFor={`perm-${permission}`}
                                                className="font-normal"
                                            >
                                                {permission}
                                            </Label>
                                        </div>
                                    ))}
                                </div>
                                <InputError message={errors.permissions} />
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

RoleCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Roles y permisos', href: roles.index() },
        { title: 'Nuevo rol', href: roles.create() },
    ],
};
