import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { TIPOS_AREA } from '@/lib/areas';

export function AreaFormFields({
    errors,
    nombre = '',
    tipo = 'secretaria',
    privada = false,
}: {
    errors: Partial<Record<string, string>>;
    nombre?: string;
    tipo?: string;
    privada?: boolean;
}) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="nombre">Nombre</Label>
                <Input
                    id="nombre"
                    name="nombre"
                    defaultValue={nombre}
                    placeholder="Ej. Secretaría de Desarrollo Social o Regidor Juan Pérez"
                    required
                    autoFocus
                />
                <InputError message={errors.nombre} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="tipo">Tipo</Label>
                <NativeSelect id="tipo" name="tipo" defaultValue={tipo}>
                    {Object.entries(TIPOS_AREA).map(([value, label]) => (
                        <option key={value} value={value}>
                            {label}
                        </option>
                    ))}
                </NativeSelect>
                <InputError message={errors.tipo} />
            </div>

            <div className="flex items-start gap-3">
                <Checkbox
                    id="privada"
                    name="privada"
                    defaultChecked={privada}
                />
                <div className="grid gap-1">
                    <Label htmlFor="privada">
                        Cada usuario ve solo lo suyo
                    </Label>
                    <p className="text-sm text-muted-foreground">
                        Para áreas como Cabildo, donde están juntos varios
                        regidores y sus asistentes. Si no se marca, los
                        integrantes del área comparten sus apoyos y facturas de
                        compra.
                    </p>
                </div>
            </div>
        </>
    );
}
