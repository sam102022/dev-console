<?php
declare(strict_types=1);

namespace App\repository\mapper;

use App\model\GitlabProject;
use App\repository\model\GitlabProjectEntity;

class GitlabProjectMapper
{
    public static function fromArray(array $data): GitlabProjectEntity
    {
        $project = new GitlabProjectEntity();
        $project->setId($data['id']);
        $project->setDescription($data['description'] ?? null);
        $project->setName($data['name']);
        $project->setNameWithNamespace($data['name_with_namespace']);
        $project->setPath($data['path']);
        $project->setPathWithNamespace($data['path_with_namespace']);
        $project->setCreatedAt($data['created_at']);
        $project->setDefaultBranch($data['default_branch'] ?? 'main');
        $project->setWebUrl($data['web_url'] ?? '');

        return $project;
    }

    public static function fromEntity(GitlabProjectEntity $entity): GitlabProject
    {
        $project = new GitlabProject();
        $project->setId($entity->getId());
        $project->setDescription($entity->getDescription());
        $project->setName($entity->getName());
        $project->setNameWithNamespace($entity->getNameWithNamespace());
        $project->setPath($entity->getPath());
        $project->setPathWithNamespace($entity->getPathWithNamespace());
        $project->setCreatedAt($entity->getCreatedAt());
        $project->setDefaultBranch($entity->getDefaultBranch());
        $project->setWebUrl($entity->getWebUrl());

        return $project;
    }

    public static function toEntity(GitlabProject $project): GitlabProjectEntity
    {
        return GitlabProjectEntity::build(
            $project->getId(),
            $project->getDescription(),
            $project->getName(),
            $project->getNameWithNamespace(),
            $project->getPath(),
            $project->getPathWithNamespace(),
            $project->getDefaultBranch(),
            $project->getCreatedAt(),
            $project->getWebUrl()
        );
    }

    public static function toModel(GitlabProjectEntity $entity): GitlabProject
    {
        return GitlabProject::build(
            $entity->getId(),
            $entity->getDescription(),
            $entity->getName(),
            $entity->getNameWithNamespace(),
            $entity->getPath(),
            $entity->getPathWithNamespace(),
            $entity->getDefaultBranch(),
            $entity->getCreatedAt(),
            $entity->getWebUrl()
        );
    }

    public static function toArray(GitlabProjectEntity $gitlabProjectEntity): array
    {
        return [
            'id' => $gitlabProjectEntity->getId(),
            'description' => $gitlabProjectEntity->getDescription(),
            'name' => $gitlabProjectEntity->getName(),
            'name_with_namespace' => $gitlabProjectEntity->getNameWithNamespace(),
            'path' => $gitlabProjectEntity->getPath(),
            'path_with_namespace' => $gitlabProjectEntity->getPathWithNamespace(),
            'created_at' => $gitlabProjectEntity->getCreatedAt(),
            'default_branch' => $gitlabProjectEntity->getDefaultBranch(),
            'web_url' => $gitlabProjectEntity->getWebUrl(),
        ];
    }

}
