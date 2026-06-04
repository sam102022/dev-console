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
        $projectEntity->setTechno($data['techno'] ?? null);
        $projectEntity->setSubscriptionName($data['subscriptionName'] ?? null);
        $projectEntity->setMdmWorkloadVersion($data['mdmWorkloadVersion'] ?? null);
        $projectEntity->setWebUrl($data['webUrl'] ?? '');
        $projectEntity->setArchived($data['archived'] ?? false);
        $projectEntity->setUrlHealthCheck($data['urlHealthCheck'] ?? []);
        $projectEntity->setUrlLogs($data['urlLogs'] ?? []);
        $projectEntity->setUrlFronts($data['urlFronts'] ?? []);
        $projectEntity->setUrlPubsubs($data['urlPubsubs'] ?? []);
        $projectEntity->setUrlsRundeck($data['urlsRundeck'] ?? []);

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
        $project->setTechno($data['techno'] ?? null);
        $project->setSubscriptionName($data['subscriptionName'] ?? null);
        $project->setMdmWorkloadVersion($data['mdmWorkloadVersion'] ?? null);
        $project->setWebUrl($data['webUrl'] ?? '');
        $project->setArchived($data['archived'] ?? false);
        $project->setUrlHealthCheck($data['urlHealthCheck'] ?? []);
        $project->setUrlLogs($data['urlLogs'] ?? []);
        $project->setUrlFronts($data['urlFronts'] ?? []);
        $project->setUrlPubsubs($data['urlPubsubs'] ?? []);
        $project->setUrlsRundeck($data['urlsRundeck'] ?? []);

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
        $project->setTechno($entity->getTechno());
        $project->setSubscriptionName($entity->getSubscriptionName());
        $project->setMdmWorkloadVersion($entity->getMdmWorkloadVersion());
        $project->setWebUrl($entity->getWebUrl());
        $project->setArchived($entity->isArchived());
        $project->setUrlHealthCheck($entity->getUrlHealthCheck());
        $project->setUrlLogs($entity->getUrlLogs());
        $project->setUrlFronts($entity->getUrlFronts());
        $project->setUrlPubsubs($entity->getUrlPubsubs());
        $project->setUrlsRundeck($entity->getUrlsRundeck());

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
            $project->getTechno(),
            $project->getSubscriptionName(),
            $project->getWebUrl(),
            $project->isArchived(),
            $project->getUrlHealthCheck(),
            $project->getUrlLogs(),
            $project->getUrlFronts(),
            $project->getUrlPubsubs(),
            $project->getMdmWorkloadVersion(),
            $project->getUrlsRundeck()
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
            'techno' => $projectEntity->getTechno(),
            'subscriptionName' => $projectEntity->getSubscriptionName(),
            'mdmWorkloadVersion' => $projectEntity->getMdmWorkloadVersion(),
            'webUrl' => $projectEntity->getWebUrl(),
            'archived' => $projectEntity->isArchived(),
            'urlHealthCheck' => $projectEntity->getUrlHealthCheck(),
            'urlLogs' => $projectEntity->getUrlLogs(),
            'urlFronts' => $projectEntity->getUrlFronts(),
            'urlPubsubs' => $projectEntity->getUrlPubsubs(),
            'urlsRundeck' => $projectEntity->getUrlsRundeck(),
        ];
    }
}
