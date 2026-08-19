// Catálogos SAT abreviados de uso más común (CFDI 4.0).
// Catálogo completo: https://www.sat.gob.mx/consultas/formato-de-catalogos

export const TAX_SYSTEMS = [
    { value: '601', label: '601 - General de Ley Personas Morales' },
    { value: '603', label: '603 - Personas Morales con Fines no Lucrativos' },
    {
        value: '605',
        label: '605 - Sueldos y Salarios e Ingresos Asimilados a Salarios',
    },
    { value: '606', label: '606 - Arrendamiento' },
    { value: '608', label: '608 - Demás ingresos' },
    {
        value: '612',
        label: '612 - Personas Físicas con Actividades Empresariales y Profesionales',
    },
    { value: '614', label: '614 - Ingresos por intereses' },
    { value: '616', label: '616 - Sin obligaciones fiscales' },
    { value: '621', label: '621 - Incorporación Fiscal' },
    {
        value: '625',
        label: '625 - Actividades Empresariales con ingresos a través de Plataformas Tecnológicas',
    },
    { value: '626', label: '626 - Régimen Simplificado de Confianza' },
];

export const CFDI_USES = [
    { value: 'G01', label: 'G01 - Adquisición de mercancías' },
    { value: 'G03', label: 'G03 - Gastos en general' },
    { value: 'I01', label: 'I01 - Construcciones' },
    { value: 'P01', label: 'P01 - Por definir' },
    { value: 'S01', label: 'S01 - Sin efectos fiscales' },
];

export const PAYMENT_FORMS = [
    { value: '01', label: '01 - Efectivo' },
    { value: '02', label: '02 - Cheque nominativo' },
    { value: '03', label: '03 - Transferencia electrónica de fondos' },
    { value: '04', label: '04 - Tarjeta de crédito' },
    { value: '28', label: '28 - Tarjeta de débito' },
    { value: '99', label: '99 - Por definir' },
];

export const PAYMENT_METHODS = [
    { value: 'PUE', label: 'PUE - Pago en una sola exhibición' },
    { value: 'PPD', label: 'PPD - Pago en parcialidades o diferido' },
];
