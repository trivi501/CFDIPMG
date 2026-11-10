import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { TIPOS_AREA } from '@/lib/areas';
import type { AreaOpcion } from '@/lib/areas';

export function UserAreaFields({
    areas,
    errors,
    areaId = null,
    titulo = '',
}: {
    areas: AreaOpcion[];
    errors: Partial<Record<string, string>>;
    areaId?: number | null;
    titulo?: string | null;
}) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="area_id">Área</Label>
                <NativeSelect
                    id="area_id"
                    name="area_id"
                    defaultValue={areaId ?? ''}
                >
                    <option value="">Sin área (solo ve lo que registra)</option>
                    {Object.entries(TIPOS_AREA).map(([tipo, etiqueta]) => (
                        <optgroup key={tipo} label={`${etiqueta}s`}>
                            {areas
                                .filter((area) => area.tipo === tipo)
                                .map((area) => (
                                    <option key={area.id} value={area.id}>
                                        {area.nombre}
                                    </option>
                                ))}
                        </optgroup>
                    ))}
                </NativeSelect>
                <InputError message={errors.area_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="titulo">Título</Label>
                <Input
                    id="titulo"
                    name="titulo"
                    defaultValue={titulo ?? ''}
                    placeholder="Ej. Enlace, Director, Coordinador"
                />
                <InputError message={errors.titulo} />
            </div>
        </>
    );
}
