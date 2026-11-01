<?php

namespace App\Services;

use Picqer\Barcode\BarcodeGeneratorSVG;

class BarcodeService
{
    /** EAN-13 when the code is a valid EAN-13, otherwise Code 128 (handles any SKU-style code). */
    public function svg(string $code, int $height = 40): string
    {
        $generator = new BarcodeGeneratorSVG;
        $type = ProductService::isValidEan13($code) ? $generator::TYPE_EAN_13 : $generator::TYPE_CODE_128;

        $svg = $generator->getBarcode($code, $type, 2, $height);

        // Inline-ready: drop the XML prolog and fixed size so CSS can scale the bars to the label.
        $svg = preg_replace('/^.*?(<svg)/s', '$1', $svg);

        return preg_replace('/<svg width="[^"]*" height="[^"]*"/', '<svg preserveAspectRatio="none"', $svg, 1);
    }
}
