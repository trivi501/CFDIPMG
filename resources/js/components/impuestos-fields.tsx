import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type ImpuestosData = { iva: boolean; isr_rate: string };

export function ImpuestosFields({
    data,
    onChange,
    errors,
    idPrefix = '',
}: {
    data: ImpuestosData;
    onChange: (data: ImpuestosData) => void;
    errors: Partial<Record<string, string>>;
    idPrefix?: string;
}) {
    const conIsr = data.isr_rate !== '';

    return (
        <div className="grid gap-3 rounded-lg border p-3">
            <p className="text-sm font-medium">Impuestos</p>
            <div className="flex items-center gap-2">
                <Checkbox
                    id={`${idPrefix}iva`}
                    checked={data.iva}
                    onCheckedChange={(checked) =>
                        onChange({ ...data, iva: checked === true })
                    }
                />
                <Label htmlFor={`${idPrefix}iva`} className="font-normal">
                    Lleva IVA (16%)
                </Label>
            </div>
            <div className="flex flex-wrap items-center gap-2">
                <Checkbox
                    id={`${idPrefix}isr`}
                    checked={conIsr}
                    onCheckedChange={(checked) =>
                        onChange({
                            ...data,
                            isr_rate: checked === true ? '10' : '',
                        })
                    }
                />
                <Label htmlFor={`${idPrefix}isr`} className="font-normal">
                    Retener ISR
                </Label>
                {conIsr && (
                    <div className="flex items-center gap-1">
                        <Input
                            type="number"
                            min="0.01"
                            max="35"
                            step="0.01"
                            className="w-24"
                            aria-label="Tasa de ISR"
                            value={data.isr_rate}
                            onChange={(e) =>
                                onChange({ ...data, isr_rate: e.target.value })
                            }
                            required
                        />
                        <span className="text-sm text-muted-foreground">%</span>
                    </div>
                )}
            </div>
            <p className="text-xs text-muted-foreground">
                Sin IVA ni ISR, la factura sale como "No objeto de impuesto".
            </p>
            <InputError message={errors.iva ?? errors.isr_rate} />
        </div>
    );
}

export function calcularImpuestos(
    importe: number,
    { iva, isr_rate }: ImpuestosData,
    ivaIncluidoEnPrecio = false,
) {
    const subtotal = iva && ivaIncluidoEnPrecio ? importe / 1.16 : importe;
    const montoIva = iva ? subtotal * 0.16 : 0;
    const montoIsr = subtotal * ((Number(isr_rate) || 0) / 100);

    return {
        subtotal,
        iva: montoIva,
        isr: montoIsr,
        total: subtotal + montoIva - montoIsr,
    };
}
