<?php
declare(strict_types=1);

namespace App\tests\model;

use App\model\ParamGitLab;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('parseDataProvider')]
    final public function testParse(array $params, array $expected): void
    {
        $paramGitLab = ParamGitLab::parse($params);

        $this->assertEquals($expected['gitlab_url'], $paramGitLab->getGitlabUrl());
        $this->assertEquals($expected['gitlab_token'], $paramGitLab->getGitlabToken());
        $this->assertEquals($expected['gitlab_business_contract_project_id'], $paramGitLab->getGitlabBusinessContractProjectId());
        $this->assertEquals($expected['gitlab_path_group_default'], $paramGitLab->getGitlabPathGroupDefault());
        $this->assertEquals($expected['projects_in_gke'], $paramGitLab->getProjectsInGke());
        $this->assertEquals($expected['exclude_projects'], $paramGitLab->getExcludeProjects());
    }

    public static function parseDataProvider(): array
    {
        return [
            'all_params' => [
                'params' => [
                    'gitlab_url' => 'https://gitlab.test',
                    'gitlab_token' => 'token_test',
                    'gitlab_business_contract_project_id' => 999,
                    'gitlab_path_group_default' => 'test/group',
                    'projects_in_gke' => 'proj1, proj2 ',
                    'exclude_projects' => ' ex1 , ex2',
                ],
                'expected' => [
                    'gitlab_url' => 'https://gitlab.test',
                    'gitlab_token' => 'token_test',
                    'gitlab_business_contract_project_id' => 999,
                    'gitlab_path_group_default' => 'test/group',
                    'projects_in_gke' => ['proj1', 'proj2'],
                    'exclude_projects' => ['ex1', 'ex2'],
                ],
            ],
        ];
    }

    #[DataProvider('parseThrowsExceptionDataProvider')]
    final public function testParseThrowsException(array $params): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Certains paramètres gitlab requis sont manquants.');
        ParamGitLab::parse($params);
    }

    public static function parseThrowsExceptionDataProvider(): array
    {
        return [
            'partial_params' => [
                'params' => [
                    'gitlab_url' => 'https://gitlab.test',
                ],
            ],
            'empty_params' => [
                'params' => [],
            ],
        ];
    }
}