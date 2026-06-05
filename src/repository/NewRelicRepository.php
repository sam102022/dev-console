<?php
declare(strict_types=1);

namespace App\repository;

use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\model\EnumEnvironment;
use App\repository\model\NewRelicEntity;
use App\service\FileService;

/**
 * Service de gestion du cache pour les URLs New Relic.
 */
class NewRelicRepository
{
    private const string CACHE_FILE = 'new_relic_urls.json';
    private FileService $fileService;

    public function __construct(LoggerFactory $loggerFactory)
    {
        $this->fileService = new FileService("../" . PATH_DATA, $loggerFactory);
    }

    /**
     * @param string $projectName Nom du projet
     * @param EnumEnvironment $env Environnement
     * @return NewRelicEntity|null
     * @throws TechnicalException
     */
    public function find(string $projectName, EnumEnvironment $env): ?NewRelicEntity
    {
        if (!$this->fileService->isFileExists(self::CACHE_FILE)) {
            return null;
        }
        $cache = $this->fileService->read(self::CACHE_FILE);
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
        if ($this->fileService->isFileExists(self::CACHE_FILE)) {
            $cache = $this->fileService->read(self::CACHE_FILE);
        }

        if (!isset($cache[$entity->getName()])) {
            $cache[$entity->getName()] = [];
        }

        $cache[$entity->getName()][$entity->getEnvironment()->value] = $entity->getUrl();
        $this->fileService->save($cache, self::CACHE_FILE);
    }
}
