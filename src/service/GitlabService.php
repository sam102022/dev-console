<?php
declare(strict_types=1);

namespace App\service;

use App\client\GitLabClient;
use App\client\NewRelicClient;
use App\config\AppConfig;
use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\model\EnumEnvironment;
use App\model\GitlabProject;
use App\model\Project;
use App\parser\ChartParser;
use App\parser\ConfigYamlParser;
use App\parser\MavenParser;
use App\parser\PackageJsonParser;
use App\repository\GitLabRepository;
use App\repository\mapper\GitlabProjectMapper;
use App\repository\mapper\ProjectMapper;
use App\repository\model\ProjectEntity;
use App\repository\ProjectRepository;
use App\util\MonitoringUtils;
use App\util\UtilsLog;
use DateMalformedStringException;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Monolog\Logger;

class GitlabService
{
    private Logger $logger;
    private array $excludeProjects;

    public function __construct(
        private readonly GitLabClient      $client,
        private readonly MavenParser       $mavenParser,
        private readonly ChartParser       $chartParser,
        private readonly RundeckService    $rundeckService,
        private readonly GitLabRepository  $gitLabRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly NewRelicClient    $newRelicService,
        private readonly AppConfig         $appConfig,
        LoggerFactory                      $loggerFactory
    )
    {
        $this->logger = $loggerFactory->get(__CLASS__);
        $this->excludeProjects = $this->appConfig->getParamConfig()->getParamGitLab()->getExcludeProjects();
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
     * @throws TechnicalException
     */
    public function getProjects(?string $pathGroup): array
    {
        $gitlabProjects = [];
        try {
            $gitLabProjectsEntities = $this->gitLabRepository->findAll();
            if (empty($gitLabProjectsEntities)) {
                $this->logger->info(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__) . "Le cache des projets est vide, récupération depuis l'API GitLab.");
                $gitLabProjectsData = $this->client->getAllProjects($pathGroup);
                $entities = [];
                foreach ($gitLabProjectsData as $gitlabProject) {
                    $entity = GitlabProjectMapper::fromArray($gitlabProject);
                    $entities[] = $entity;
                }

                $this->gitLabRepository->updateAll($entities);
                $gitLabProjectsEntities = $this->gitLabRepository->findAll() ?? [];
            }
            foreach ($gitLabProjectsEntities as $gitLabProjectsEntity) {
                $gitlabProjects[] = GitlabProjectMapper::toModel($gitLabProjectsEntity);
            }
        } catch (GuzzleException $e) {
            throw new TechnicalException($e->getMessage());
        }
        return $gitlabProjects;
    }

    /**
     * @param string $projectCode
     * @return Project|null
     * @throws TechnicalException
     */
    public function getProjectByCode(string $projectCode): ?Project
    {
        $this->logger->debug(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__) . "debut");
        try {
            $projectEntity = $this->projectRepository->findByCode($projectCode);
            if ($projectEntity !== null) {
                return ProjectMapper::fromEntity($projectEntity);
            }
            return null;
        } catch (TechnicalException $e) {
            // cache vide
            try {
                $projectEntities = $this->initProjects();
                $this->projectRepository->updateAll($projectEntities);
                $projectEntity = array_find($projectEntities, static fn($result) => $result->getName() === $projectCode);
                if ($projectEntity) {
                    return ProjectMapper::fromEntity($projectEntity);
                }
                return null;
            } catch (TechnicalException $e) {
                $this->logger->error(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
                    . "Erreur technique : " . $e->getMessage());
                throw $e;
            }
        }
    }

    /**
     * Scan les projets gitlab
     *
     * @return Project[]|null
     * @throws TechnicalException
     */
    public function scan(): ?array
    {
        $this->logger->debug(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__) . 'debut scan');

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
     * @throws TechnicalException
     */
    private function buildProject(GitlabProject $gitLabProject): ?Project
    {
        $this->logger->info(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
            . "Traitement du projet " . $gitLabProject->getName() . "...");
        
        $pathInfo = $this->extractPathInfo($gitLabProject);
        $deploymentInfo = $this->getDeploymentInfo($gitLabProject);
        $mavenInfo = $this->scanPomXml($gitLabProject);
        $techno = $this->getTechno($gitLabProject);
        $subscriptionName = $this->getSubscriptionName($gitLabProject);

        $projectName = $gitLabProject->getName();

        $data = [
            'name' => $projectName,
            'serviceName' => $deploymentInfo['deployName'],
            'domain' => $pathInfo['domain'],
            'domainName' => $pathInfo['domainName'],
            'sf' => $pathInfo['sf'],
            'cloudGCP' => $deploymentInfo['cloudGCP'],
            'techno' => $techno,
            'subscriptionName' => $subscriptionName,
            'webUrl' => $gitLabProject->getWebUrl(),
            'archived' => $gitLabProject->isArchived(),
            'mdmWorkloadVersion' => $deploymentInfo['mdmWorkloadVersion'],
            'urlHealthCheck' => [],
            'urlActuatorInfo' => [],
            'urlLogs' => [],
            'urlFronts' => [],
            'urlPubsubs' => [],
            'urlsRundeck' => [],
            'urlsDeploymentGcp' => [],
            ...$mavenInfo ?? [],
        ];
        $project = ProjectMapper::projectFromArray($data);

        $urlsHealth = [];
        $urlsActuatorInfo = [];
        $urlsLogs = [];
        $urlsFronts = [];
        $urlsPubsubs = [];
        $urlsRundeck = [];
        $urlsDeploymentGcp = [];
        foreach (EnumEnvironment::cases() as $env) {
            if ($techno === 'java') {
                if (str_starts_with($projectName, 'api') || str_starts_with($projectName, 'flow')) {
                    $urlsHealth[$env->value] = MonitoringUtils::buildUrlActuatorHealth($project, $env, $this->excludeProjects);
                    $urlsActuatorInfo[$env->value] = MonitoringUtils::buildUrlActuatorInfo($project, $env, $this->excludeProjects);
                }
                if (str_starts_with($projectName, 'flow')) {
                    $urlsPubsubs[$env->value] = MonitoringUtils::buildPubSubUrl($project, $env);
                }
                if (str_starts_with($projectName, 'batch')) {
                    $rundeckProject = $this->rundeckService->findByProjectName($projectName, $env);
                    $urlsRundeck[$env->value] = MonitoringUtils::buildRundeckUrl($project, $env, $rundeckProject);
                }
            }
            if ($techno === 'java' || $techno === 'react' || $techno === 'nuxt') {
                $urlsLogs[$env->value] = MonitoringUtils::buildLogUrl($project, $env);
            }
            if ($techno === 'react' || $techno === 'nuxt') {
                $urlsFronts[$env->value] = MonitoringUtils::buildFrontReactUrl($project, $env, $this->appConfig->getParamConfig()->getTokenE107());
            }
            if ($techno === 'php' && str_starts_with($projectName, 'zend')) {
                $urlsFronts[$env->value] = MonitoringUtils::buildFrontPhpUrl($project, $env);
            }
            if ($project->isCloudGCP()) {
                $urlsDeploymentGcp[$env->value] = MonitoringUtils::buildDeploymentGcpUrl($project, $env);
            }
        }
        $project->setUrlHealthCheck($urlsHealth);
        $project->setUrlActuatorInfo($urlsActuatorInfo);
        $project->setUrlLogs($urlsLogs);
        $project->setUrlFronts($urlsFronts);
        $project->setUrlPubsubs($urlsPubsubs);
        $project->setUrlsRundeck($urlsRundeck);
        $project->setUrlsDeploymentGcp($urlsDeploymentGcp);

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
        $this->logger->info(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
            . "Récupération du fichier pom.xml du projet " . $gitLabProject->getName() . "...");

        $pom = $this->client->getFile($gitLabProject->getId(), 'pom.xml', true, $gitLabProject->getDefaultBranch());
        if (!$pom) {
            return null;
        }
        return $this->mavenParser->parsePomXml($pom);
    }

    private function extractPathInfo(GitlabProject $gitLabProject): array
    {
        $path = explode('/', $gitLabProject->getPathWithNamespace() ?? '');
        $namePath = explode('/', $gitLabProject->getNameWithNamespace() ?? '');
        return [
            'domain' => $path[2] ?? null,
            'domainName' => $namePath[2] ?? null,
            'sf' => $path[3] ?? null,
        ];
    }

    private function getDeploymentInfo(GitlabProject $gitLabProject): array
    {
        $this->logger->info(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
            . "Récupération des infos de déploiement du projet " . $gitLabProject->getName() . "...");

        $chartFile = $this->client->getFile($gitLabProject->getId(), 'chart/Chart.yaml', true, $gitLabProject->getDefaultBranch());
        $cloudGCP = (bool)$chartFile;
        $mdmWorkloadVersion = $chartFile ? $this->chartParser->parseChartYaml($chartFile) : null;

        $deployName = null;
        if (!$cloudGCP) {
            // Ex : id = 648
            // Récupère le nom du service à partir du fichier deploy.yml pour construire l'url kibana
            $deployYamlContent = $this->client->getFile($gitLabProject->getId(), 'deploy/conf/dev/deploy.yml', true, $gitLabProject->getDefaultBranch());
            if ($deployYamlContent) {
                try {
                    $deployName = ConfigYamlParser::parseServiceName($deployYamlContent);
                } catch (Exception $e) {
                    $this->logger->error(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__) . "Erreur lors du parsing du fichier deploy.yml pour le projet " . $gitLabProject->getName() . " : " . $e->getMessage());
                }
            }
        }

        return [
            'cloudGCP' => $cloudGCP,
            'deployName' => $deployName,
            'mdmWorkloadVersion' => $mdmWorkloadVersion,
        ];
    }

    private function getTechno(GitlabProject $gitLabProject): string
    {
        $this->logger->info(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
            . "Récupération du language du projet " . $gitLabProject->getName() . "...");

        $name = $gitLabProject->getName();

        if (str_starts_with($name, 'api')
            || str_starts_with($name, 'flow')
            || str_starts_with($name, 'batch')
            || str_starts_with($name, 'integ')
        ) {
            return 'java';
        }
        if (str_contains(strtolower($name), 'php')
            || str_starts_with(strtolower($name), 'zend')) {
            return 'php';
        }

        $packageFile = $this->client->getFile(
            $gitLabProject->getId(),
            'package.json',
            true,
            $gitLabProject->getDefaultBranch()
        );

        return $packageFile
            ? (PackageJsonParser::parsePackage($packageFile) ?? '')
            : '';
    }

    private function getSubscriptionName(GitlabProject $gitLabProject): ?string
    {
        $this->logger->info(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
            . "Récupération de la souscription du projet " . $gitLabProject->getName() . "...");

        $yamlContent = $this->client->getFile(
            $gitLabProject->getId(),
            'src/main/resources/application.yml',
            true,
            $gitLabProject->getDefaultBranch()
        );

        if (!$yamlContent) {
            $yamlContent = $this->client->getFile(
                $gitLabProject->getId(),
                'src/main/resources/application.yaml',
                true,
                $gitLabProject->getDefaultBranch()
            );
        }

        if ($yamlContent) {
            $subscriptionName = null;
            try {
                $subscriptionName = ConfigYamlParser::parseSubscriptionName($yamlContent);

                if ($subscriptionName && preg_match('/^\$\{(.+)}$/', $subscriptionName, $matches)) {
                    $variableName = $matches[1];
                    $valuesDevContent = $this->client->getFile(
                        $gitLabProject->getId(),
                        'chart/values-dev.yaml',
                        true,
                        $gitLabProject->getDefaultBranch()
                    );

                    if ($valuesDevContent) {
                        return ConfigYamlParser::parseVariableInValuesFile($valuesDevContent, $variableName);
                    }
                }
            } catch (Exception $e) {
                $this->logger->error(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__) . " Erreur lors du parsing du fichier deploy.yml pour le projet " . $gitLabProject->getName() . " : " . $e->getMessage());
            }
            return $subscriptionName;
        }

        return null;
    }

    /**
     * @return ProjectEntity[]
     * @throws TechnicalException
     */
    private function initProjects(): array
    {
        $this->logger->info("Le cache des projets Java est vide, scan en cours...");
        $gitlabProjects = $this->getProjects($this->appConfig->getParamConfig()->getParamGitLab()->getGitlabPathGroupDefault());
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
            // 1. Trier par 'sf' en premier
            $sfComparison = $a->getSf() <=> $b->getSf();

            // Si les 'sf' sont différents, on retourne le résultat de la comparaison
            if ($sfComparison !== 0) {
                return $sfComparison;
            }

            // 2. Si les 'sf' sont identiques, on trie par 'name'
            return $a->getName() <=> $b->getName();
        });

        $projectEntities = [];
        foreach ($projects as $project) {
            $projectEntities[] = ProjectMapper::toEntity($project);
        }

        return $projectEntities;
    }

    /**
     * @throws TechnicalException|JsonException
     */
    public function buildNewRelicUrl(Project $project, EnumEnvironment $env): ?string
    {
        $guid = $this->newRelicService->getEntityGuid($project->getName(), $env);
        if ($guid !== null) {
            return $this->newRelicService->generateEntityUrl($guid);
        }
        return null;
    }
}
