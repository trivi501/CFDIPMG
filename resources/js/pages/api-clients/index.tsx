import { Form, Head, router } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import { ConfirmDeleteButton } from '@/components/confirm-delete-button';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useClipboard } from '@/hooks/use-clipboard';
import { useFlashValue } from '@/hooks/use-flash-value';
import { dashboard } from '@/routes';
import apiClients from '@/routes/api-clients';

type ApiClient = {
    id: number;
    name: string;
    is_active: boolean;
    last_used_at: string | null;
};

export default function ApiClientsIndex({
    apiClients: clients,
}: {
    apiClients: ApiClient[];
}) {
    const [newApiKey, clearNewApiKey] = useFlashValue<string>('newApiKey');
    const [copiedText, copy] = useClipboard();

    return (
        <>
            <Head title="API clients" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title="API clients"
                    description="Credenciales para que el sistema externo envíe recibos de pago a POST /api/v1/receipts."
                />

                <Card className="max-w-xl">
                    <CardHeader>
                        <CardTitle>Nueva API key</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...apiClients.store.form()}
                            resetOnSuccess
                            className="flex items-end gap-2"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid flex-1 gap-2">
                                        <Label htmlFor="name">
                                            Nombre del sistema externo
                                        </Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            placeholder="Ej. Sistema de cobranza"
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <Button disabled={processing}>
                                        Generar key
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nombre</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead>Último uso</TableHead>
                                <TableHead className="text-right">
                                    Acciones
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {clients.length === 0 && (
                                <TableEmpty colSpan={4}>
                                    No hay API clients registrados.
                                </TableEmpty>
                            )}
                            {clients.map((client) => (
                                <TableRow key={client.id}>
                                    <TableCell className="font-medium">
                                        {client.name}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                client.is_active
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {client.is_active
                                                ? 'Activo'
                                                : 'Inactivo'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {client.last_used_at ?? 'Nunca'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    router.put(
                                                        apiClients.update(
                                                            client,
                                                        ).url,
                                                        {
                                                            is_active:
                                                                !client.is_active,
                                                        },
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                {client.is_active
                                                    ? 'Desactivar'
                                                    : 'Activar'}
                                            </Button>
                                            <ConfirmDeleteButton
                                                href={
                                                    apiClients.destroy(client)
                                                        .url
                                                }
                                                confirmMessage={`¿Eliminar "${client.name}"? El sistema externo dejará de poder enviar recibos.`}
                                            />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>

            <Dialog
                open={!!newApiKey}
                onOpenChange={(open) => !open && clearNewApiKey()}
            >
                <DialogContent onInteractOutside={(e) => e.preventDefault()}>
                    <DialogTitle>API key generada</DialogTitle>
                    <DialogDescription>
                        Cópiala ahora: por seguridad no se volverá a mostrar.
                        Úsala en el encabezado{' '}
                        <code className="text-foreground">X-Api-Key</code> al
                        llamar a{' '}
                        <code className="text-foreground">
                            POST /api/v1/receipts
                        </code>
                        .
                    </DialogDescription>

                    <div className="flex items-center gap-2">
                        <Input
                            readOnly
                            value={newApiKey ?? ''}
                            className="font-mono text-sm"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            onClick={() => newApiKey && copy(newApiKey)}
                        >
                            {copiedText === newApiKey ? (
                                <Check className="size-4" />
                            ) : (
                                <Copy className="size-4" />
                            )}
                        </Button>
                    </div>

                    <DialogFooter>
                        <Button type="button" onClick={clearNewApiKey}>
                            Ya la copié
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

ApiClientsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'API clients', href: apiClients.index() },
    ],
};
