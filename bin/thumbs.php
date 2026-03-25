<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use S3Gallery\Service\DatabaseFactory;
use S3Gallery\Service\S3ClientFactory;
use S3Gallery\Service\ThumbnailGenerator;

try {
    $s3 = S3ClientFactory::create();
    $db = DatabaseFactory::create();
    $bucket = $_ENV['S3_BUCKET'] ?? 'gallery';

    $generator = new ThumbnailGenerator($s3, $db, $bucket);
    $exitCode = $generator->generate();

    exit($exitCode);
} catch (\Throwable $e) {
    fwrite(STDERR, "Fatal error: {$e->getMessage()}\n");
    exit(1);
}
