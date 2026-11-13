<?php

declare(strict_types=1);

namespace Tests;

use Denosys\Console\Application;
use Denosys\Console\Command\MakeModelCommand;
use Denosys\Container\Container;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class MakeModelCommandTest extends TestCase
{
    public function testGeneratesARecordWithPersists(): void
    {
        $base = sys_get_temp_dir() . '/denosys-model-generator-' . bin2hex(random_bytes(6));
        mkdir($base, 0700);

        try {
            $console = new Application(new Container());
            $console->add(new MakeModelCommand($base));

            self::assertSame(0, $console->getSymfonyApplication()->run(new StringInput('make:model BlogPost'), new BufferedOutput()));

            $path = $base . '/app/Models/BlogPost.php';
            $code = (string) file_get_contents($path);
            self::assertStringContainsString('namespace App\\Models;', $code);
            self::assertStringContainsString("#[Table('blog_posts')]", $code);
            self::assertStringContainsString('final class BlogPost implements Record', $code);
            self::assertStringContainsString('use Persists;', $code);
            self::assertStringContainsString('public ?int $id = null;', $code);
            self::assertStringNotContainsString('extends', $code);

            exec('php -l ' . escapeshellarg($path) . ' 2>&1', $lint, $status);
            self::assertSame(0, $status, implode("\n", $lint));
        } finally {
            @unlink($base . '/app/Models/BlogPost.php');
            @rmdir($base . '/app/Models');
            @rmdir($base . '/app');
            @rmdir($base);
        }
    }
}
