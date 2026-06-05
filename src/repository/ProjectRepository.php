<?php
declare(strict_types=1);

namespace App\repository;

use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\repository\mapper\ProjectMapper;
use App\repository\model\ProjectEntity;
use App\service\FileService;
use App\util\UtilsLog;
use Monolog\Logger;

class ProjectRepository
{
    public const string FILE_JAVA_PROJECTS = 'javaProjects.json';

    private FileService $fileService;
    private Logger $logger;

    public function __construct(LoggerFactory $loggerFactory)
    {
        $this->fileService = new FileService("../" . PATH_DATA, $loggerFactory);
        $this->logger = $loggerFactory->get(__CLASS__);
    }

    /**
     * @return ProjectEntity[]
     * @throws TechnicalException
     */
    public function findAll(): array
    {
        if ($this->fileService->isFileExists(self::FILE_JAVA_PROJECTS)) {
            $this->logger->debug("Lecture du cache des projets Java.");
            $projectsData = $this->fileService->read(self::FILE_JAVA_PROJECTS);

            $projects = [];
            foreach ($projectsData as $data) {
                $projects[] = ProjectMapper::projectEntityFromArray($data);
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
        $projectEntities = $this->findAll();

        return array_find($projectEntities, static fn($projectEntity) => $projectEntity->getName() === $projectCode);
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
        $this->fileService->save($data, self::FILE_JAVA_PROJECTS);
    }

    public function purgeAll(): void
    {
        $this->logger->info("Purge du cache projects.");
        $this->fileService->delete(self::FILE_JAVA_PROJECTS);
    }
}
