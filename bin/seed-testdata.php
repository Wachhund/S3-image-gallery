<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use S3Gallery\Service\S3ClientFactory;

$s3 = S3ClientFactory::create();
$bucket = $_ENV['S3_BUCKET'] ?? 'gallery';

$events = [
    '2026/2026-03-25 Demo-Event',
    '2026/2026-03-20 Testgalerie',
    '2025/2025-12-24 Weihnachten',
];

$palettes = [
    [200, 100, 50],
    [50, 150, 200],
    [100, 200, 80],
    [180, 80, 160],
    [220, 180, 60],
];

$count = 0;
foreach ($events as $dir) {
    for ($i = 1; $i <= 4; $i++) {
        $img = imagecreatetruecolor(800, 600);
        $c = $palettes[($count) % count($palettes)];
        $bg = imagecolorallocate($img, $c[0], $c[1], $c[2]);
        imagefill($img, 0, 0, $bg);

        $white = imagecolorallocate($img, 255, 255, 255);
        $dark = imagecolorallocate($img, 30, 30, 30);
        imagefilledrectangle($img, 250, 240, 550, 360, $dark);
        imagestring($img, 5, 310, 270, "Bild $i", $white);
        imagestring($img, 3, 280, 300, basename($dir), $white);

        $tmp = tempnam(sys_get_temp_dir(), 'seed_');
        imagejpeg($img, $tmp, 90);
        imagedestroy($img);

        $key = "$dir/bild-$i.jpg";
        $s3->putObject([
            'Bucket' => $bucket,
            'Key' => $key,
            'SourceFile' => $tmp,
            'ContentType' => 'image/jpeg',
        ]);
        unlink($tmp);
        echo "Uploaded: $key\n";
        $count++;
    }
}

echo "\nDone. $count test images uploaded.\n";
