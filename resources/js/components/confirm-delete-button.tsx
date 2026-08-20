import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';

export function ConfirmDeleteButton({
    href,
    confirmMessage = '¿Estás seguro? Esta acción no se puede deshacer.',
}: {
    href: string;
    confirmMessage?: string;
}) {
    return (
        <ConfirmDialog
            trigger={
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="text-destructive hover:text-destructive"
                >
                    <Trash2 className="size-4" />
                </Button>
            }
            title="Confirmar eliminación"
            description={confirmMessage}
            confirmLabel="Eliminar"
            onConfirm={() => router.delete(href, { preserveScroll: true })}
        />
    );
}
