import { Form, Head } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { UserAreaFields } from '@/components/user-area-fields';
import type { AreaOpcion } from '@/lib/areas';
import { dashboard } from '@/routes';
import users from '@/routes/users';

export default function UserCreate({
    passwordInicial,
    roles,
    areas,
}: {
    passwordInicial: string;
    roles: string[];
    areas: AreaOpcion[];
}) {
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

                            <div className="rounded-lg border bg-muted/40 p-3 text-sm">
                                Se creará con la contraseña inicial{' '}
                                <code className="rounded bg-background px-1.5 py-0.5 font-mono font-semibold">
                                    {passwordInicial}
                                </code>
                                . Al iniciar sesión por primera vez tendrá que
                                cambiarla.
                            </div>

                            <UserAreaFields areas={areas} errors={errors} />

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
