import { Form, Head } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import users from '@/routes/users';

type User = {
    id: number;
    name: string;
    email: string;
    roles: { id: number; name: string }[];
};

export default function UserEdit({
    user,
    roles,
}: {
    user: User;
    roles: string[];
}) {
    const userRoleNames = user.roles.map((role) => role.name);

    return (
        <>
            <Head title={`Editar ${user.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading title="Editar usuario" description={user.name} />

                <Form
                    {...UserController.update.form(user)}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nombre</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    defaultValue={user.name}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    defaultValue={user.email}
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    Nueva contraseña
                                </Label>
                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    minLength={8}
                                    placeholder="Dejar en blanco para no cambiar"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label>Roles</Label>
                                <div className="space-y-2 rounded-lg border p-3">
                                    {roles.map((role) => (
                                        <div
                                            key={role}
                                            className="flex items-center gap-2"
                                        >
                                            <Checkbox
                                                id={`role-${role}`}
                                                name="roles[]"
                                                value={role}
                                                defaultChecked={userRoleNames.includes(
                                                    role,
                                                )}
                                            />
                                            <Label
                                                htmlFor={`role-${role}`}
                                                className="font-normal"
                                            >
                                                {role}
                                            </Label>
                                        </div>
                                    ))}
                                </div>
                                <InputError message={errors.roles} />
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

UserEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Usuarios', href: users.index() },
        { title: 'Editar', href: '#' },
    ],
};
