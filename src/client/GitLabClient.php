<?php
declare(strict_types=1);

namespace App\client;

use App\config\AppConfig;
use App\factory\LoggerFactory;
use App\util\UtilsLog;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;

class GitLabClient
{
    private const string BASE_URI = '/api/v4/projects';

    private string $token;
    private Logger $logger;

    public function __construct(
        private readonly Client $client,
        AppConfig               $appConfig,
        LoggerFactory           $loggerFactory)
    {
        $this->logger = $loggerFactory->get(__CLASS__);
        $this->token = $appConfig->getParamConfig()->getParamGitLab()->getGitlabToken();
    }

    /**
     * @param string|null $groupPath Chemin du groupe gitlab
     * @return array
     * @throws GuzzleException
     */
    public function getAllProjects(?string $groupPath): array
    {
        $allProjects = [];
        $page = 1;
        $uri = self::BASE_URI;
        if ($groupPath) {
            $groupPathEncoded = urlencode($groupPath);
            $uri = "/api/v4/groups/$groupPathEncoded/projects";
        }

        do {
            $this->logger->info(UtilsLog::prefixLog(__CLASS__, __FUNCTION__, __LINE__)
                . "Recherche à partir du chemin $groupPath sur la page " . $page);

            $response = $this->requestGET($uri, [
                'query' => [
                    'per_page' => 100,
                    'page' => $page,
                    'include_subgroups' => 1 // doit être 1, pas true
                ]
            ]);

            $projects = json_decode($response->getBody()->getContents(), true);

            if (is_array($projects)) {
                $allProjects = array_merge($allProjects, $projects);
            }

            $nextPage = $response->getHeaderLine('X-Next-Page');
            $page = $nextPage ? (int)$nextPage : null;

        } while ($page);

        return $allProjects;
    }

    /**
     * @param string|null $groupPath Chemin du groupe gitlab
     * @param int $pageNumber Numéro de la page
     * @return Response Reponse api gitlab
     * @throws GuzzleException
     */
    private function getProjects(?string $groupPath, int $pageNumber): Response
    {
        $uri = self::BASE_URI;
        if ($groupPath) {
            $groupPathEncoded = urlencode($groupPath);
            $uri = "/api/v4/groups/$groupPathEncoded/projects";
        }
        $this->logger->info(UtilsLog::prefixLog(__CLASS__, __FUNCTION__, __LINE__)
            . "Recherche à partir du chemin $groupPath de la page " . $pageNumber);

        return $this->requestGET($uri, [
            'query' => [
                'per_page' => 100,
                'page' => $pageNumber,
                'include_subgroups' => 1 // doit être 1, pas true
            ]
        ]);
    }

    /**
     * 📁 Lister les fichiers / dossiers
     *
     * @param int $projectId Identifiant du projet gitlab
     * @param string $path Chemin du dossier
     * @param string $branch Nom de la branche
     * @return array
     * @throws GuzzleException
     */
    public function listRepositoryTree(int $projectId, string $path = '', string $branch = 'master'): array
    {
        $this->logger->info(UtilsLog::prefixLog(__CLASS__, __FUNCTION__, __LINE__)
            . "Liste des fichiers avec le path $path");

        $uri = self::BASE_URI . "/$projectId/repository/tree"
            . "?pagination=keyset&per_page=50&order_by=name&sort=asc&ref=$branch&path=" . urlencode($path);

        $this->logger->debug(UtilsLog::prefixLog(__CLASS__, __FUNCTION__, __LINE__)
            . "uri $uri");

        $response = $this->requestGET($uri);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Permet de récupérer un fichier d'un projet
     *
     * @param int $projectId Identifiant du projet gitlab
     * @param string $filePath Chemin du fichier
     * @param bool $raw Mode brut
     * @param string $branch Nom de la branche
     * @return string|array|null
     */
    public function getFile(int $projectId, string $filePath, bool $raw, string $branch = 'main'): string|array|null
    {
        $this->logger->info(UtilsLog::prefixLog(__CLASS__, __FUNCTION__, __LINE__)
            . "Fichier avec le path $filePath");

        try {
            $encodedPath = urlencode($filePath);
            $uri = self::BASE_URI . "/$projectId/repository/files/$encodedPath";
            if ($raw) {
                $uri .= "/raw";
            }
            $uri .= "?ref=$branch";

            $response = $this->requestGET($uri);

            $contents = $response->getBody()->getContents();

            return $raw ? $contents : json_decode($contents, true);

        } catch (GuzzleException $e) {
            $this->logger->error(UtilsLog::prefixLog(__CLASS__, __FUNCTION__, __LINE__)
                . "Une erreur est survenue : " . $e->getMessage());
            return null;
        }
    }

    /**
     * @throws GuzzleException
     */
    private function requestGET(string $uri, ?array $options = null): ResponseInterface
    {
        return $this->request('GET', $uri, $options);
    }

    /**
     * @throws GuzzleException
     */
    private function request(string $method, string $uri, ?array $options = null): ResponseInterface
    {
        if (empty($options)) {
            $options = [];
        }

        // S'assurer que les headers sont toujours fusionnés avec ceux par défaut
        $options['headers'] = array_merge(
            $options['headers'] ?? [],
            $this->getHeaders()
        );

        return $this->client->request($method, $uri, $options);
    }

    private function getHeaders(): array
    {
        return ['PRIVATE-TOKEN' => $this->token];
    }
}