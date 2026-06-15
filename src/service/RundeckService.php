<?php
declare(strict_types=1);

namespace App\service;

use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\model\EnumEnvironment;
use App\model\RundeckProject;
use App\repository\mapper\RundeckProjectMapper;
use App\repository\RundeckRepository;
use App\util\UtilsLog;
use Monolog\Logger;

class RundeckService
{
    private Logger $logger;

    public function __construct(
        private readonly RundeckRepository $rundeckRepository,
        LoggerFactory                      $loggerFactory
    ) {
        $this->logger = $loggerFactory->get(__CLASS__);
    }

    /**
     * @return RundeckProject[]
     * @throws TechnicalException
     */
    public function findAll(): array
    {
        $this->logger->debug(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__) . "debut findAll");
        $entities = $this->rundeckRepository->findAll();
        $models = [];
        
        if ($entities !== null) {
            foreach ($entities as $entity) {
                $models[] = RundeckProjectMapper::toModel($entity);
            }
        }
        
        return $models;
    }

    /**
     * @param string $projectName Nom du projet
     * @param EnumEnvironment $env Environnement
     * @return RundeckProject|null
     * @throws TechnicalException
     */
    public function findByProjectName(string $projectName, EnumEnvironment $env): ?RundeckProject
    {
        $this->logger->debug(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__) . "debut findByProjectName avec projectName : " . $projectName);
        $projects = $this->findAll();
        
        foreach ($projects as $project) {
            // && $project->getEnv() === $env->value
            // Les tokens sont les mêmes sur chaque environnement
            if ($project->getProjectName() === $projectName) {
                return $project;
            }
        }
        
        return null;
    }
}
