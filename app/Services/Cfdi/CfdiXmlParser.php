<?php

namespace App\Services\Cfdi;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

class CfdiXmlParser
{
    protected const TFD_NAMESPACE = 'http://www.sat.gob.mx/TimbreFiscalDigital';

    /**
     * @return array{fecha: ?string, emisor: ?string, uuid: ?string, conceptos: list<array{cantidad: float, articulo: string, costo_unidad: float, iva: float}>}
     */
    public function parse(string $xml): array
    {
        $document = new DOMDocument;

        libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml);
        libxml_clear_errors();

        if (! $loaded || ! $document->documentElement instanceof DOMElement) {
            throw new RuntimeException('El archivo no es un XML válido.');
        }

        $comprobante = $document->documentElement;
        $namespace = $comprobante->namespaceURI;

        if ($namespace === null) {
            throw new RuntimeException('El XML no parece un CFDI (falta el namespace del comprobante).');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('cfdi', $namespace);
        $xpath->registerNamespace('tfd', self::TFD_NAMESPACE);

        $conceptoNodes = $xpath->query('//cfdi:Conceptos/cfdi:Concepto');

        if ($conceptoNodes === false || $conceptoNodes->length === 0) {
            throw new RuntimeException('El XML no contiene conceptos facturados.');
        }

        $conceptos = [];

        foreach ($conceptoNodes as $concepto) {
            if (! $concepto instanceof DOMElement) {
                continue;
            }

            $cantidad = (float) $concepto->getAttribute('Cantidad');
            $importe = (float) $concepto->getAttribute('Importe');

            $iva = 0.0;
            $traslados = $xpath->query('.//cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado', $concepto);

            if ($traslados !== false) {
                foreach ($traslados as $traslado) {
                    if ($traslado instanceof DOMElement && $traslado->getAttribute('Impuesto') === '002') {
                        $iva += (float) $traslado->getAttribute('Importe');
                    }
                }
            }

            $conceptos[] = [
                'cantidad' => $cantidad,
                'articulo' => $concepto->getAttribute('Descripcion'),
                'costo_unidad' => $cantidad > 0 ? round($importe / $cantidad, 2) : $importe,
                'iva' => round($iva, 2),
            ];
        }

        $emisorNode = $xpath->query('//cfdi:Emisor')?->item(0);
        $emisor = $emisorNode instanceof DOMElement ? $emisorNode->getAttribute('Nombre') : '';
        $fecha = $comprobante->getAttribute('Fecha');

        $timbreNode = $xpath->query('//tfd:TimbreFiscalDigital')?->item(0);
        $uuid = $timbreNode instanceof DOMElement ? $timbreNode->getAttribute('UUID') : '';

        return [
            'fecha' => $fecha !== '' ? $fecha : null,
            'emisor' => $emisor !== '' ? $emisor : null,
            'uuid' => $uuid !== '' ? $uuid : null,
            'conceptos' => $conceptos,
        ];
    }
}
