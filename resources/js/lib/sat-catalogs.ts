// Catálogos SAT de CFDI 4.0 para facturas de ingreso.
// Catálogo completo: https://www.sat.gob.mx/consultas/formato-de-catalogos

export const TAX_SYSTEMS = [
    { value: '601', label: '601 - General de Ley Personas Morales' },
    { value: '603', label: '603 - Personas Morales con Fines no Lucrativos' },
    {
        value: '605',
        label: '605 - Sueldos y Salarios e Ingresos Asimilados a Salarios',
    },
    { value: '606', label: '606 - Arrendamiento' },
    {
        value: '607',
        label: '607 - Régimen de Enajenación o Adquisición de Bienes',
    },
    { value: '608', label: '608 - Demás ingresos' },
    {
        value: '610',
        label: '610 - Residentes en el Extranjero sin Establecimiento Permanente en México',
    },
    {
        value: '611',
        label: '611 - Ingresos por Dividendos (socios y accionistas)',
    },
    {
        value: '612',
        label: '612 - Personas Físicas con Actividades Empresariales y Profesionales',
    },
    { value: '614', label: '614 - Ingresos por intereses' },
    {
        value: '615',
        label: '615 - Régimen de los ingresos por obtención de premios',
    },
    { value: '616', label: '616 - Sin obligaciones fiscales' },
    {
        value: '620',
        label: '620 - Sociedades Cooperativas de Producción que optan por diferir sus ingresos',
    },
    { value: '621', label: '621 - Incorporación Fiscal' },
    {
        value: '622',
        label: '622 - Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras',
    },
    { value: '623', label: '623 - Opcional para Grupos de Sociedades' },
    { value: '624', label: '624 - Coordinados' },
    {
        value: '625',
        label: '625 - Actividades Empresariales con ingresos a través de Plataformas Tecnológicas',
    },
    { value: '626', label: '626 - Régimen Simplificado de Confianza' },
];

// Sin CP01 ni CN01: son exclusivos de complementos de pago y de nómina.
export const CFDI_USES = [
    { value: 'G01', label: 'G01 - Adquisición de mercancías' },
    {
        value: 'G02',
        label: 'G02 - Devoluciones, descuentos o bonificaciones',
    },
    { value: 'G03', label: 'G03 - Gastos en general' },
    { value: 'I01', label: 'I01 - Construcciones' },
    {
        value: 'I02',
        label: 'I02 - Mobiliario y equipo de oficina por inversiones',
    },
    { value: 'I03', label: 'I03 - Equipo de transporte' },
    { value: 'I04', label: 'I04 - Equipo de cómputo y accesorios' },
    {
        value: 'I05',
        label: 'I05 - Dados, troqueles, moldes, matrices y herramental',
    },
    { value: 'I06', label: 'I06 - Comunicaciones telefónicas' },
    { value: 'I07', label: 'I07 - Comunicaciones satelitales' },
    { value: 'I08', label: 'I08 - Otra maquinaria y equipo' },
    {
        value: 'D01',
        label: 'D01 - Honorarios médicos, dentales y gastos hospitalarios',
    },
    {
        value: 'D02',
        label: 'D02 - Gastos médicos por incapacidad o discapacidad',
    },
    { value: 'D03', label: 'D03 - Gastos funerales' },
    { value: 'D04', label: 'D04 - Donativos' },
    {
        value: 'D05',
        label: 'D05 - Intereses reales por créditos hipotecarios (casa habitación)',
    },
    { value: 'D06', label: 'D06 - Aportaciones voluntarias al SAR' },
    { value: 'D07', label: 'D07 - Primas por seguros de gastos médicos' },
    {
        value: 'D08',
        label: 'D08 - Gastos de transportación escolar obligatoria',
    },
    {
        value: 'D09',
        label: 'D09 - Depósitos en cuentas para el ahorro y planes de pensiones',
    },
    {
        value: 'D10',
        label: 'D10 - Pagos por servicios educativos (colegiaturas)',
    },
    { value: 'S01', label: 'S01 - Sin efectos fiscales' },
];

// Regla c_UsoCFDI del SAT. Debe coincidir con app/Support/UsoCfdi.php.
const REGIMES_FOR_EXPENSES = [
    '601',
    '603',
    '606',
    '612',
    '620',
    '621',
    '622',
    '623',
    '624',
    '625',
    '626',
];
const REGIMES_FOR_DEDUCTIONS = [
    '605',
    '606',
    '607',
    '608',
    '611',
    '612',
    '614',
    '615',
    '625',
];

export const UNIVERSAL_USE = 'S01';

export function usesForRegime(regime: string | null | undefined) {
    return CFDI_USES.filter(({ value }) => {
        if (value === UNIVERSAL_USE) {
            return TAX_SYSTEMS.some((system) => system.value === regime);
        }

        return (
            value.startsWith('D')
                ? REGIMES_FOR_DEDUCTIONS
                : REGIMES_FOR_EXPENSES
        ).includes(regime ?? '');
    });
}

// Conserva el uso elegido si sigue siendo válido al cambiar de régimen.
export function pickUseForRegime(
    currentUse: string,
    regime: string | null | undefined,
): string {
    const allowed = usesForRegime(regime).map(({ value }) => value);

    if (allowed.includes(currentUse)) {
        return currentUse;
    }

    if (allowed.includes('G03')) {
        return 'G03';
    }

    return allowed.includes(UNIVERSAL_USE) ? UNIVERSAL_USE : '';
}

export const PAYMENT_FORMS = [
    { value: '01', label: '01 - Efectivo' },
    { value: '02', label: '02 - Cheque nominativo' },
    { value: '03', label: '03 - Transferencia electrónica de fondos' },
    { value: '04', label: '04 - Tarjeta de crédito' },
    { value: '05', label: '05 - Monedero electrónico' },
    { value: '06', label: '06 - Dinero electrónico' },
    { value: '08', label: '08 - Vales de despensa' },
    { value: '12', label: '12 - Dación en pago' },
    { value: '13', label: '13 - Pago por subrogación' },
    { value: '14', label: '14 - Pago por consignación' },
    { value: '15', label: '15 - Condonación' },
    { value: '17', label: '17 - Compensación' },
    { value: '23', label: '23 - Novación' },
    { value: '24', label: '24 - Confusión' },
    { value: '25', label: '25 - Remisión de deuda' },
    { value: '26', label: '26 - Prescripción o caducidad' },
    { value: '27', label: '27 - A satisfacción del acreedor' },
    { value: '28', label: '28 - Tarjeta de débito' },
    { value: '29', label: '29 - Tarjeta de servicios' },
    { value: '30', label: '30 - Aplicación de anticipos' },
    { value: '31', label: '31 - Intermediario pagos' },
    { value: '99', label: '99 - Por definir' },
];

export const PAYMENT_METHODS = [
    { value: 'PUE', label: 'PUE - Pago en una sola exhibición' },
    { value: 'PPD', label: 'PPD - Pago en parcialidades o diferido' },
];
