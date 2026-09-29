<?php
declare(strict_types=1);

namespace App\tests\model;

use App\model\GitlabProject;
use App\model\Project;
use PHPUnit\Framework\TestCase;

class ProjectTest extends TestCase
{
    public function testDefaultWebUrlIsEmptyStringOnProject(): void
    {
        $project = new Project();
        $this->assertSame('', $project->getWebUrl());
    }

    public function testSetAndGetWebUrlOnProject(): void
    {
        $project = new Project();
        $project->setWebUrl('https://gitlab.example.com/group/project');
        $this->assertSame('https://gitlab.example.com/group/project', $project->getWebUrl());
    }

    public function testDefaultWebUrlIsEmptyStringOnGitlabProject(): void
    {
        $gitlabProject = new GitlabProject();
        $this->assertSame('', $gitlabProject->getWebUrl());
    }

    public function testSetAndGetWebUrlOnGitlabProject(): void
    {
        $gitlabProject = new GitlabProject();
        $gitlabProject->setWebUrl('https://gitlab.example.com/group/project');
        $this->assertSame('https://gitlab.example.com/group/project', $gitlabProject->getWebUrl());
    }
}
