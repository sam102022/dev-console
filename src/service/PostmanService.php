<?php
declare(strict_types=1);

namespace App\service;

use App\client\PostmanClient;
use App\factory\LoggerFactory;
use App\util\UtilsLog;
use InvalidArgumentException;
use Monolog\Logger;

class PostmanService
{
    private Logger $logger;

    public function __construct(
        private readonly PostmanClient $client,
        LoggerFactory                  $loggerFactory)
    {
        $this->logger = $loggerFactory->get(__CLASS__);
    }

    // -------------------- Workspaces --------------------

    /**
     * Récupère la liste des workspaces Postman.
     * @return array
     */
    public function getWorkspaces(): array
    {
        return $this->client->get('/workspaces');
    }

    /**
     * Créer un nouveau workspace Postman.
     * @param string $name Nom du workspace
     * @param string $description Description du workspace
     * @return array
     */
    public function createWorkspace(string $name, string $description = ''): array
    {
        $this->logger->info(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
            . "Création du workspace $name");

        return $this->client->post('/workspaces', [
            'workspace' => [
                'name' => $name,
                'type' => 'team',
                'description' => $description ?: 'Créé via interface PHP'
            ]
        ]);
    }

    /**
     * Récupère les détails d'un workspace Postman.
     * @param string $workspaceId ID du workspace
     * @return null[]
     */
    public function getWorkspaceDetails(string $workspaceId): array
    {
        $workspace = $this->client->get("/workspaces/$workspaceId");
        // Environnements et collections
        //$envs = $this->client->get("/environments");
        //$colls = $this->client->get("/collections");

        return [
            'workspace' => $workspace['workspace'] ?? null
            //"environments" => array_values(array_filter($envs['environments'] ?? [], fn($e) => ($e['workspace']['id'] ?? null) === $workspaceId)),
            //"collections" => array_values(array_filter($colls['collections'] ?? [], fn($c) => ($c['workspace']['id'] ?? null) === $workspaceId))
        ];
    }

    // -------------------- Environments --------------------

    /**
     * Récupère la liste des environnements Postman.
     *
     * @param string $workspaceId ID du workspace
     * @param string $name Nom de l'environnement
     * @param array $variables Variables de l'environnement
     * @return array
     */
    public function createEnvironment(string $workspaceId, string $name, array $variables): array
    {
        $this->logger->info(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
            . "Création de l'environnement $name dans le workspace $workspaceId");

        $values = [];
        foreach ($variables as $key => $value) {
            $values[] = [
                'key' => $key,
                'value' => $value,
                'enabled' => true
            ];
        }

        return $this->client->post(
            "/environments?workspace=$workspaceId",
            [
                'environment' => [
                    'name' => $name,
                    'values' => $values
                ]
            ]
        );
    }

    // -------------------- OpenAPI --------------------

    /**
     * Importe un fichier OpenAPI dans un workspace Postman.
     * @param string $workspaceId ID du workspace
     * @param array $fileContent Contenu du fichier
     * @return array
     */
    public function importOpenApi(string $workspaceId, array $fileContent): array
    {
        $this->logger->info(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
            . "Importation d'un fichier dans le workspace $workspaceId");

        if (empty($fileContent)) {
            throw new InvalidArgumentException('Contenu OpenAPI manquant');
        }

        return $this->client->post(
            "/import/openapi?workspace=$workspaceId",
            [
                'type' => 'json',
                'input' => $fileContent
            ]
        );
    }
}
