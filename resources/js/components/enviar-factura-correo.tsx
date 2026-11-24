import { useForm } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import invoices from '@/routes/invoices';

/**
 * Botón y ventana para enviar por correo el PDF y el XML de una factura (los manda Facturapi).
 * Propone el correo del cliente; si el cliente no tiene, ofrece guardarlo.
 */
export function EnviarFacturaCorreo({
    invoiceId,
    correoCliente,
}: {
    invoiceId: number;
    correoCliente: string | null;
}) {
    const [abierto, setAbierto] = useState(false);
    const form = useForm({
        emails: correoCliente ?? '',
        guardar_en_cliente: false,
    });

    const errores = form.errors as Record<string, string | undefined>;
    const errorCorreos =
        errores.emails ??
        Object.entries(errores).find(([campo]) =>
            campo.startsWith('emails.'),
        )?.[1];

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(invoices.email(invoiceId).url, {
            preserveScroll: true,
            onSuccess: () => setAbierto(false),
        });
    };

    return (
        <Dialog
            open={abierto}
            onOpenChange={(valor) => {
                setAbierto(valor);

                if (valor) {
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Mail /> Enviar por correo
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={enviar} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>Enviar factura por correo</DialogTitle>
                        <DialogDescription>
                            Se envían el PDF y el XML adjuntos. Puedes poner
                            varios correos separados por coma.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="emails">Correo(s)</Label>
                        <Input
                            id="emails"
                            value={form.data.emails}
                            onChange={(e) =>
                                form.setData('emails', e.target.value)
                            }
                            placeholder="cliente@correo.com, otro@correo.com"
                            autoComplete="email"
                            aria-invalid={errorCorreos ? true : undefined}
                            autoFocus
                            required
                        />
                        <InputError message={errorCorreos} />
                    </div>

                    {!correoCliente && (
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="guardar_en_cliente"
                                checked={form.data.guardar_en_cliente}
                                onCheckedChange={(valor) =>
                                    form.setData(
                                        'guardar_en_cliente',
                                        valor === true,
                                    )
                                }
                            />
                            <Label
                                htmlFor="guardar_en_cliente"
                                className="font-normal"
                            >
                                Guardar el primer correo en el cliente para la
                                próxima vez
                            </Label>
                        </div>
                    )}

                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            <Mail />
                            {form.processing ? 'Enviando…' : 'Enviar'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
