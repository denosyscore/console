<?php

declare(strict_types=1);

namespace Tests;

use Denosys\Console\Application;
use Denosys\Console\Command\MakeMailCommand;
use Denosys\Container\Container;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Mime\Email;

final class MakeMailCommandTest extends TestCase
{
    public function testGeneratedMessageIsAUsableSymfonyEmail(): void
    {
        $base = $this->temporaryProject();
        $name = 'Welcome' . bin2hex(random_bytes(4));

        try {
            $output = new BufferedOutput();
            $console = new Application(new Container());
            $console->add(new MakeMailCommand($base));

            self::assertSame(0, $console->getSymfonyApplication()->run(new StringInput("make:mail {$name}"), $output));
            require $base . "/app/Mail/{$name}.php";

            $class = 'App\\Mail\\' . $name;
            $message = new $class('sender@example.test', 'recipient@example.test', 'Hello');
            self::assertInstanceOf(Email::class, $message);
            self::assertSame('Hello', $message->getTextBody());
            self::assertSame('sender@example.test', $message->getFrom()[0]->getAddress());
            self::assertSame('recipient@example.test', $message->getTo()[0]->getAddress());
            self::assertStringContainsString('send(new ' . $name, $output->fetch());
        } finally {
            $this->removeProject($base);
        }
    }

    public function testViewOptionGeneratesRenderablePhpAndHtmlMessage(): void
    {
        $base = $this->temporaryProject();
        $name = 'Notice' . bin2hex(random_bytes(4));

        try {
            $output = new BufferedOutput();
            $console = new Application(new Container());
            $console->add(new MakeMailCommand($base));

            self::assertSame(0, $console->getSymfonyApplication()->run(new StringInput("make:mail {$name} --view"), $output));
            require $base . "/app/Mail/{$name}.php";

            $template = $base . '/resources/views/emails/' . strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $name)) . '.php';
            self::assertFileExists($template);
            ob_start();
            require $template;
            $html = ob_get_clean();

            $class = 'App\\Mail\\' . $name;
            $message = new $class('sender@example.test', 'recipient@example.test', $html);
            self::assertInstanceOf(Email::class, $message);
            self::assertSame($html, $message->getHtmlBody());
            self::assertStringContainsString('<h1>', $html);
        } finally {
            $this->removeProject($base);
        }
    }

    public function testUnsupportedMarkdownOptionDoesNotGenerateFiles(): void
    {
        $base = $this->temporaryProject();

        try {
            $output = new BufferedOutput();
            $console = new Application(new Container());
            $console->add(new MakeMailCommand($base));

            self::assertNotSame(0, $console->getSymfonyApplication()->run(new StringInput('make:mail Unsupported --markdown'), $output));
            self::assertFileDoesNotExist($base . '/app/Mail/Unsupported.php');
        } finally {
            $this->removeProject($base);
        }
    }

    private function temporaryProject(): string
    {
        $base = sys_get_temp_dir() . '/denosys-mail-generator-' . bin2hex(random_bytes(6));
        mkdir($base, 0700);

        return $base;
    }

    private function removeProject(string $base): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($base);
    }
}
