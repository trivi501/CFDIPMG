import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export function useFlashValue<T = unknown>(
    key: string,
): [T | null, () => void] {
    const [value, setValue] = useState<T | null>(null);

    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;

            if (flash && key in flash) {
                setValue(flash[key] as T);
            }
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return [value, () => setValue(null)];
}
