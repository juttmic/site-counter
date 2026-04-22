<?php

declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Usage: php tools/validate_gif.php <path-to-gif>\n");
    exit(2);
}

$path = $argv[1];
if (!is_file($path)) {
    fwrite(STDERR, "Missing file: {$path}\n");
    exit(2);
}

$bytes = filesize($path);
$fh = fopen($path, 'rb');
$head = $fh ? fread($fh, 16) : '';
if ($fh) {
    fseek($fh, -1, SEEK_END);
    $tail = fread($fh, 1);
    fclose($fh);
} else {
    $tail = '';
}

echo "path={$path}\n";
echo "bytes={$bytes}\n";
echo "head_hex=" . strtoupper(bin2hex((string) $head)) . "\n";
echo "tail_hex=" . strtoupper(bin2hex((string) $tail)) . "\n";

if (!class_exists(Imagick::class)) {
    echo "imagick=0\n";
    exit(0);
}

echo "imagick=1\n";
try {
    $im = new Imagick();
    $im->readImage($path);
    echo "frames=" . $im->getNumberImages() . "\n";
    $im->clear();
} catch (Throwable $e) {
    echo "imagick_error_class=" . $e::class . "\n";
    echo "imagick_error=" . $e->getMessage() . "\n";
}

