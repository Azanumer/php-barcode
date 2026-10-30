<?php
/**
 * Barcode — dependency-free Code 39 barcode generator (PNG via GD + SVG).
 *
 * Code 39 encodes A-Z, 0-9 and " - . $ / + % space". Each character is 9
 * elements (bars and spaces alternating, bar first) of which exactly 3 are
 * wide. The start/stop character is '*'. An optional Mod-43 check digit
 * (the standard Code 39 checksum) can be appended with withChecksum().
 *
 * Patterns below are the standard Code 39 table, written as 9-bit strings
 * (1 = wide element, 0 = narrow). Every pattern contains exactly three 1s;
 * encode() verifies that invariant for the characters it uses, so a typo
 * in the table fails loudly instead of printing a bad barcode.
 */
class Barcode
{
    /** Character => 9-element pattern (bars/spaces, bar first). */
    const PATTERNS = [
        '0' => '000110100', '1' => '100100001', '2' => '001100001',
        '3' => '101100000', '4' => '000110001', '5' => '100110000',
        '6' => '001110000', '7' => '000100101', '8' => '100100100',
        '9' => '001100100', 'A' => '100001001', 'B' => '001001001',
        'C' => '101001000', 'D' => '000011001', 'E' => '100011000',
        'F' => '001011000', 'G' => '000001101', 'H' => '100001100',
        'I' => '001001100', 'J' => '000011100', 'K' => '100000011',
        'L' => '001000011', 'M' => '101000010', 'N' => '000010011',
        'O' => '100010010', 'P' => '001010010', 'Q' => '000000111',
        'R' => '100000110', 'S' => '001000110', 'T' => '000010110',
        'U' => '110000001', 'V' => '011000001', 'W' => '111000000',
        'X' => '010010001', 'Y' => '110010000', 'Z' => '011010000',
        '-' => '010000101', '.' => '110000100', ' ' => '011000100',
        '$' => '010101000', '/' => '010100010', '+' => '010001010',
        '%' => '000101010', '*' => '010010100',
    ];

    /** Character => Mod-43 check value. */
    const VALUES = [
        '0' => 0, '1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5,
        '6' => 6, '7' => 7, '8' => 8, '9' => 9, 'A' => 10, 'B' => 11,
        'C' => 12, 'D' => 13, 'E' => 14, 'F' => 15, 'G' => 16, 'H' => 17,
        'I' => 18, 'J' => 19, 'K' => 20, 'L' => 21, 'M' => 22, 'N' => 23,
        'O' => 24, 'P' => 25, 'Q' => 26, 'R' => 27, 'S' => 28, 'T' => 29,
        'U' => 30, 'V' => 31, 'W' => 32, 'X' => 33, 'Y' => 34, 'Z' => 35,
        '-' => 36, '.' => 37, ' ' => 38, '$' => 39, '/' => 40,
        '+' => 41, '%' => 42,
    ];

    private $narrow;
    private $wide;
    private $height;
    private $withChecksum;

    /**
     * @param int  $narrow       Width in px/units of a narrow element.
     * @param int  $wide         Width of a wide element (typically 3x narrow).
     * @param int  $height       Barcode height in px/units.
     * @param bool $withChecksum Append a Mod-43 check digit.
     */
    public function __construct($narrow = 2, $wide = 6, $height = 80, $withChecksum = false)
    {
        $this->narrow = max(1, (int) $narrow);
        $this->wide = max($this->narrow + 1, (int) $wide);
        $this->height = max(10, (int) $height);
        $this->withChecksum = (bool) $withChecksum;
    }

    /** Validate + normalise input; returns the full string including '*' guards and check digit. */
    public function fullText($text)
    {
        $text = strtoupper(trim((string) $text));
        if ($text === '') {
            throw new InvalidArgumentException('Barcode text must not be empty.');
        }
        foreach (str_split($text) as $ch) {
            if (!isset(self::PATTERNS[$ch]) || $ch === '*') {
                throw new InvalidArgumentException("Character not encodable in Code 39: '{$ch}'.");
            }
        }
        if ($this->withChecksum) {
            $sum = 0;
            foreach (str_split($text) as $ch) {
                $sum += self::VALUES[$ch];
            }
            $text .= array_search($sum % 43, self::VALUES, true);
        }
        return '*' . $text . '*';
    }

    /**
     * Encode to a list of bars: each entry is [x, width] in element units.
     * Useful for custom renderers; renderSvg()/renderPng() use it internally.
     */
    public function bars($text)
    {
        $full = $this->fullText($text);
        $bars = [];
        $x = 0;
        $chars = str_split($full);
        foreach ($chars as $i => $ch) {
            $pattern = self::PATTERNS[$ch];
            // Table-integrity guard: exactly 3 wide elements per character.
            if (substr_count($pattern, '1') !== 3 || strlen($pattern) !== 9) {
                throw new RuntimeException("Invalid Code 39 pattern for '{$ch}'.");
            }
            for ($e = 0; $e < 9; $e++) {
                $w = $pattern[$e] === '1' ? $this->wide : $this->narrow;
                if ($e % 2 === 0) { // bars are the even elements (0,2,4,6,8)
                    $bars[] = [$x, $w];
                }
                $x += $w;
            }
            // Inter-character narrow gap (not after the final stop char).
            if ($i < count($chars) - 1) {
                $x += $this->narrow;
            }
        }
        return [$bars, $x];
    }

    /** Total barcode width in element units. */
    public function width($text)
    {
        list(, $width) = $this->bars($text);
        return $width;
    }

    /** Render an SVG string (no GD needed; scales cleanly for print). */
    public function renderSvg($text)
    {
        list($bars, $width) = $this->bars($text);
        $label = htmlspecialchars($this->fullText($text), ENT_QUOTES, 'UTF-8');
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width
            . '" height="' . ($this->height + 20) . '" viewBox="0 0 ' . $width . ' ' . ($this->height + 20) . '">';
        foreach ($bars as $bar) {
            $svg .= '<rect x="' . $bar[0] . '" y="0" width="' . $bar[1] . '" height="' . $this->height . '" fill="#000"/>';
        }
        $svg .= '<text x="' . ($width / 2) . '" y="' . ($this->height + 16)
            . '" font-family="monospace" font-size="14" text-anchor="middle">' . $label . '</text>';
        return $svg . '</svg>';
    }

    /** Render a PNG string via GD (requires the GD extension). */
    public function renderPng($text)
    {
        if (!function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('GD extension is required for PNG output; use renderSvg() instead.');
        }
        list($bars, $width) = $this->bars($text);
        $img = imagecreatetruecolor($width, $this->height + 20);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $width - 1, $this->height + 19, $white);
        foreach ($bars as $bar) {
            imagefilledrectangle($img, $bar[0], 0, $bar[0] + $bar[1] - 1, $this->height - 1, $black);
        }
        $label = $this->fullText($text);
        $font = 5; // largest built-in GD font
        $tw = imagefontwidth($font) * strlen($label);
        imagestring($img, $font, max(0, (int) (($width - $tw) / 2)), $this->height + 2, $label, $black);
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);
        return $png;
    }
}
