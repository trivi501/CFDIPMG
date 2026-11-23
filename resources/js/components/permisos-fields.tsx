import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

/** Un permiso tal como lo manda el servidor: nombre interno, grupo y texto en español. */
export type PermisoOpcion = {
    name: string;
    grupo: string;
    etiqueta: string;
};

/** Casillas de permisos de un rol, agrupadas por módulo y con su descripción en español. */
export function PermisosFields({
    permisos,
    seleccionados = [],
    error,
}: {
    permisos: PermisoOpcion[];
    seleccionados?: string[];
    error?: string;
}) {
    const grupos = permisos.reduce<Map<string, PermisoOpcion[]>>(
        (mapa, permiso) =>
            mapa.set(permiso.grupo, [
                ...(mapa.get(permiso.grupo) ?? []),
                permiso,
            ]),
        new Map(),
    );

    return (
        <div className="grid gap-2">
            <Label>Permisos</Label>
            <div className="grid gap-4 rounded-lg border p-3">
                {[...grupos.entries()].map(([grupo, lista]) => (
                    <fieldset key={grupo} className="grid gap-2">
                        <legend className="mb-1 text-sm font-semibold">
                            {grupo}
                        </legend>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {lista.map((permiso) => (
                                <div
                                    key={permiso.name}
                                    className="flex items-start gap-2"
                                >
                                    <Checkbox
                                        id={`perm-${permiso.name}`}
                                        name="permissions[]"
                                        value={permiso.name}
                                        defaultChecked={seleccionados.includes(
                                            permiso.name,
                                        )}
                                        className="mt-0.5"
                                    />
                                    <Label
                                        htmlFor={`perm-${permiso.name}`}
                                        className="grid gap-0.5 font-normal"
                                    >
                                        <span>{permiso.etiqueta}</span>
                                        <span className="font-mono text-xs text-muted-foreground">
                                            {permiso.name}
                                        </span>
                                    </Label>
                                </div>
                            ))}
                        </div>
                    </fieldset>
                ))}
            </div>
            <InputError message={error} />
        </div>
    );
}
