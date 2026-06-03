<?php
declare(strict_types=1);

namespace App\tests\fixtures;

use App\repository\model\GitlabProjectEntity;

class GitlabProjectEntityFixtures
{
    public static function getGitlabProjectEntityA(): GitlabProjectEntity
    {
        return GitlabProjectEntity::build(1, 'New Project', 'project-a',
            'name-with-namespace', 'path', 'path-with-namespace',
            'main', '2023-01-01', 'http://url/a', false, null);
    }

    public static function getGitlabProjectEntityB(): GitlabProjectEntity
    {
        return GitlabProjectEntity::build(2, 'New Project', 'project-b',
            'a / b / e / f', 'a/b/e/f', 'a/b/e/f',
            'main', '2023-01-01', 'http://url/b', false,
            null);
    }

    public static function getGitlabProjectEntityExcluded(): GitlabProjectEntity
    {
        return GitlabProjectEntity::build(3, 'New Project', 'excluded-project',
            'a / b / g / h', 'a/b/g/h', 'a/b/g/h',
            'main', '2023-01-01', 'http://url/c', false, null);
    }

    public static function getGitlabProjectEntity(): GitlabProjectEntity
    {
        return GitlabProjectEntity::build(1, 'New Project', 'New Project',
            'name-with-namespace', 'path',
            'path-with-namespace', 'main', '2023-01-01',
            'http://url', false, null);
    }

    public static function getGitlabProjectData(): array
    {
        return ['id' => 1, 'description' => 'New Project', 'name' => 'New Project',
            'name_with_namespace' => 'name-with-namespace', 'path' => 'path',
            'path_with_namespace' => 'path-with-namespace', 'created_at' => '2023-01-01',
            'default_branch' => 'main', 'web_url' => 'http://url', 'archived' => false,
            'mdm_workload_version' => null];
    }

    public static function getGitlabProjectEntityFromCache(): GitlabProjectEntity
    {
        return GitlabProjectEntity::build(1, 'New Project', 'Project From Cache',
            'name-with-namespace', 'path', 'path-with-namespace',
            'main', '2023-01-01', 'http://url', false,
            null);
    }
}
