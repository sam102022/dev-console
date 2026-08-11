<?php
declare(strict_types=1);

namespace App\tests\Command;

use App\Command\ScanCommand;
use App\exception\TechnicalException;
use App\service\GitlabService;
use App\tests\AbstractTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ScanCommandTest extends AbstractTestCase
{
    private GitlabService $gitlabServiceMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gitlabServiceMock = $this->createMock(GitlabService::class);
    }

    /**
     * @throws \Exception
     */
    #[DataProvider('executeDataProvider')]
    final public function testExecute(
        mixed $scanResult,
        int $expectedOutputResult,
        ?string $expectedError,
        bool $expectException
    ): void {
        // Configuration du mock GitlabService
        if ($expectException) {
            $this->gitlabServiceMock->method('scan')->willThrowException(new TechnicalException('GitLab API error'));
        } else {
            $this->gitlabServiceMock->method('scan')->willReturn($scanResult);
        }

        // Création de l'instance de la commande avec les mocks
        $command = new ScanCommand($this->gitlabServiceMock, $this->loggerFactoryMocked);

        $input = [];
        $output = [];

        // Exécution de la commande
        $command->execute($input, $output);

        // Assertions
        $this->assertSame($expectedOutputResult, $output['result']);

        if ($expectedError) {
            $this->assertStringContainsString($expectedError, $output['errors']['error'][0]);
        } else {
            $this->assertArrayNotHasKey('errors', $output);
        }
    }

    public static function executeDataProvider(): array
    {
        return [
            'Cas nominal: scan réussi' => [
                'scanResult' => [['id' => 1, 'name' => 'Project 1']],
                'expectedOutputResult' => ScanCommand::RESULT_OK,
                'expectedError' => null,
                'expectException' => false,
            ],
            'Cas d\'erreur: aucun projet trouvé' => [
                'scanResult' => null,
                'expectedOutputResult' => ScanCommand::RESULT_KO,
                'expectedError' => null, // L'erreur est loggée mais pas mise dans $output['errors']
                'expectException' => false,
            ],
            'Cas d\'erreur: exception technique' => [
                'scanResult' => null,
                'expectedOutputResult' => ScanCommand::RESULT_KO,
                'expectedError' => 'Erreur lors du scan des projets gitlab',
                'expectException' => true,
            ],
        ];
    }
}