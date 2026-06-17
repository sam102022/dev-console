<?php
declare(strict_types=1);

namespace App\repository;

use App\config\AppConfig;
use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\repository\mapper\GitlabProjectMapper;
use App\repository\model\GitlabProjectEntity;
use App\service\RepositoryService;
use Monolog\Logger;

class GitLabRepository
{
    public const string FILE_GITLAB_PROJECTS = 'gitlabProjects.json';

    private RepositoryService $repositoryService;
    private Logger $logger;

    public function __construct(AppConfig $appConfig, LoggerFactory $loggerFactory)
    {
        $this->repositoryService = new RepositoryService($appConfig->getPathData(), $loggerFactory);
        $this->logger = $loggerFactory->get(__CLASS__);
    }

    /**
     * @return GitlabProjectEntity[]|null
     * @throws TechnicalException
     */
    public function findAll(): ?array
    {
        if ($this->repositoryService->isFileExists(self::FILE_GITLAB_PROJECTS)) {
            $this->logger->debug("Lecture du cache des projets GitLab.");
            $data = $this->repositoryService->read(self::FILE_GITLAB_PROJECTS);

            $entities = [];
            foreach ($data as $projectGitLab) {
                $entities[] = GitlabProjectMapper::fromArray($projectGitLab);
            }
            return $entities;
        }
        $this->logger->info("Cache des projets GitLab non trouvé.");
        return null;
    }

    /**
     * @param GitlabProjectEntity[] $projects
     * @return void
     * @throws TechnicalException
     */
    public function updateAll(array $projects): void
    {
        $data = [];
        foreach ($projects as $projectGitLab) {
            $data[] = GitlabProjectMapper::toArray($projectGitLab);
        }

        $this->logger->info("Sauvegarde du cache des projets GitLab.");
        $this->repositoryService->save($data, self::FILE_GITLAB_PROJECTS);
    }

    public function purgeAll(): void
    {
        $this->logger->info("Purge de tous les caches GitLab.");
        $this->repositoryService->delete(self::FILE_GITLAB_PROJECTS);
    }
}