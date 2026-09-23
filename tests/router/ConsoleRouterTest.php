<?php
declare(strict_types=1);

namespace App\tests\router;

use App\command\ScanCommand;
use App\command\ScanSymfonyCommand;
use App\router\ConsoleRouter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ConsoleRouterTest extends TestCase
{
    private ContainerInterface|MockObject $containerMock;
    private ScanCommand|MockObject $scanCommandMock;
    private ScanSymfonyCommand $scanSymfonyCommand;
    private ConsoleRouter $router;

    protected function setUp(): void
    {
        $this->containerMock = $this->createMock(ContainerInterface::class);
        $this->scanCommandMock = $this->createMock(ScanCommand::class);
        
        $this->scanSymfonyCommand = new ScanSymfonyCommand($this->scanCommandMock);

        $this->containerMock->method('get')
            ->with(ScanSymfonyCommand::class)
            ->willReturn($this->scanSymfonyCommand);

        $this->router = new ConsoleRouter($this->containerMock);
    }

    /**
     * Provider for console arguments dispatching tests (parameterized)
     */
    public static function argvProvider(): array
    {
        return [
            'execute scan command' => [
                'argv' => ['bin/console.php', 'ScanCommand'],
                'expectCommandRun' => true
            ],
            'execute with help' => [
                'argv' => ['bin/console.php', '--help'],
                'expectCommandRun' => false
            ],
            'execute with version' => [
                'argv' => ['bin/console.php', '--version'],
                'expectCommandRun' => false
            ]
        ];
    }

    #[DataProvider('argvProvider')]
    public function testDispatch(array $argv, bool $expectCommandRun): void
    {
        if ($expectCommandRun) {
            $stub = new class {
                public function execute(array $arrInput, array &$arrOutput): void
                {
                    $arrOutput['result'] = 'OK'; // ScanCommand::RESULT_OK
                }
            };

            $this->scanCommandMock->expects($this->once())
                ->method('execute')
                ->willReturnCallback([$stub, 'execute']);
        } else {
            $this->scanCommandMock->expects($this->never())
                ->method('execute');
        }

        // Buffer standard output during the dispatch to keep tests quiet
        ob_start();
        $this->router->dispatch($argv);
        ob_end_clean();
    }
}
