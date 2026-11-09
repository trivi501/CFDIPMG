import { PackagePlus } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { asignarPaquete } from '@/lib/paquetes';
import type { FacturaStock, LineaPaquete, Paquete } from '@/lib/paquetes';

export function PaqueteSelector<F extends FacturaStock>({
    paquetes,
    facturas,
    onAgregar,
}: {
    paquetes: Paquete[];
    facturas: F[];
    onAgregar: (lineas: LineaPaquete[], facturas: F[]) => void;
}) {
    const [paqueteId, setPaqueteId] = useState('');
    const [veces, setVeces] = useState('1');
    const [faltantes, setFaltantes] = useState<string[]>([]);

    const paquete = paquetes.find((p) => String(p.id) === paqueteId);

    const agregar = () => {
        if (!paquete || Number(veces) <= 0) {
            return;
        }

        const resultado = asignarPaquete(paquete, Number(veces), facturas);
        setFaltantes(resultado.faltantes);

        if (resultado.faltantes.length === 0) {
            onAgregar(resultado.lineas, resultado.facturas);
            setPaqueteId('');
            setVeces('1');
        }
    };

    if (paquetes.length === 0) {
        return null;
    }

    return (
        <div className="space-y-2 rounded-lg border p-4">
            <div className="flex flex-wrap items-end gap-4">
                <div className="grid min-w-64 gap-2">
                    <Label htmlFor="paquete">Paquete</Label>
                    <Combobox
                        id="paquete"
                        options={paquetes.map((p) => ({
                            value: String(p.id),
                            label: p.nombre,
                        }))}
                        value={paqueteId}
                        onChange={(value) => {
                            setPaqueteId(value);
                            setFaltantes([]);
                        }}
                        placeholder="Busca un paquete"
                    />
                </div>
                <div className="grid w-32 gap-2">
                    <Label htmlFor="paquete_veces">Paquetes</Label>
                    <Input
                        id="paquete_veces"
                        type="number"
                        min="1"
                        step="1"
                        value={veces}
                        onChange={(e) => setVeces(e.target.value)}
                    />
                </div>
                <Button
                    type="button"
                    variant="outline"
                    disabled={!paquete}
                    onClick={agregar}
                >
                    <PackagePlus /> Agregar paquete
                </Button>
            </div>

            {paquete && (
                <p className="text-sm text-muted-foreground">
                    Incluye:{' '}
                    {paquete.articulos
                        .map((a) => `${Number(a.cantidad)} × ${a.descripcion}`)
                        .join(', ')}
                </p>
            )}

            {faltantes.length > 0 && (
                <div className="text-sm text-destructive">
                    <p>
                        No se agregó el paquete porque no alcanza el inventario:
                    </p>
                    <ul className="list-disc pl-5">
                        {faltantes.map((faltante) => (
                            <li key={faltante}>{faltante}</li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}
