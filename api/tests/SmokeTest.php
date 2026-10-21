<?php

declare(strict_types=1);

namespace Adab\Api\Tests;

use Adab\Api\Database;
use Adab\Api\Migrator;
use PHPUnit\Framework\TestCase;

/**
 * Smoke test do esqueleto — valida autoload e estrutura básica.
 */
final class SmokeTest extends TestCase
{
    public function testAutoloadCarregaClassesNucleo(): void
    {
        $this->assertTrue(class_exists(Database::class));
        $this->assertTrue(class_exists(Migrator::class));
    }

    public function testConfigExampleSemSegredosReais(): void
    {
        $config = require dirname(__DIR__) . '/config.example.php';
        $this->assertArrayHasKey('db', $config);
        $this->assertSame('', $config['db']['pass']);
        $this->assertStringContainsString('TROCAR', $config['session_secret']);
    }
}
