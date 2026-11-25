import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/** Solo se muestra si la espera pasa de este tiempo, para que no parpadee en respuestas rápidas. */
const RETRASO_MS = 250;

/**
 * Pantalla de "Cargando…" con el emblema del Ayuntamiento mientras se cambia de página o se envía
 * un formulario (cualquier visita de Inertia que muestre progreso; las precargas no). Al montarse
 * quita también la pantalla inicial que pinta app.blade.php mientras arranca la app.
 */
export function PantallaCargando() {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        document.getElementById('cargando-inicial')?.remove();

        let pendientes = 0;
        let temporizador: ReturnType<typeof setTimeout> | undefined;
        const cuenta = (visit: { prefetch: boolean; showProgress: boolean }) =>
            !visit.prefetch && visit.showProgress;

        const quitarStart = router.on('start', (event) => {
            if (!cuenta(event.detail.visit)) {
                return;
            }

            pendientes++;

            if (pendientes === 1) {
                temporizador = setTimeout(() => setVisible(true), RETRASO_MS);
            }
        });

        // 'finish' llega también si la visita se cancela, se interrumpe o falla.
        const quitarFinish = router.on('finish', (event) => {
            if (!cuenta(event.detail.visit)) {
                return;
            }

            pendientes = Math.max(0, pendientes - 1);

            if (pendientes === 0) {
                clearTimeout(temporizador);
                setVisible(false);
            }
        });

        return () => {
            quitarStart();
            quitarFinish();
            clearTimeout(temporizador);
        };
    }, []);

    if (!visible) {
        return null;
    }

    return (
        <div
            role="status"
            aria-live="polite"
            className="fixed inset-0 z-[9999] flex animate-in flex-col items-center justify-center gap-4 duration-200 fade-in"
        >
            <div className="absolute inset-0 bg-white/75 backdrop-blur-[2px] dark:bg-neutral-950/75" />
            <div className="relative flex size-[120px] items-center justify-center">
                <div className="absolute inset-0 animate-spin rounded-full border-4 border-[#7a3446]/15 border-t-[#7a3446] border-r-[#c4956f] [animation-duration:0.9s]" />
                <img
                    src="/img/logo-emblema.png"
                    alt=""
                    className="h-16 w-auto animate-[cargando-latido_1.4s_ease-in-out_infinite]"
                />
            </div>
            <span className="relative font-semibold tracking-wide text-[#7a3446] dark:text-[#c4956f]">
                Cargando…
            </span>
        </div>
    );
}
