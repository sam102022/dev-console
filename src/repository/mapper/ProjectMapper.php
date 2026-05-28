<?php
declare(strict_types=1);

namespace App\repository\mapper;

use App\model\Project;
use App\repository\model\ProjectEntity;

class ProjectMapper
{
    public static function projectEntityFromArray(array $data): ProjectEntity
    {
        $projectEntity = new ProjectEntity();
        $projectEntity->setName($data['name']);
        $projectEntity->setServiceName($data['serviceName']);
        $projectEntity->setSf($data['sf'] ?? '');
        $projectEntity->setSfName($data['sfName'] ?? '');
        $projectEntity->setSubsf($data['subsf'] ?? '');
        $projectEntity->setCloudGCP($data['cloudGCP'] ?? false);
        $projectEntity->setSpringBootVersion($data['springBoot'] ?? null);
        $projectEntity->setJavaVersion($data['java'] ?? null);
        $projectEntity->setUrlHealthCheck($data['urlHealthCheck'] ?? []);
        $projectEntity->setUrlLogs($data['urlLogs'] ?? []);

        return $projectEntity;
    }

    public static function projectFromArray(array $data): Project
    {
        $project = new Project();
        $project->setName($data['name']);
        $project->setServiceName($data['serviceName']);
        $project->setSf($data['sf'] ?? '');
        $project->setSfName($data['sfName'] ?? '');
        $project->setSubsf($data['subsf'] ?? '');
        $project->setCloudGCP($data['cloudGCP'] ?? false);
        $project->setSpringBoot($data['springBoot'] ?? null);
        $project->setJava($data['java'] ?? null);
        $project->setUrlHealthCheck($data['urlHealthCheck'] ?? []);
        $project->setUrlLogs($data['urlLogs'] ?? []);

        return $project;
    }

    public static function fromEntity(ProjectEntity $entity): Project
    {
        $project = new Project();
        $project->setName($entity->getName());
        $project->setServiceName($entity->getServiceName());
        $project->setSf($entity->getSf());
        $project->setSfName($entity->getSfName());
        $project->setSubsf($entity->getSubsf());
        $project->setCloudGCP($entity->isCloudGCP());
        $project->setSpringBoot($entity->getSpringBootVersion());
        $project->setJava($entity->getJavaVersion());
        $project->setUrlHealthCheck($entity->getUrlHealthCheck());
        $project->setUrlLogs($entity->getUrlLogs());

        return $project;
    }

    public static function toEntity(Project $project): ProjectEntity
    {
        return ProjectEntity::build(
            $project->getName(),
            $project->getServiceName(),
            $project->getSf() ?? '',
            $project->getSfName() ?? '',
            $project->getSubsf() ?? '',
            $project->isCloudGCP(),
            $project->getSpringBoot(),
            $project->getJava(),
            $project->getUrlHealthCheck(),
            $project->getUrlLogs()
        );
    }

    public static function toArray(ProjectEntity $projectEntity): array
    {
        return [
            'name' => $projectEntity->getName(),
            'serviceName' => $projectEntity->getName(),
            'sf' => $projectEntity->getSf(),
            'sfName' => $projectEntity->getSfName(),
            'subsf' => $projectEntity->getSubsf(),
            'cloudGCP' => $projectEntity->isCloudGCP(),
            'springBoot' => $projectEntity->getSpringBootVersion(),
            'java' => $projectEntity->getJavaVersion(),
            'urlHealthCheck' => $projectEntity->getUrlHealthCheck(),
            'urlLogs' => $projectEntity->getUrlLogs(),
        ];
    }
}
