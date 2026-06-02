<?php
declare(strict_types=1);

namespace App\tests\fixtures;

use App\model\GitlabProject;

class GitlabProjectFixtures
{
    public static function getGitlabProject(): GitlabProject
    {
        return GitlabProject::build(1, 'New Project', 'New Project', 'name-with-namespace', 'path', 'path-with-namespace', 'main', '2023-01-01', 'http://url', false);
    }

    public static function getGitlabProjectFromCache(): GitlabProject
    {
        return GitlabProject::build(1, 'New Project', 'Project 1 from cache', 'name-with-namespace', 'path', 'path-with-namespace', 'main', '2023-01-01', 'http://url', false);
    }
}
