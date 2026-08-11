<?php
/**
 * PulseBoard home-screen icon — matches the site favicon SVG.
 * Renders the ECG logo at 512×512 using GD.
 * URL: /{sheet_id}/pulseboard/icon.png
 */
error_reporting(0);
header('Content-Type: image/png');
header('Cache-Control: public, max-age=3600');

$W = 512; $H = 512;
$img = imagecreatetruecolor($W, $H);

$c_bg  = imagecolorallocate($img, 26,  26,  26);   // #1a1a1a
$c_red = imagecolorallocate($img, 239, 68,  68);   // #ef4444
$c_grn = imagecolorallocate($img, 22,  163, 74);   // #16a34a

imagefill($img, 0, 0, $c_bg);

// Scale from the 180×180 SVG viewBox to 512×512
$s  = 512 / 180;
$sw = max(1, (int)round(10 * $s));   // stroke-width: 10 → ~28px

// ECG polyline + line extension (all as one connected path)
// SVG: polyline points="8,90 42,90 52,38 68,138 82,58 98,90 132,90"
//      line x1="132" y1="90" x2="148" y2="90"
$pts = [
    [8,90],[42,90],[52,38],[68,138],[82,58],[98,90],[132,90],[148,90]
];

imagesetthickness($img, $sw);
for ($i = 0; $i < count($pts) - 1; $i++) {
    imageline($img,
        (int)round($pts[$i][0]   * $s), (int)round($pts[$i][1]   * $s),
        (int)round($pts[$i+1][0] * $s), (int)round($pts[$i+1][1] * $s),
        $c_red
    );
}

// Round line caps — fill circles at each vertex to match stroke-linecap:round
$r_cap = (int)round($sw / 2);
imagesetthickness($img, 1);
foreach ($pts as $p) {
    $px = (int)round($p[0] * $s);
    $py = (int)round($p[1] * $s);
    imagefilledellipse($img, $px, $py, $sw, $sw, $c_red);
}

// Green circle: cx="164" cy="90" r="16"
$cx = (int)round(164 * $s);
$cy = (int)round(90  * $s);
$r  = (int)round(16  * $s);
imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $c_grn);

imagepng($img);
imagedestroy($img);
