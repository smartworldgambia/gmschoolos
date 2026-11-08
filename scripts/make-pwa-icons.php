<?php

$dir = dirname(__DIR__).'/public/icons';
if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}

foreach ([192, 512] as $s) {
    if (! function_exists('imagecreatetruecolor')) {
        file_put_contents(
            "{$dir}/icon-{$s}.svg",
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$s.' '.$s.'"><rect width="100%" height="100%" fill="#1A2B4C"/><circle cx="'.($s / 2).'" cy="'.($s / 2).'" r="'.($s * 0.28).'" fill="#14B8A6"/></svg>'
        );
        echo "svg {$s}\n";
        continue;
    }

    $im = imagecreatetruecolor($s, $s);
    $navy = imagecolorallocate($im, 26, 43, 76);
    $teal = imagecolorallocate($im, 20, 184, 166);
    imagefilledrectangle($im, 0, 0, $s, $s, $navy);
    imagefilledellipse($im, (int) ($s / 2), (int) ($s / 2), (int) ($s * 0.55), (int) ($s * 0.55), $teal);
    imagepng($im, "{$dir}/icon-{$s}.png");
    imagedestroy($im);
    echo "png {$s}\n";
}
