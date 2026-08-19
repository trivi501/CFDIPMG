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

type Role = {
    id: number;
    name: string;
    permissions: { id: number; name: string }[];
};

export default function RoleEdit({
    role,
    permissions,
}: {
    role: Role;
    permissions: string[];
}) {
    const rolePermissionNames = role.permissions.map(
        (permission) => permission.name,
    );

    return (
        <>
            <Head title={`Editar ${role.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading title="Editar rol" description={role.name} />

                <Form
                    {...RoleController.update.form(role)}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nombre del rol</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    defaultValue={role.name}
                                />
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
                                                defaultChecked={rolePermissionNames.includes(
                                                    permission,
                                                )}
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

RoleEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Roles y permisos', href: roles.index() },
        { title: 'Editar', href: '#' },
    ],
};
