<?php

/*
 * Real JPEG photos of exact sizes for the browser suite's upload checks: a face-like shape over camera-like noise at
 * phone dimensions, padded to the target size with JPEG comment segments (valid markers that viewers ignore).
 * Usage: php -d memory_limit=1G images.php <folder>
 */
$folder = $argv[1] ?? throw new InvalidArgumentException('Give the output folder.');
@mkdir($folder, 0777, true);
$targets = ['photo-1_5mb.jpg' => 1.5, 'photo-5mb.jpg' => 5.0, 'photo-9_8mb.jpg' => 9.8, 'photo-11mb.jpg' => 11.0];

function noisyPhoto(int $width, int $height): string
{
    mt_srand(42);
    $image = imagecreatetruecolor($width, $height);
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $base = 150 + (int) (60 * sin($x / 97) * cos($y / 131));
            imagesetpixel($image, $x, $y, ($base + mt_rand(-70, 70)) << 16 | ($base + mt_rand(-70, 70) - 20) << 8 | ($base + mt_rand(-70, 70) - 40));
        }
    }
    imagefilledellipse($image, intdiv($width, 2), intdiv($height * 10, 26), intdiv($width, 3), intdiv($height, 2), (int) imagecolorallocate($image, 200, 160, 130));
    ob_start();
    imagejpeg($image, null, 92);

    return (string) ob_get_clean();
}

foreach ($targets as $name => $megabytes) {
    $target = (int) ($megabytes * 1024 * 1024);
    $width = min(5600, (int) sqrt($target / 2.3 * 4 / 3));
    $jpeg = noisyPhoto($width, intdiv($width * 3, 4));
    while (strlen($jpeg) > $target && $width > 400) {
        $width = (int) ($width * 0.9);
        $jpeg = noisyPhoto($width, intdiv($width * 3, 4));
    }
    $padding = '';
    for ($remaining = $target - strlen($jpeg); $remaining > 4; $remaining -= $chunk + 4) {
        $chunk = min(65533, $remaining - 4);
        $padding .= "\xFF\xFE".pack('n', $chunk + 2).str_repeat('.', $chunk);
    }
    file_put_contents("$folder/$name", substr($jpeg, 0, 2).$padding.substr($jpeg, 2));
}
echo 'images ready', PHP_EOL;
