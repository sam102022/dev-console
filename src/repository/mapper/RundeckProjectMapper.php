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
        $nom = $data['nom'] ?? null;

        $entity = new RundeckProjectEntity();
        $entity->setEnv($data['env'] ?? '');
        $entity->setSf($data['sf'] ?? '');
        $entity->setCategory($data['category'] ?? null);
        $entity->setToken($data['token'] ?? '');
        $entity->setPath($path);
        $entity->setProjectName($projectName);
        $entity->setNom($nom);
        return $entity;
    }

    public static function toArray(RundeckProjectEntity $entity): array
    {
        return [
            'env' => $entity->getEnv(),
            'sf' => $entity->getSf(),
            'category' => $entity->getCategory(),
            'token' => $entity->getToken(),
            'path' => $entity->getPath(),
            'projectName' => $entity->getProjectName(),
            'nom' => $entity->getNom(),
        ];
    }

    public static function fromEntity(RundeckProjectEntity $entity): RundeckProject
    {
        $model = new RundeckProject();
        $model->setNom($entity->getNom());
        $model->setEnv($entity->getEnv());
        $model->setSf($entity->getSf());
        $model->setCategory($entity->getCategory());
        $model->setToken($entity->getToken());
        $model->setPath($entity->getPath());
        $model->setProjectName($entity->getProjectName());
        return $model;
    }

    public static function toEntity(RundeckProject $project): RundeckProjectEntity
    {
        $entity = new RundeckProjectEntity();
        $entity->setNom($project->getNom());
        $entity->setEnv($project->getEnv());
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
        $model->setNom($entity->getNom());
        $model->setEnv($entity->getEnv());
        $model->setSf($entity->getSf());
        $model->setCategory($entity->getCategory());
        $model->setToken($entity->getToken());
        $model->setPath($entity->getPath());
        $model->setProjectName($entity->getProjectName());
        return $model;
    }
}
