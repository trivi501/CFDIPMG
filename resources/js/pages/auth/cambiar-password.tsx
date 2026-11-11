import { Form, Head } from '@inertiajs/react';
import CambiarPasswordController from '@/actions/App/Http/Controllers/Auth/CambiarPasswordController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';

export default function CambiarPassword({
    passwordRules,
}: {
    passwordRules: string;
}) {
    return (
        <>
            <Head title="Cambiar contraseña" />

            <Form
                {...CambiarPasswordController.update.form()}
                resetOnError={['password', 'password_confirmation']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="password">Nueva contraseña</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="new-password"
                                passwordrules={passwordRules}
                                required
                                autoFocus
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Confirmar contraseña
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                passwordrules={passwordRules}
                                required
                            />
                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>

                        <Button
                            className="w-full"
                            disabled={processing}
                            data-test="cambiar-password-button"
                        >
                            {processing && <Spinner />}
                            Guardar y continuar
                        </Button>
                    </>
                )}
            </Form>

            <TextLink href={logout()} className="mx-auto block text-sm">
                Cerrar sesión
            </TextLink>
        </>
    );
}

CambiarPassword.layout = {
    title: 'Cambia tu contraseña',
    description:
        'Entraste con la contraseña inicial. Elige una nueva para continuar.',
};
