<?php

declare(strict_types=1);

namespace S3Gallery\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use S3Gallery\Service\GalleryService;

final class GalleryServiceTest extends TestCase
{
    public function testValidateDateAcceptsValidDate(): void
    {
        self::assertNull(GalleryService::validateDate('2026-03-25'));
        self::assertNull(GalleryService::validateDate('2024-02-29')); // leap year
    }

    public function testValidateDateRejectsInvalidFormat(): void
    {
        $error = GalleryService::validateDate('25-03-2026');
        self::assertNotNull($error);
        self::assertStringContainsString('YYYY-MM-DD', $error);
    }

    public function testValidateDateRejectsInvalidDay(): void
    {
        $error = GalleryService::validateDate('2026-02-30');
        self::assertNotNull($error);
    }

    public function testValidateDateRejectsNonLeapYear(): void
    {
        $error = GalleryService::validateDate('2025-02-29');
        self::assertNotNull($error);
    }

    public function testValidateEventNameAcceptsValid(): void
    {
        self::assertNull(GalleryService::validateEventName('Geburtstag'));
        self::assertNull(GalleryService::validateEventName('Sommer-Fest 2026'));
        self::assertNull(GalleryService::validateEventName('Weihnachtsfeier'));
        self::assertNull(GalleryService::validateEventName('Event mit Umlauten äöü'));
    }

    public function testValidateEventNameRejectsEmpty(): void
    {
        $error = GalleryService::validateEventName('');
        self::assertNotNull($error);
        self::assertStringContainsString('leer', $error);
    }

    public function testValidateEventNameRejectsSlashes(): void
    {
        self::assertNotNull(GalleryService::validateEventName('path/traversal'));
        self::assertNotNull(GalleryService::validateEventName('back\\slash'));
        self::assertNotNull(GalleryService::validateEventName('dot..dot'));
    }

    public function testValidateEventNameRejectsSpecialChars(): void
    {
        self::assertNotNull(GalleryService::validateEventName('event<script>'));
        self::assertNotNull(GalleryService::validateEventName('name@evil'));
    }

    public function testValidateEventNameRejectsTooLong(): void
    {
        $long = str_repeat('a', 101);
        $error = GalleryService::validateEventName($long);
        self::assertNotNull($error);
        self::assertStringContainsString('100', $error);
    }

    public function testValidateEventNameAcceptsMaxLength(): void
    {
        $exact = str_repeat('a', 100);
        self::assertNull(GalleryService::validateEventName($exact));
    }

    public function testValidateEventNameTrimsWhitespace(): void
    {
        $error = GalleryService::validateEventName('   ');
        self::assertNotNull($error);
    }
}
