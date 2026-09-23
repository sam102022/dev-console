<?php
declare(strict_types=1);

namespace App\repository;

use App\config\AppConfig;
use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\repository\mapper\ProjectMapper;
use App\repository\model\ProjectEntity;
use App\service\RepositoryService;
use App\util\UtilsLog;
use Monolog\Logger;

class ProjectRepository
{
    public const string FILE_JAVA_PROJECTS = 'javaProjects.json';

    private RepositoryService $repositoryService;
    private Logger $logger;

    public function __construct(AppConfig $appConfig, LoggerFactory $loggerFactory)
    {
        $this->repositoryService = new RepositoryService($appConfig->getPathData(), $loggerFactory);
        $this->logger = $loggerFactory->get(__CLASS__);
    }

    /**
     * @return ProjectEntity[]
     * @throws TechnicalException
     */
    public function findAll(): array
    {
        if ($this->repositoryService->isFileExists(self::FILE_JAVA_PROJECTS)) {
            $this->logger->debug("Lecture du cache des projets Java.");
            $projectsData = $this->repositoryService->read(self::FILE_JAVA_PROJECTS);
            $tagsByProject = $this->repositoryService->getTagsByProject();

            $projects = [];
            foreach ($projectsData as $data) {
                $entity = ProjectMapper::projectEntityFromArray($data);
                if (isset($tagsByProject[$entity->getName()])) {
                    $entity->setTags($tagsByProject[$entity->getName()]);
                }
                $projects[] = $entity;
            }
            return $projects;
        }
        $this->logger->info("Cache des projets Java non trouvé.");
        throw new TechnicalException("Le cache des projets Java est vide.", 404, null);
    }

    /**
     * @param string $projectCode
     * @return ProjectEntity|null
     * @throws TechnicalException
     */
    public function findByCode(string $projectCode): ?ProjectEntity
    {
        $this->logger->debug(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__) . "debut");
        if (!$this->repositoryService->isFileExists(self::FILE_JAVA_PROJECTS)) {
            $this->logger->info("Cache des projets Java non trouvé.");
            throw new TechnicalException("Le cache des projets Java est vide.", 404, null);
        }

        $projectData = $this->repositoryService->findProjectByName($projectCode);
        if ($projectData) {
            $entity = ProjectMapper::projectEntityFromArray($projectData);
            $tags = $this->repositoryService->getTagsForProject($projectCode);
            if (!empty($tags)) {
                $entity->setTags($tags);
            }
            return $entity;
        }
        return null;
    }

    /**
     * @return array<string, array<string>>
     */
    public function getTagsByProject(): array
    {
        return $this->repositoryService->getTagsByProject();
    }

    /**
     * @param string $projectName
     * @return array<string>
     */
    public function getTagsForProject(string $projectName): array
    {
        return $this->repositoryService->getTagsForProject($projectName);
    }

    /**
     * @param ProjectEntity[] $projectEntities
     * @throws TechnicalException
     */
    public function updateAll(array $projectEntities): void
    {
        $this->logger->info("Sauvegarde du cache des projets Java.");

        $data = [];
        foreach ($projectEntities as $projectEntity) {
            $data[] = ProjectMapper::toArray($projectEntity);
        }
        $this->repositoryService->save($data, self::FILE_JAVA_PROJECTS);
    }

    public function purgeAll(): void
    {
        $this->logger->info("Purge du cache projects.");
        $this->repositoryService->delete(self::FILE_JAVA_PROJECTS);
    }
}