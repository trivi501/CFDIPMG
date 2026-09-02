import { Head, Link } from '@inertiajs/react';
import { login } from '@/routes';

export default function Welcome() {
    return (
        <>
            <Head />
            <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6">
                <div className="flex h-[80vh] items-center justify-center rounded-md bg-white p-4">
                    <img
                        src="/img/logo-ayuntamiento-guadalupe.png"
                        alt="Ayuntamiento de Guadalupe"
                        className="h-full w-auto"
                    />
                </div>

                <Link
                    href={login()}
                    className="inline-block rounded-md bg-primary px-6 py-2.5 text-sm font-medium text-primary-foreground hover:opacity-90"
                >
                    Iniciar sesión
                </Link>
            </div>
        </>
    );
}
