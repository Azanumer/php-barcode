# PHP Barcode (Code 39)

A dependency-free Code 39 barcode generator for PHP — no Composer packages,
no external services. Renders SVG (no extensions needed, scales cleanly for
print) and PNG (via GD).

## Usage

```php
require 'src/Barcode.php';

$barcode = new Barcode(2, 6, 80, true); // narrow, wide, height, Mod-43 checksum
file_put_contents('label.svg', $barcode->renderSvg('SKU-12345'));
file_put_contents('label.png', $barcode->renderPng('SKU-12345')); // needs GD
```

Run the example from the command line:

```bash
php examples/barcode-example.php 'ORDER-987'
# writes barcode.svg and barcode.png in the current directory
```

## Details

- **Charset:** A–Z, 0–9 and ` - . $ / + %` plus space. Input is uppercased
  automatically; anything else throws `InvalidArgumentException`.
- **Checksum:** pass `true` as the 4th constructor argument to append the
  standard Mod-43 check digit (most scanners accept barcodes with or
  without it).
- **Table integrity:** `bars()` verifies every pattern is 9 elements with
  exactly 3 wide elements, so a typo in the encoding table fails loudly
  instead of printing an unscannable barcode.
- **Custom renderers:** `bars()` returns `[x, width]` pairs in element
  units, and `width()` returns the total width — enough to draw the
  barcode on any canvas, PDF or label printer.

## License

MIT — see [LICENSE](LICENSE).
