<?php
declare(strict_types=1);

namespace App\service;

use App\client\GitLabClient;
use App\config\AppConfig;
use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\parser\GradleParser;
use App\parser\MavenParser;
use App\util\UtilsLog;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Logger;

class GitlabService
{
    private Logger $logger;
    private array $excludeProjects;

    public function __construct(
        private readonly GitLabClient $client,
        private readonly MavenParser  $mavenParser,
        private readonly GradleParser $gradleParser,
        private readonly FileService $fileService,
        private readonly AppConfig    $appConfig,
        LoggerFactory                 $loggerFactory
    )
    {
        $this->logger = $loggerFactory->get(__CLASS__);
        $this->excludeProjects = $this->appConfig->getParamConfig()->getExcludeProjects();
    }

    /**
     * Nettoie les fichiers de cache locaux (gitlabProjects.json et javaProjects.json)
     */
    public function purgeCache(): void
    {
        $this->logger->debug(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
            . "debut purgeCache");

        $this->fileService->delete('gitlabProjects.json');
        $this->fileService->delete('javaProjects.json');
    }

    /**
     * Scan les projets gitlab
     *
     * @param string|null $pathGroup Le chemin du groupe GitLab à scanner (ex: 'core/dev/pdv').
     * @return array
     * @throws GuzzleException|TechnicalException
     */
    public function getProjects(?string $pathGroup): array
    {
        $filenameProjects = 'gitlabProjects.json';
        if (!$this->fileService->isFileExists($filenameProjects)) {
            $this->logger->info(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
                . "Le fichier : $filenameProjects n'existe pas");

            $projects = $this->client->getAllProjects($pathGroup);
            $this->fileService->save($projects, $filenameProjects);
        } else {
            $this->logger->debug(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
                . "lecture du fichier $filenameProjects");

            $projects = $this->fileService->read($filenameProjects);
        }
        return $projects;
    }

    /**
     * @param string $projectCode
     * @return array|null
     * @throws GuzzleException
     */
    public function getProjectByCode(string $projectCode): ?array
    {
        $this->logger->debug(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
            . "debut");

        $results = $this->scan();

        return array_find($results, static fn($result) => $result['name'] === $projectCode);
    }

    /**
     * Scan les projets gitlab
     *
     * @return array|null
     * @throws GuzzleException|TechnicalException
     */
    public function scan(): ?array
    {
        $this->logger->debug(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
            . 'debut scan');

        $results = [];
        $filenameJava = 'javaProjects.json';

        if (!$this->fileService->isFileExists($filenameJava)) {

            $this->logger->debug(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
                . "Le fichier $filenameJava n'existe pas");

            $projects = $this->getProjects($this->appConfig->getParamConfig()->getGitlabPathGroupDefault());

            $this->logger->debug(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
                . ' nbProjects:' . count($projects));

            foreach ($projects as $project) {
                // Exclusion des projets
                if (in_array($project['name'], $this->excludeProjects, true)) {
                    continue;
                }

                $result = $this->scanPomXml($project);

                if (!empty($result)) {
                    $results[] = $result;
                    continue;
                }

                // Gradle
                /*$result = $this->scanBuildGradle($project);

                if (!empty($result)) {
                    $results[] = $result;
                }*/
            }
            $subsf = array_column($results, 'subsf');
            $name = array_column($results, 'name');

            array_multisort($subsf, SORT_ASC, $name, SORT_ASC, $results);
            $this->fileService->save($results, $filenameJava);
        } else {
            $this->logger->info(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
                . "lecture du fichier $filenameJava");

            $results = $this->fileService->read($filenameJava);
        }

        return $results;
    }

    /**
     * @param int $projectId L'ID du projet GitLab.
     * @param string $path Le chemin du fichier dans le projet GitLab.
     * @return array
     * @throws GuzzleException
     */
    public function getTree(int $projectId, string $path): array
    {
        return $this->client->listRepositoryTree($projectId, $path);
    }

    /**
     * @param int $projectId L'ID du projet GitLab.
     * @param string $file Le nom du fichier dans le projet GitLab.
     * @return array
     */
    public function getFile(int $projectId, string $file): array
    {
        return [
            'content' => $this->client->getFile($projectId, $file, true, 'master')
        ];
    }

    /**
     * Scan un fichier pom.xml d'un projet gitlab
     *
     * @param array $gitLabProject Le projet gitlab à scanner
     * @return array|null
     */
    private function scanPomXml(array $gitLabProject): ?array
    {
        $projectId = $gitLabProject['id'];
        $projectName = $gitLabProject['name'];
        $branch = $gitLabProject['default_branch'] ?? 'main';
        $path = explode('/', $gitLabProject['path_with_namespace']);
        $namePath = explode('/', $gitLabProject['name_with_namespace']);
        // Ex path : core/dev/pdv/receipt/checkout-syst-monitoring
        $sf = $path[2]; // pdv
        $sfName = $namePath[2]; // pdv
        $subsf = $path[3]; // receipt

        // Maven
        $pom = $this->client->getFile($projectId, 'pom.xml', true, $branch);

        // Pour savoir si le projet a été migré sur GKE ou pas
        $chartValuesFile = $this->client->getFile($projectId, 'chart/values.yaml', true, $branch);
        $cloudGCP = (bool)$chartValuesFile;

        if ($pom) {
            return [
                'name' => $projectName,
                'sf' => $sf,
                'sfName' => $sfName,
                'subsf' => $subsf,
                'cloudGCP' => $cloudGCP,
                ...$this->mavenParser->parse($pom)
            ];
        }
        return null;
    }

    private function scanBuildGradle(array $gitLabProject): ?array
    {
        $projectId = $gitLabProject['id'];
        $projectName = $gitLabProject['name'];
        $branch = $gitLabProject['default_branch'] ?? 'main';
        $path = explode('/', $gitLabProject['path_with_namespace']);
        $namePath = explode('/', $gitLabProject['name_with_namespace']);
        // Ex path : core/dev/pdv/receipt/checkout-syst-monitoring
        $sf = $path[2]; // pdv
        $sfName = $namePath[2]; // pdv
        $subsf = $path[3]; // receipt

        $gradle = $this->client->getFile($projectId, 'build.gradle', true, $branch);

        if ($gradle) {
            return [
                'name' => $projectName,
                'sf' => $sf,
                'sfName' => $sfName,
                'subsf' => $subsf,
                ...$this->gradleParser->parse($gradle)
            ];
        }
        return null;
    }
}