<?php
declare(strict_types=1);

namespace App\service;

use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\model\EnumEnvironment;
use App\model\NewRelic;
use App\repository\mapper\NewRelicMapper;
use App\repository\NewRelicRepository;
use Monolog\Logger;

/**
 * Service de gestion du cache pour les URLs New Relic.
 */
class NewRelicService
{
    private Logger $logger;

    public function __construct(private readonly NewRelicRepository $newRelicRepository,
                                LoggerFactory $loggerFactory)
    {
        $this->logger = $loggerFactory->get(__CLASS__);
    }

    /**
     * @param string $projectName Nom du projet
     * @param EnumEnvironment $env Environnement
     * @return NewRelic|null
     * @throws TechnicalException
     */
    public function find(string $projectName, EnumEnvironment $env): ?NewRelic
    {
        $entity = $this->newRelicRepository->find($projectName, $env);
        if (!$entity) {
            return null;
        }

        return NewRelicMapper::toModel($entity);
    }

    /**
     * @param NewRelic $model
     * @throws TechnicalException
     */
    public function save(NewRelic $model): void
    {
        $entity = NewRelicMapper::toEntity($model);
        $this->newRelicRepository->save($entity);
    }
}
