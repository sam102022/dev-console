<?php
declare(strict_types=1);

namespace App\service;

use App\client\GitLabClient;
use App\config\AppConfig;
use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\model\EnumEnvironment;
use App\model\GitlabProject;
use App\model\Project;
use App\parser\GradleParser;
use App\parser\MavenParser;
use App\repository\GitLabRepository;
use App\repository\mapper\GitlabProjectMapper;
use App\repository\mapper\ProjectMapper;
use App\repository\model\ProjectEntity;
use App\repository\ProjectRepository;
use App\util\MonitoringUtils;
use App\util\UtilsLog;
use DateMalformedStringException;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Logger;

class GitlabService
{
    private Logger $logger;
    private array $excludeProjects;

    public function __construct(
        private readonly GitLabClient      $client,
        private readonly MavenParser       $mavenParser,
        private readonly GradleParser      $gradleParser,
        private readonly GitLabRepository  $gitLabRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly AppConfig         $appConfig,
        LoggerFactory                      $loggerFactory
    )
    {
        $this->logger = $loggerFactory->get(__CLASS__);
        $this->excludeProjects = $this->appConfig->getParamConfig()->getExcludeProjects();
    }

    /**
     * Supprime toutes les données
     */
    public function purgeCache(): void
    {
        $this->logger->debug(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__) . "debut purgeCache");
        $this->gitLabRepository->purgeAll();
        $this->projectRepository->purgeAll();
    }

    /**
     * Scan les projets gitlab
     *
     * @param string|null $pathGroup Le chemin du groupe GitLab à scanner (ex: 'core/dev/pdv').
     * @return GitlabProject[]
     * @throws GuzzleException|TechnicalException
     */
    public function getProjects(?string $pathGroup): array
    {
        $gitLabProjectsEntities = $this->gitLabRepository->findAll();
        if (empty($gitLabProjectsEntities)) {
            $this->logger->info(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__) . "Le cache des projets est vide, récupération depuis l'API GitLab.");
            $gitLabProjectsData = $this->client->getAllProjects($pathGroup);
            $entities = [];
            foreach ($gitLabProjectsData as $gitlabProject) {
                $entities[] = GitlabProjectMapper::fromArray($gitlabProject);
            }

            $this->gitLabRepository->updateAll($entities);
            $gitLabProjectsEntities = $this->gitLabRepository->findAll() ?? [];
        }
        $gitlabProjects = [];
        foreach ($gitLabProjectsEntities as $gitLabProjectsEntity) {
            $gitlabProjects[] = GitlabProjectMapper::toModel($gitLabProjectsEntity);
        }
        return $gitlabProjects;
    }

    /**
     * @param string $projectCode
     * @return Project|null
     */
    public function getProjectByCode(string $projectCode): ?Project
    {
        $this->logger->debug(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__) . "debut");
        try {
            $projectEntity = $this->projectRepository->findByCode($projectCode);
            if ($projectEntity != null) {
                return ProjectMapper::fromEntity($projectEntity);
            }
            return null;
        } catch (TechnicalException $e) {
            $projectEntities = $this->initProjects();
            $this->projectRepository->updateAll($projectEntities);
            $projectEntity = array_find($projectEntities, static fn($result) => $result->getName() === $projectCode);
            if ($projectEntity) {
                return ProjectMapper::fromEntity($projectEntity);
            }
            return null;
        }
    }

    /**
     * Scan les projets gitlab
     *
     * @return Project[]|null
     * @throws GuzzleHttp|GuzzleException|TechnicalException
     */
    public function scan(): ?array
    {
        $this->logger->debug(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__) . 'debut scan');

        //$deployYamlContent = $this->client->getFile(648, 'deploy/conf/dev/deploy.yml', true, 'master');
        //var_dump($deployYamlContent);

        try {
            $projectEntities = $this->projectRepository->findAll();
        } catch (TechnicalException $e) {
            $projectEntities = $this->initProjects();
            $this->projectRepository->updateAll($projectEntities);
        }

        $projects = [];
        foreach ($projectEntities as $projectEntity) {
            $projects[] = ProjectMapper::fromEntity($projectEntity);
        }

        return $projects;
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
        return ['content' => $this->client->getFile($projectId, $file, true, 'master')];
    }

    /**
     * Construit un objet {@link Project} à partir d'un projet gitlab {@link GitlabProject}
     *
     * @param GitlabProject $gitLabProject Le projet gitlab à scanner
     * @return Project|null
     * @throws DateMalformedStringException
     */
    private function buildProject(GitlabProject $gitLabProject): ?Project
    {
        $pathInfo = $this->extractPathInfo($gitLabProject);
        $deploymentInfo = $this->getDeploymentInfo($gitLabProject);
        $mavenInfo = $this->scanPomXml($gitLabProject);

        $projectName = $gitLabProject->getName();

        $data = [
            'name' => $projectName,
            'serviceName' => $deploymentInfo['deployName'],
            'sf' => $pathInfo['sf'],
            'sfName' => $pathInfo['sfName'],
            'subsf' => $pathInfo['subsf'],
            'cloudGCP' => $deploymentInfo['cloudGCP'],
            'webUrl' => $gitLabProject->getWebUrl(),
            'archived' => $gitLabProject->isArchived(),
            'urlHealthCheck' => [],
            'urlLogs' => [],
            ...$mavenInfo ?? [],
        ];
        $project = ProjectMapper::projectFromArray($data);

        $urlsHealth = [];
        $urlsLogs = [];
        foreach (EnumEnvironment::cases() as $env) {
            $urlsHealth[$env->value] = MonitoringUtils::buildUrlHealthCheck($project, $env, $this->excludeProjects);
            $urlsLogs[$env->value] = MonitoringUtils::buildLogUrl($project, $env);
        }
        $project->setUrlHealthCheck($urlsHealth);
        $project->setUrlLogs($urlsLogs);

        return $project;
    }

    /**
     * Scan un fichier pom.xml d'un projet gitlab
     *
     * @param GitlabProject $gitLabProject Le projet gitlab à scanner
     * @return array|null
     */
    private function scanPomXml(GitlabProject $gitLabProject): ?array
    {
        $pom = $this->client->getFile($gitLabProject->getId(), 'pom.xml', true, $gitLabProject->getDefaultBranch());
        if (!$pom) {
            return null;
        }
        return $this->mavenParser->parse($pom);
    }

    private function extractPathInfo(GitlabProject $gitLabProject): array
    {
        $path = explode('/', $gitLabProject->getPathWithNamespace() ?? '');
        $namePath = explode('/', $gitLabProject->getNameWithNamespace() ?? '');
        return [
            'sf' => $path[2] ?? null,
            'sfName' => $namePath[2] ?? null,
            'subsf' => $path[3] ?? null,
        ];
    }

    private function getDeploymentInfo(GitlabProject $gitLabProject): array
    {
        $chartValuesFile = $this->client->getFile($gitLabProject->getId(), 'chart/values.yaml', true, $gitLabProject->getDefaultBranch());
        $cloudGCP = (bool)$chartValuesFile;

        $deployName = null;
        if (!$cloudGCP) {
            // Ex : 648
            $deployYamlContent = $this->client->getFile($gitLabProject->getId(), 'deploy/conf/dev/deploy.yml', true, $gitLabProject->getDefaultBranch());
            if ($deployYamlContent) {
                $deployName = MonitoringUtils::parseServiceName($deployYamlContent);
            }
        }

        return [
            'cloudGCP' => $cloudGCP,
            'deployName' => $deployName,
        ];
    }

    private function scanBuildGradle(GitlabProject $gitLabProject): ?array
    {
        $pathInfo = $this->extractPathInfo($gitLabProject);
        $gradle = $this->client->getFile($gitLabProject->getId(), 'build.gradle', true, $gitLabProject->getDefaultBranch());

        if ($gradle) {
            return [
                'name' => $gitLabProject->getName(),
                ...$pathInfo,
                ...$this->gradleParser->parse($gradle)
            ];
        }
        return null;
    }

    /**
     * @return ProjectEntity[]
     * @throws GuzzleException|TechnicalException|DateMalformedStringException
     */
    private function initProjects(): array
    {
        $this->logger->info("Le cache des projets Java est vide, scan en cours...");
        $gitlabProjects = $this->getProjects($this->appConfig->getParamConfig()->getGitlabPathGroupDefault());
        $this->logger->debug(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__) . ' nb gitlab projects:' . count($gitlabProjects));

        $projects = [];
        foreach ($gitlabProjects as $gitlabProject) {
            if (in_array($gitlabProject->getName(), $this->excludeProjects, true)) {
                continue;
            }

            $project = $this->buildProject($gitlabProject);
            if (!empty($project)) {
                $projects[] = $project;
            }
        }

        usort($projects, static function (Project $a, Project $b) {
            // 1. Trier par 'subsf' en premier
            $subsfComparison = $a->getSubsf() <=> $b->getSubsf();

            // Si les 'subsf' sont différents, on retourne le résultat de la comparaison
            if ($subsfComparison !== 0) {
                return $subsfComparison;
            }

            // 2. Si les 'subsf' sont identiques, on trie par 'name'
            return $a->getName() <=> $b->getName();
        });

        $projectEntities = [];
        foreach ($projects as $project) {
            $projectEntities[] = ProjectMapper::toEntity($project);
        }

        return $projectEntities;
    }
}
