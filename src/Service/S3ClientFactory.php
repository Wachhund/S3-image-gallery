<?php

declare(strict_types=1);

namespace S3Gallery\Service;

use Aws\S3\S3Client;

final class S3ClientFactory
{
    public static function create(): S3Client
    {
        $endpoint = $_ENV['S3_ENDPOINT'] ?? 'http://storage:9000';
        $accessKey = $_ENV['S3_ACCESS_KEY'] ?? '';
        $secretKey = $_ENV['S3_SECRET_KEY'] ?? '';
        $region = $_ENV['S3_REGION'] ?? 'us-east-1';

        if ($accessKey === '' || $secretKey === '') {
            throw new \RuntimeException('S3_ACCESS_KEY and S3_SECRET_KEY must be set.');
        }

        return new S3Client([
            'version' => 'latest',
            'region' => $region,
            'endpoint' => $endpoint,
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => $accessKey,
                'secret' => $secretKey,
            ],
        ]);
    }
}
