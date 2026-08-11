<?php
declare(strict_types=1);

namespace App\repository\mapper;

use App\model\RundeckProject;
use App\repository\model\RundeckProjectEntity;

class RundeckProjectMapper
{
    public static function fromArray(array $data): RundeckProjectEntity
    {
        $path = $data['path'] ?? null;
        $projectName = $path ? basename($path) : null;
        $name = $data['name'] ?? null;
        $domain = $data['domain'] ?? null;

        $entity = new RundeckProjectEntity();
        $entity->setSf($data['sf'] ?? '');
        $entity->setCategory($data['category'] ?? null);
        
        $token = $data['token'] ?? [];
        if (is_string($token)) {
            $token = [['dev' => '', 'prod' => $token]];
        }
        $entity->setToken($token);
        
        $entity->setPath($path);
        $entity->setProjectName($projectName);
        $entity->setName($name);
        $entity->setDomain($domain);
        return $entity;
    }

    public static function toArray(RundeckProjectEntity $entity): array
    {
        return [
            'sf' => $entity->getSf(),
            'category' => $entity->getCategory(),
            'token' => $entity->getToken(),
            'path' => $entity->getPath(),
            'projectName' => $entity->getProjectName(),
            'name' => $entity->getName(),
            'domain' => $entity->getDomain(),
        ];
    }

    public static function toEntity(RundeckProject $project): RundeckProjectEntity
    {
        $entity = new RundeckProjectEntity();
        $entity->setName($project->getName());
        $entity->setDomain($project->getDomain());
        $entity->setSf($project->getSf());
        $entity->setCategory($project->getCategory());
        $entity->setToken($project->getToken());
        $entity->setPath($project->getPath());
        $entity->setProjectName($project->getProjectName());
        return $entity;
    }

    public static function toModel(RundeckProjectEntity $entity): RundeckProject
    {
        $model = new RundeckProject();
        $model->setName($entity->getName());
        $model->setDomain($entity->getDomain());
        $model->setSf($entity->getSf());
        $model->setCategory($entity->getCategory());
        $model->setToken($entity->getToken());
        $model->setPath($entity->getPath());
        $model->setProjectName($entity->getProjectName());
        return $model;
    }
}
