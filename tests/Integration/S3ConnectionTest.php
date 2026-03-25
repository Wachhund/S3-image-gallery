<?php

declare(strict_types=1);

namespace S3Gallery\Tests\Integration;

use PHPUnit\Framework\TestCase;
use S3Gallery\Service\DatabaseFactory;
use S3Gallery\Service\S3ClientFactory;

/**
 * @group integration
 *
 * Requires running Docker containers (db, storage).
 * Run with: composer test -- --group=integration
 */
final class S3ConnectionTest extends TestCase
{
    public function testS3ClientCanListBuckets(): void
    {
        $s3 = S3ClientFactory::create();
        $result = $s3->listBuckets();

        self::assertIsArray($result['Buckets']);
    }

    public function testS3BucketExists(): void
    {
        $s3 = S3ClientFactory::create();
        $bucket = $_ENV['S3_BUCKET'] ?? 'gallery';

        $s3->headBucket(['Bucket' => $bucket]);
        // No exception means bucket exists
        self::assertTrue(true);
    }

    public function testDatabaseConnectionWorks(): void
    {
        $db = DatabaseFactory::create();
        $stmt = $db->query('SELECT 1');
        self::assertEquals(1, $stmt->fetchColumn());
    }

    public function testDatabaseTablesExist(): void
    {
        $db = DatabaseFactory::create();
        $expectedTables = ['dirs', 'images', 'thumbs', 'passkeys', 'otp_status'];

        foreach ($expectedTables as $table) {
            $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
            self::assertNotFalse(
                $stmt->fetchColumn(),
                "Table '{$table}' does not exist."
            );
        }
    }

    public function testS3UploadAndDownload(): void
    {
        $s3 = S3ClientFactory::create();
        $bucket = $_ENV['S3_BUCKET'] ?? 'gallery';
        $key = '_test/integration-test-' . uniqid() . '.txt';
        $content = 'Integration test content';

        $s3->putObject([
            'Bucket' => $bucket,
            'Key' => $key,
            'Body' => $content,
        ]);

        $result = $s3->getObject([
            'Bucket' => $bucket,
            'Key' => $key,
        ]);

        self::assertEquals($content, (string) $result['Body']);

        $s3->deleteObject(['Bucket' => $bucket, 'Key' => $key]);
    }
}
