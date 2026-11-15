<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LabImageVersionTest extends TestCase
{
    public function test_packaged_laboratory_version_is_expected(): void
    {
        $expected = getenv('INVOIZ_LAB_EXPECTED_VERSION') ?: 'INVOIZ CI/CD laboratory image - Version 1.0';
        self::assertSame($expected, trim(file_get_contents(__DIR__.'/../docker/lab-version.txt')));
    }
}
