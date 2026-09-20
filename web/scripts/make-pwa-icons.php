<?php

declare(strict_types=1);

$root = dirname(__DIR__).'/public';
$sourcePath = $root.'/logo.png';
if (!is_file($sourcePath)) {
    fwrite(STDERR, "Missing {$sourcePath}\n");
    exit(1);
}

$source = imagecreatefrompng($sourcePath);
if ($source === false) {
    fwrite(STDERR, "Unable to read logo.png\n");
    exit(1);
}

function fitSquare(GdImage $source, int $size, float $scale = 1.0): GdImage
{
    $canvas = imagecreatetruecolor($size, $size);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefilledrectangle($canvas, 0, 0, $size, $size, $white);
    imagealphablending($canvas, true);
    imagesavealpha($canvas, true);

    $srcW = imagesx($source);
    $srcH = imagesy($source);
    $inner = (int) max(1, round($size * $scale));
    $ratio = min($inner / $srcW, $inner / $srcH);
    $dstW = (int) max(1, round($srcW * $ratio));
    $dstH = (int) max(1, round($srcH * $ratio));
    $dstX = (int) (($size - $dstW) / 2);
    $dstY = (int) (($size - $dstH) / 2);
    imagecopyresampled($canvas, $source, $dstX, $dstY, 0, 0, $dstW, $dstH, $srcW, $srcH);

    return $canvas;
}

function writePng(GdImage $image, string $path): void
{
    imagepng($image, $path, 6);
    imagedestroy($image);
}

writePng(fitSquare($source, 32), $root.'/favicon-32.png');
writePng(fitSquare($source, 180), $root.'/apple-touch-icon.png');
writePng(fitSquare($source, 192), $root.'/icon-192.png');
writePng(fitSquare($source, 512), $root.'/icon-512.png');
writePng(fitSquare($source, 512, 0.8), $root.'/icon-512-maskable.png');
imagedestroy($source);
echo "wrote PWA icons\n";
