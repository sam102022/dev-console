<?php
declare(strict_types=1);

namespace App\tests\fixtures;

use App\repository\model\GitlabProjectEntity;

class GitlabProjectEntityFixtures
{
    public static function getGitlabProjectEntityA(): GitlabProjectEntity
    {
        return GitlabProjectEntity::build(1, 'New Project', 'project-a', 'a / b / c / d', 'a/b/c/d', 'a/b/c/d', 'main', '2023-01-01', 'http://url/a', false);
    }

    public static function getGitlabProjectEntityB(): GitlabProjectEntity
    {
        return GitlabProjectEntity::build(2, 'New Project', 'project-b', 'a / b / e / f', 'a/b/e/f', 'a/b/e/f', 'main', '2023-01-01', 'http://url/b', false);
    }

    public static function getGitlabProjectEntityExcluded(): GitlabProjectEntity
    {
        return GitlabProjectEntity::build(3, 'New Project', 'excluded-project', 'a / b / g / h', 'a/b/g/h', 'a/b/g/h', 'main', '2023-01-01', 'http://url/c', false);
    }

    public static function getGitlabProjectEntity(): GitlabProjectEntity
    {
        return GitlabProjectEntity::build(1, 'New Project', 'New Project', 'name-with-namespace', 'path', 'path-with-namespace', 'main', '2023-01-01', 'http://url', false);
    }

    public static function getGitlabProjectEntityFromCache(): GitlabProjectEntity
    {
        return GitlabProjectEntity::build(1, 'New Project', 'Project From Cache', 'name-with-namespace', 'path', 'path-with-namespace', 'main', '2023-01-01', 'http://url', false);
    }
}
