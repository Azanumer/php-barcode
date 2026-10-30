<?php
// Usage: php barcode-example.php [TEXT]
// Writes barcode.svg (always) and barcode.png (when GD is available)
// into the current directory. Defaults to a sample SKU.
require __DIR__ . '/../src/Barcode.php';

$text = $argv[1] ?? 'SKU-12345';
$barcode = new Barcode(2, 6, 80, true); // narrow 2, wide 6, Mod-43 checksum on

file_put_contents('barcode.svg', $barcode->renderSvg($text));
echo "SVG: barcode.svg ({$barcode->width($text)} units wide, text: {$barcode->fullText($text)})\n";

try {
    file_put_contents('barcode.png', $barcode->renderPng($text));
    echo "PNG: barcode.png\n";
} catch (RuntimeException $e) {
    echo "PNG skipped: {$e->getMessage()}\n";
}
