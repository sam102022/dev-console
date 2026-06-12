<?php
declare(strict_types=1);

namespace App\repository;

use App\config\AppConfig;
use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\model\EnumEnvironment;
use App\repository\model\NewRelicEntity;
use App\service\RepositoryService;

/**
 * Service de gestion du cache pour les URLs New Relic.
 */
class NewRelicRepository
{
    private const string CACHE_FILE = 'new_relic_urls.json';
    private RepositoryService $repositoryService;

    public function __construct(AppConfig $appConfig, LoggerFactory $loggerFactory)
    {
        $this->repositoryService = new RepositoryService($appConfig->getPathData(), $loggerFactory);
    }

    /**
     * @param string $projectName Nom du projet
     * @param EnumEnvironment $env Environnement
     * @return NewRelicEntity|null
     * @throws TechnicalException
     */
    public function find(string $projectName, EnumEnvironment $env): ?NewRelicEntity
    {
        if (!$this->repositoryService->isFileExists(self::CACHE_FILE)) {
            return null;
        }
        $cache = $this->repositoryService->read(self::CACHE_FILE);
        $url = $cache[$projectName][$env->value] ?? null;
        if ($url) {
            return NewRelicEntity::build($projectName, $env, $url);
        }
        return null;
    }

    /**
     * @param NewRelicEntity $entity
     * @throws TechnicalException
     */
    public function save(NewRelicEntity $entity): void
    {
        $cache = [];
        if ($this->repositoryService->isFileExists(self::CACHE_FILE)) {
            $cache = $this->repositoryService->read(self::CACHE_FILE);
        }

        if (!isset($cache[$entity->getName()])) {
            $cache[$entity->getName()] = [];
        }

        $cache[$entity->getName()][$entity->getEnvironment()->value] = $entity->getUrl();
        $this->repositoryService->save($cache, self::CACHE_FILE);
    }
}