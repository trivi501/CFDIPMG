import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';

export function ConfirmDeleteButton({
    href,
    confirmMessage = '¿Estás seguro? Esta acción no se puede deshacer.',
}: {
    href: string;
    confirmMessage?: string;
}) {
    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            className="text-destructive hover:text-destructive"
            onClick={() => {
                if (confirm(confirmMessage)) {
                    router.delete(href, { preserveScroll: true });
                }
            }}
        >
            <Trash2 className="size-4" />
        </Button>
    );
}
