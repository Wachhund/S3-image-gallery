<?php

declare(strict_types=1);

namespace S3Gallery\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use S3Gallery\Service\BucketScanner;

final class BucketScannerTest extends TestCase
{
    public function testAllowedExtensionFiltering(): void
    {
        $scanner = $this->createScannerForReflection();

        $method = new \ReflectionMethod(BucketScanner::class, 'hasAllowedExtension');

        self::assertTrue($method->invoke($scanner, 'photo.jpg'));
        self::assertTrue($method->invoke($scanner, 'photo.JPEG'));
        self::assertTrue($method->invoke($scanner, 'photo.png'));
        self::assertTrue($method->invoke($scanner, 'photo.webp'));
        self::assertTrue($method->invoke($scanner, 'photo.gif'));

        self::assertFalse($method->invoke($scanner, 'document.pdf'));
        self::assertFalse($method->invoke($scanner, 'script.php'));
        self::assertFalse($method->invoke($scanner, 'readme.txt'));
        self::assertFalse($method->invoke($scanner, 'noextension'));
    }

    public function testAllowedExtensionIsCaseInsensitive(): void
    {
        $scanner = $this->createScannerForReflection();
        $method = new \ReflectionMethod(BucketScanner::class, 'hasAllowedExtension');

        self::assertTrue($method->invoke($scanner, 'photo.JPG'));
        self::assertTrue($method->invoke($scanner, 'photo.Png'));
        self::assertTrue($method->invoke($scanner, 'photo.GIF'));
    }

    public function testExtensionFilterHandlesPathsWithDots(): void
    {
        $scanner = $this->createScannerForReflection();
        $method = new \ReflectionMethod(BucketScanner::class, 'hasAllowedExtension');

        self::assertTrue($method->invoke($scanner, '2026/event.name/photo.jpg'));
        self::assertFalse($method->invoke($scanner, '2026/event.jpg/readme.txt'));
    }

    public function testCustomExtensionList(): void
    {
        $s3 = $this->createMock(\Aws\S3\S3Client::class);
        $pdo = $this->createMock(\PDO::class);

        $scanner = new BucketScanner($s3, $pdo, 'test', ['tiff', 'bmp']);

        $method = new \ReflectionMethod(BucketScanner::class, 'hasAllowedExtension');

        self::assertTrue($method->invoke($scanner, 'photo.tiff'));
        self::assertTrue($method->invoke($scanner, 'photo.bmp'));
        self::assertFalse($method->invoke($scanner, 'photo.jpg'));
    }

    private function createScannerForReflection(): BucketScanner
    {
        $s3 = $this->createMock(\Aws\S3\S3Client::class);
        $pdo = $this->createMock(\PDO::class);

        return new BucketScanner($s3, $pdo, 'test-bucket');
    }
}
