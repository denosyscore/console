<?php

declare(strict_types=1);

namespace Tests;

use Denosys\Console\AsCommand;
use Denosys\Console\CommandDefinition;
use Denosys\Console\InputInterface;
use Denosys\Console\Kernel;
use Denosys\Console\OutputInterface;
use Denosys\Container\Container;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class GreetingService
{
    public static int $built = 0;

    public function __construct()
    {
        self::$built++;
    }

    public function greet(string $name): string
    {
        return "Hello, {$name}";
    }
}

#[AsCommand('greet', 'Greet someone')]
final readonly class Greet
{
    public function __construct(private GreetingService $greetings)
    {
    }

    public static function definition(): CommandDefinition
    {
        return (new CommandDefinition())->addArgument('name', CommandDefinition::ARGUMENT_REQUIRED, 'Who to greet');
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln($this->greetings->greet((string) $input->getArgument('name')));

        return 0;
    }
}

#[AsCommand('ping')]
final class Ping
{
    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('pong');

        return 0;
    }
}

/** Commands are plain invokable classes; no base class and no kernel subclass. */
final class AsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        GreetingService::$built = 0;
    }

    public function testAttributeCommandsAreListedWithoutBeingBuilt(): void
    {
        $kernel = new Kernel(new Container(), __DIR__, commands: [Greet::class, Ping::class], discoverIn: null, withCoreCommands: false);
        $symfony = $kernel->bootstrap()->getSymfonyApplication();
        $output = new BufferedOutput();

        self::assertSame(0, $symfony->run(new ArrayInput(['command' => 'list']), $output));
        $listing = $output->fetch();
        self::assertStringContainsString('greet', $listing);
        self::assertStringContainsString('Greet someone', $listing);
        self::assertStringContainsString('ping', $listing);
        self::assertSame(0, GreetingService::$built);
    }

    public function testAttributeCommandsRunWithTheirDependenciesAndArguments(): void
    {
        $kernel = new Kernel(new Container(), __DIR__, commands: [Greet::class], discoverIn: null, withCoreCommands: false);
        $output = new BufferedOutput();

        self::assertSame(0, $kernel->bootstrap()->getSymfonyApplication()->run(new ArrayInput(['command' => 'greet', 'name' => 'Ada']), $output));
        self::assertStringContainsString('Hello, Ada', $output->fetch());
        self::assertSame(1, GreetingService::$built);
    }

    public function testCommandsAreDiscoveredInAnApplicationDirectory(): void
    {
        $base = sys_get_temp_dir() . '/denosys-console-discovery-' . bin2hex(random_bytes(6));
        mkdir($base . '/app/Commands', 0700, true);
        $namespace = 'Discovered' . bin2hex(random_bytes(4));
        file_put_contents($base . '/app/Commands/Hello.php', "<?php\nnamespace {$namespace};\nuse Denosys\\Console\\AsCommand;\nuse Denosys\\Console\\InputInterface;\nuse Denosys\\Console\\OutputInterface;\n#[AsCommand('hello:discovered')]\nfinal class Hello { public function __invoke(InputInterface \$i, OutputInterface \$o): int { return 0; } }\n");
        require $base . '/app/Commands/Hello.php';

        try {
            $kernel = new Kernel(new Container(), $base, discoverIn: 'app/Commands', discoverNamespace: $namespace, withCoreCommands: false);

            self::assertArrayHasKey('hello:discovered', $kernel->bootstrap()->getCommands());
        } finally {
            unlink($base . '/app/Commands/Hello.php');
            rmdir($base . '/app/Commands');
            rmdir($base . '/app');
            rmdir($base);
        }
    }

    public function testTheKernelCannotBeSubclassed(): void
    {
        self::assertTrue((new \ReflectionClass(Kernel::class))->isFinal());
    }
}
