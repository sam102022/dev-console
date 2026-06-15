<?php
declare(strict_types=1);

namespace App\repository;

use App\config\AppConfig;
use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\repository\mapper\RundeckProjectMapper;
use App\repository\model\RundeckProjectEntity;
use App\service\RepositoryService;
use Monolog\Logger;

class RundeckRepository
{
    public const string FILE_RUNDECK_PROJECTS = 'rundeckObjects.json';

    private RepositoryService $repositoryService;
    private Logger $logger;

    public function __construct(AppConfig $appConfig, LoggerFactory $loggerFactory)
    {
        $this->repositoryService = new RepositoryService($appConfig->getPathData(), $loggerFactory);
        $this->logger = $loggerFactory->get(__CLASS__);
    }

    /**
     * @return RundeckProjectEntity[]|null
     * @throws TechnicalException
     */
    public function findAll(): ?array
    {
        if ($this->repositoryService->isFileExists(self::FILE_RUNDECK_PROJECTS)) {
            $this->logger->debug("Lecture du cache des projets Rundeck.");
            $data = $this->repositoryService->read(self::FILE_RUNDECK_PROJECTS);

            $entities = [];
            foreach ($data as $projectRundeck) {
                $entities[] = RundeckProjectMapper::fromArray($projectRundeck);
            }
            return $entities;
        }
        $this->logger->info("Cache des projets Rundeck non trouvé.");
        return null;
    }

    /**
     * @param RundeckProjectEntity[] $projects
     * @return void
     * @throws TechnicalException
     */
    public function updateAll(array $projects): void
    {
        $data = [];
        foreach ($projects as $projectRundeck) {
            $data[] = RundeckProjectMapper::toArray($projectRundeck);
        }

        $this->logger->info("Sauvegarde du cache des projets Rundeck.");
        $this->repositoryService->save($data, self::FILE_RUNDECK_PROJECTS);
    }

    public function purgeAll(): void
    {
        $this->logger->info("Purge de tous les caches Rundeck.");
        $this->repositoryService->delete(self::FILE_RUNDECK_PROJECTS);
    }
}