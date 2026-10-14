<?php
declare(strict_types=1);

namespace App\tests\command;

use App\command\ScanCommand;
use App\command\ScanSymfonyCommand;
use App\tests\AbstractTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Classe ScanSymfonyCommandTest
 *
 * Tests unitaires paramétrés pour la commande ScanSymfonyCommand.
 */
class ScanSymfonyCommandTest extends AbstractTestCase
{
    /**
     * Teste l'exécution de la commande Symfony avec différentes valeurs de retour du scanner sous-jacent.
     */
    #[DataProvider('executionDataProvider')]
    public function testExecuteCommand(int $scanResult, int $expectedStatus, string $expectedOutputContains): void
    {
        // Mock du ScanCommand d'origine
        $scanCommandMock = $this->createMock(ScanCommand::class);
        $scanCommandMock->method('execute')
            ->willReturnCallback(function(array $input, array &$output) use ($scanResult) {
                $output['result'] = $scanResult;
            });

        $command = new ScanSymfonyCommand($scanCommandMock);
        $tester = new CommandTester($command);
        
        $status = $tester->execute([]);
        
        $this->assertEquals($expectedStatus, $status);
        $this->assertStringContainsString($expectedOutputContains, $tester->getDisplay());
    }

    /**
     * Fournisseur de données pour le test d'exécution.
     */
    public static function executionDataProvider(): array
    {
        return [
            'scan_success' => [
                ScanCommand::RESULT_OK,
                Command::SUCCESS,
                'Scan terminé avec succès !'
            ],
            'scan_failure' => [
                ScanCommand::RESULT_KO,
                Command::FAILURE,
                'Le scan a échoué.'
            ],
        ];
    }

    public function testExecuteCommandWithSfOption(): void
    {
        $capturedInput = [];
        $scanCommandMock = $this->createMock(ScanCommand::class);
        $scanCommandMock->expects($this->once())
            ->method('execute')
            ->willReturnCallback(function(array $input, array &$output) use (&$capturedInput) {
                $capturedInput = $input;
                $output['result'] = ScanCommand::RESULT_OK;
                $output['projectsCount'] = 5;
            });

        $command = new ScanSymfonyCommand($scanCommandMock);
        $tester = new CommandTester($command);

        $status = $tester->execute(['--sf' => 'receipt']);

        $this->assertEquals(Command::SUCCESS, $status);
        $this->assertEquals(['sf' => 'receipt'], $capturedInput);
        $this->assertStringContainsString('Scan terminé avec succès !', $tester->getDisplay());
    }

    public function testExecuteCommandWithShortSfOption(): void
    {
        $capturedInput = [];
        $scanCommandMock = $this->createMock(ScanCommand::class);
        $scanCommandMock->expects($this->once())
            ->method('execute')
            ->willReturnCallback(function(array $input, array &$output) use (&$capturedInput) {
                $capturedInput = $input;
                $output['result'] = ScanCommand::RESULT_OK;
            });

        $command = new ScanSymfonyCommand($scanCommandMock);
        $tester = new CommandTester($command);

        $status = $tester->execute(['-s' => 'stores']);

        $this->assertEquals(Command::SUCCESS, $status);
        $this->assertEquals(['sf' => 'stores'], $capturedInput);
    }
}
