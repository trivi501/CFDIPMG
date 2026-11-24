import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ClaveSat } from '@/lib/claves-sat';

/** Campos de una clave SAT, compartidos por crear y editar. */
export function ClaveSatFields({
    clave,
    errors,
}: {
    clave?: ClaveSat;
    errors: Partial<Record<'clave' | 'unidad' | 'concepto', string>>;
}) {
    return (
        <>
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="clave">Clave SAT producto/servicio</Label>
                    <Input
                        id="clave"
                        name="clave"
                        defaultValue={clave?.clave}
                        inputMode="numeric"
                        pattern="\d{8}"
                        maxLength={8}
                        placeholder="84111506"
                        required
                        autoFocus={!clave}
                    />
                    <p className="text-xs text-muted-foreground">
                        8 dígitos del catálogo c_ClaveProdServ.
                    </p>
                    <InputError message={errors.clave} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="unidad">Clave SAT unidad</Label>
                    <Input
                        id="unidad"
                        name="unidad"
                        defaultValue={clave?.unidad}
                        maxLength={3}
                        placeholder="E48"
                        className="uppercase"
                        required
                    />
                    <p className="text-xs text-muted-foreground">
                        Del catálogo c_ClaveUnidad, p. ej. E48 (servicio) o H87
                        (pieza).
                    </p>
                    <InputError message={errors.unidad} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="concepto">Concepto</Label>
                <Input
                    id="concepto"
                    name="concepto"
                    defaultValue={clave?.concepto}
                    placeholder="Servicios de facturación"
                    required
                />
                <p className="text-xs text-muted-foreground">
                    Lo que se escribe en la descripción del concepto de la
                    factura.
                </p>
                <InputError message={errors.concepto} />
            </div>
        </>
    );
}
