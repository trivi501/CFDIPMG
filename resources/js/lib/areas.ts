export const TIPOS_AREA = {
    secretaria: 'Secretaría',
    regiduria: 'Regiduría',
} as const;

export type AreaOpcion = {
    id: number;
    nombre: string;
    tipo: keyof typeof TIPOS_AREA;
    /** Cada integrante ve solo lo suyo (p. ej. Cabildo). */
    privada?: boolean;
};
