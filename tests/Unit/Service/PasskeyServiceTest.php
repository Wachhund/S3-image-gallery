<?php

declare(strict_types=1);

namespace S3Gallery\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use S3Gallery\Service\PasskeyService;

final class PasskeyServiceTest extends TestCase
{
    public function testVerifyOtpReturnsFalseWhenNotConfigured(): void
    {
        unset($_ENV['S3G_REGISTRATION_OTP']);

        $pdo = $this->createMock(\PDO::class);
        $service = new PasskeyService($pdo, 'Test', 'localhost');

        self::assertFalse($service->verifyOtp('any-value'));
    }

    public function testVerifyOtpReturnsTrueForCorrectOtp(): void
    {
        $_ENV['S3G_REGISTRATION_OTP'] = 'secret-otp-123';

        $pdo = $this->createMock(\PDO::class);
        $service = new PasskeyService($pdo, 'Test', 'localhost');

        self::assertTrue($service->verifyOtp('secret-otp-123'));
    }

    public function testVerifyOtpReturnsFalseForWrongOtp(): void
    {
        $_ENV['S3G_REGISTRATION_OTP'] = 'secret-otp-123';

        $pdo = $this->createMock(\PDO::class);
        $service = new PasskeyService($pdo, 'Test', 'localhost');

        self::assertFalse($service->verifyOtp('wrong-otp'));
    }

    public function testIsOtpConfiguredReturnsFalseWhenEmpty(): void
    {
        $_ENV['S3G_REGISTRATION_OTP'] = '';

        $pdo = $this->createMock(\PDO::class);
        $service = new PasskeyService($pdo, 'Test', 'localhost');

        self::assertFalse($service->isOtpConfigured());
    }

    public function testIsOtpConfiguredReturnsTrueWhenSet(): void
    {
        $_ENV['S3G_REGISTRATION_OTP'] = 'some-otp';

        $pdo = $this->createMock(\PDO::class);
        $service = new PasskeyService($pdo, 'Test', 'localhost');

        self::assertTrue($service->isOtpConfigured());
    }

    protected function tearDown(): void
    {
        unset($_ENV['S3G_REGISTRATION_OTP']);
    }
}
