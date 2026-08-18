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
}
