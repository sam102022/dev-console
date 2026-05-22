<?php
declare(strict_types=1);

namespace App\tests\model;

use App\model\ParamConfig;
use PHPUnit\Framework\TestCase;

class ParamConfigTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $paramConfig = new ParamConfig();

        $paramConfig->setDatabaseHost('localhost');
        $this->assertEquals('localhost', $paramConfig->getDatabaseHost());

        $paramConfig->setDatabasePort(3306);
        $this->assertEquals(3306, $paramConfig->getDatabasePort());

        $paramConfig->setDatabaseName('iptv');
        $this->assertEquals('iptv', $paramConfig->getDatabaseName());

        $paramConfig->setDatabaseUser('root');
        $this->assertEquals('root', $paramConfig->getDatabaseUser());

        $paramConfig->setDatabasePassword('');
        $this->assertEquals('', $paramConfig->getDatabasePassword());

        $paramConfig->setUrlServer('http://server.com');
        $this->assertEquals('http://server.com', $paramConfig->getUrlServer());

        $paramConfig->setIpLocal('127.0.0.1');
        $this->assertEquals('127.0.0.1', $paramConfig->getIpLocal());

        $paramConfig->setPortLocal(8000);
        $this->assertEquals(8000, $paramConfig->getPortLocal());
        
        $paramConfig->setGitlabUrl('https://gitlab.example.com');
        $this->assertEquals('https://gitlab.example.com', $paramConfig->getGitlabUrl());

        $paramConfig->setGitlabToken('my-token');
        $this->assertEquals('my-token', $paramConfig->getGitlabToken());

        $paramConfig->setGitlabBusinessContractProjectId(123);
        $this->assertEquals(123, $paramConfig->getGitlabBusinessContractProjectId());

        $paramConfig->setGitlabPathGroupDefault('group/project');
        $this->assertEquals('group/project', $paramConfig->getGitlabPathGroupDefault());

        $paramConfig->setPostmanApiKey('postman-key');
        $this->assertEquals('postman-key', $paramConfig->getPostmanApiKey());

        $paramConfig->setPostmanApiUrl('https://api.postman.com');
        $this->assertEquals('https://api.postman.com', $paramConfig->getPostmanApiUrl());

        $paramConfig->setProjectsInGke(['project1', 'project2']);
        $this->assertEquals(['project1', 'project2'], $paramConfig->getProjectsInGke());

        $paramConfig->setExcludeProjects(['exclude1']);
        $this->assertEquals(['exclude1'], $paramConfig->getExcludeProjects());
    }

    public function testParse(): void
    {
        $params = [
            'database_host' => 'db_host',
            'database_port' => 3307,
            'database_name' => 'db_name',
            'database_user' => 'db_user',
            'database_password' => 'db_pass',
            'base_url' => 'http://base.url',
            'host' => 'local_host',
            'port' => 8080,
            'gitlab_url' => 'https://gitlab.test',
            'gitlab_token' => 'token_test',
            'gitlab_business_contract_project_id' => 999,
            'gitlab_path_group_default' => 'test/group',
            'postman_api_key' => 'pm_key',
            'postman_api_url' => 'https://pm.test',
            'projects_in_gke' => 'proj1, proj2 ',
            'exclude_projects' => ' ex1 , ex2',
        ];

        $paramConfig = ParamConfig::parse($params);

        $this->assertEquals('db_host', $paramConfig->getDatabaseHost());
        $this->assertEquals(3307, $paramConfig->getDatabasePort());
        $this->assertEquals('db_name', $paramConfig->getDatabaseName());
        $this->assertEquals('db_user', $paramConfig->getDatabaseUser());
        $this->assertEquals('db_pass', $paramConfig->getDatabasePassword());
        $this->assertEquals('http://base.url', $paramConfig->getUrlServer());
        $this->assertEquals('local_host', $paramConfig->getIpLocal());
        $this->assertEquals(8080, $paramConfig->getPortLocal());
        $this->assertEquals('https://gitlab.test', $paramConfig->getGitlabUrl());
        $this->assertEquals('token_test', $paramConfig->getGitlabToken());
        $this->assertEquals(999, $paramConfig->getGitlabBusinessContractProjectId());
        $this->assertEquals('test/group', $paramConfig->getGitlabPathGroupDefault());
        $this->assertEquals('pm_key', $paramConfig->getPostmanApiKey());
        $this->assertEquals('https://pm.test', $paramConfig->getPostmanApiUrl());
        $this->assertEquals(['proj1', 'proj2'], $paramConfig->getProjectsInGke());
        $this->assertEquals(['ex1', 'ex2'], $paramConfig->getExcludeProjects());
    }

    public function testParseWithDefaults(): void
    {
        $paramConfig = ParamConfig::parse([]);

        $this->assertEquals('localhost', $paramConfig->getDatabaseHost());
        $this->assertEquals(3306, $paramConfig->getDatabasePort());
        $this->assertEquals('', $paramConfig->getDatabaseName());
        $this->assertEquals('', $paramConfig->getDatabaseUser());
        $this->assertEquals('', $paramConfig->getDatabasePassword());
        $this->assertEquals('', $paramConfig->getUrlServer());
        $this->assertEquals('localhost', $paramConfig->getIpLocal());
        $this->assertEquals(80, $paramConfig->getPortLocal());
        $this->assertEquals('', $paramConfig->getGitlabUrl());
        $this->assertEquals('', $paramConfig->getGitlabToken());
        $this->assertEquals(0, $paramConfig->getGitlabBusinessContractProjectId());
        $this->assertEquals('', $paramConfig->getGitlabPathGroupDefault());
        $this->assertEquals('', $paramConfig->getPostmanApiKey());
        $this->assertEquals('', $paramConfig->getPostmanApiUrl());
        $this->assertEquals([], $paramConfig->getProjectsInGke());
        $this->assertEquals([], $paramConfig->getExcludeProjects());
    }

    public function testJsonSerialize(): void
    {
        $paramConfig = new ParamConfig();
        $paramConfig->setDatabaseHost('localhost');
        $paramConfig->setGitlabBusinessContractProjectId(42);

        $json = json_encode($paramConfig);
        $decoded = json_decode($json, true);

        $this->assertArrayHasKey('databaseHost', $decoded);
        $this->assertEquals('localhost', $decoded['databaseHost']);
        $this->assertArrayHasKey('gitlabBusinessContractProjectId', $decoded);
        $this->assertEquals(42, $decoded['gitlabBusinessContractProjectId']);
    }
}