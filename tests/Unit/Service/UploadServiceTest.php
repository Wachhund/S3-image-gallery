<?php

declare(strict_types=1);

namespace S3Gallery\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use S3Gallery\Service\UploadService;

final class UploadServiceTest extends TestCase
{
    private UploadService $service;

    protected function setUp(): void
    {
        $s3 = $this->createMock(\Aws\S3\S3Client::class);
        $pdo = $this->createMock(\PDO::class);

        $this->service = new UploadService($s3, $pdo, 'test-bucket');
    }

    public function testValidateFileRejectsNoFile(): void
    {
        $error = $this->service->validateFile([
            'error' => UPLOAD_ERR_NO_FILE,
            'size' => 0,
            'tmp_name' => '',
        ]);

        self::assertNotNull($error);
    }

    public function testValidateFileRejectsOversizedFile(): void
    {
        $s3 = $this->createMock(\Aws\S3\S3Client::class);
        $pdo = $this->createMock(\PDO::class);
        $service = new UploadService($s3, $pdo, 'test', maxFileSize: 1024);

        $error = $service->validateFile([
            'error' => UPLOAD_ERR_OK,
            'size' => 2048,
            'tmp_name' => '/tmp/fake',
        ]);

        self::assertNotNull($error);
        self::assertStringContainsString('Limit', $error);
    }

    public function testValidateFileRejectsUploadErrors(): void
    {
        $error = $this->service->validateFile([
            'error' => UPLOAD_ERR_INI_SIZE,
            'size' => 100,
            'tmp_name' => '/tmp/fake',
        ]);

        self::assertNotNull($error);
        self::assertStringContainsString('groß', $error);
    }
}
