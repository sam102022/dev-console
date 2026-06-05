<?php
declare(strict_types=1);

namespace App\tests\model;

use App\model\ParamGitLab;
use PHPUnit\Framework\TestCase;

class ParamGitLabTest extends TestCase
{
    final public function testGettersAndSetters(): void
    {
        $paramGitLab = new ParamGitLab();

        $paramGitLab->setGitlabUrl('https://gitlab.example.com');
        $this->assertEquals('https://gitlab.example.com', $paramGitLab->getGitlabUrl());

        $paramGitLab->setGitlabToken('my-token');
        $this->assertEquals('my-token', $paramGitLab->getGitlabToken());

        $paramGitLab->setGitlabBusinessContractProjectId(123);
        $this->assertEquals(123, $paramGitLab->getGitlabBusinessContractProjectId());

        $paramGitLab->setGitlabPathGroupDefault('group/project');
        $this->assertEquals('group/project', $paramGitLab->getGitlabPathGroupDefault());

        $paramGitLab->setProjectsInGke(['project1', 'project2']);
        $this->assertEquals(['project1', 'project2'], $paramGitLab->getProjectsInGke());

        $paramGitLab->setExcludeProjects(['exclude1']);
        $this->assertEquals(['exclude1'], $paramGitLab->getExcludeProjects());
    }
}