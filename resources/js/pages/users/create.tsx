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

export default function UserCreate({ roles }: { roles: string[] }) {
    return (
        <>
            <Head title="Nuevo usuario" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title="Nuevo usuario"
                    description="Crea una cuenta y asigna sus roles."
                />

                <Form
                    {...UserController.store.form()}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nombre</Label>
                                <Input id="name" name="name" required />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Contraseña</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    minLength={8}
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
                                <Button disabled={processing}>Guardar</Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

UserCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Usuarios', href: users.index() },
        { title: 'Nuevo usuario', href: users.create() },
    ],
};
