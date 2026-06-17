<?php
declare(strict_types=1);

namespace App\tests\model;

use App\model\ParamConfig;
use App\model\ParamGitLab;
use App\model\ParamPostman;
use App\model\ParamNewRelic;
use App\model\ParamRepository;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ParamConfigTest extends TestCase
{
    final public function testGettersAndSetters(): void
    {
        $paramConfig = new ParamConfig();
        $paramRepository = new ParamRepository();
        $paramGitLab = new ParamGitLab();
        $paramPostman = new ParamPostman();

        $paramRepository->setDatabaseHost('localhost');
        $paramConfig->setParamRepository($paramRepository);
        $this->assertEquals($paramRepository, $paramConfig->getParamRepository());

        $paramConfig->setUrlServer('http://server.com');
        $this->assertEquals('http://server.com', $paramConfig->getUrlServer());

        $paramConfig->setIpLocal('127.0.0.1');
        $this->assertEquals('127.0.0.1', $paramConfig->getIpLocal());

        $paramConfig->setPortLocal(8000);
        $this->assertEquals(8000, $paramConfig->getPortLocal());

        $paramGitLab->setGitlabUrl('https://gitlab.example.com');
        $paramConfig->setParamGitLab($paramGitLab);
        $this->assertEquals($paramGitLab, $paramConfig->getParamGitLab());

        $paramPostman->setPostmanApiKey('postman-key');
        $paramConfig->setParamPostman($paramPostman);
        $this->assertEquals($paramPostman, $paramConfig->getParamPostman());

        $paramConfig->setTokenE107('my-token-e107');
        $this->assertEquals('my-token-e107', $paramConfig->getTokenE107());

        $paramNewRelic = new ParamNewRelic();
        $paramNewRelic->setApiUser('user');
        $paramConfig->setParamNewRelic($paramNewRelic);
        $this->assertEquals($paramNewRelic, $paramConfig->getParamNewRelic());
    }

    final public function testParse(): void
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
            'token_e107' => 'e107_token_val',
            'newrelic-api-user' => 'nr_user',
            'newrelic-api-key-rec' => 'nr_key_rec',
            'newrelic-api-key-prod' => 'nr_key_prod',
            'newrelic-account-id-dev' => '1',
            'newrelic-account-id-rec' => '2',
            'newrelic-account-id-pp' => '3',
            'newrelic-account-id-prod' => '4',
        ];

        $paramConfig = ParamConfig::parse($params);

        $this->assertEquals('http://base.url', $paramConfig->getUrlServer());
        $this->assertEquals('local_host', $paramConfig->getIpLocal());
        $this->assertEquals(8080, $paramConfig->getPortLocal());
        $this->assertEquals('pm_key', $paramConfig->getParamPostman()->getPostmanApiKey());
        $this->assertEquals('https://pm.test', $paramConfig->getParamPostman()->getPostmanApiUrl());
        $this->assertEquals('e107_token_val', $paramConfig->getTokenE107());
        $this->assertEquals('db_host', $paramConfig->getParamRepository()->getDatabaseHost());
        $this->assertEquals('https://gitlab.test', $paramConfig->getParamGitLab()->getGitlabUrl());
        $this->assertEquals('nr_user', $paramConfig->getParamNewRelic()->getApiUser());
    }

    final public function testParseWithDefaults(): void
    {
        try {
            $paramConfig = ParamConfig::parse([]);
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('Le tableau de paramètres est vide.', $e->getMessage());
        }
    }

    final public function testJsonSerialize(): void
    {
        $paramConfig = new ParamConfig();
        $paramRepository = new ParamRepository();
        $paramRepository->setDatabaseHost('localhost');
        $paramConfig->setParamRepository($paramRepository);
        $paramGitLab = new ParamGitLab();
        $paramGitLab->setGitlabBusinessContractProjectId(42);
        $paramConfig->setParamGitLab($paramGitLab);
        $paramPostman = new ParamPostman();
        $paramPostman->setPostmanApiKey('pm_key');
        $paramConfig->setParamPostman($paramPostman);
        $paramNewRelic = new ParamNewRelic();
        $paramNewRelic->setApiUser('user');
        $paramNewRelic->setApiKeyRec('key');
        $paramConfig->setParamNewRelic($paramNewRelic);

        $json = json_encode($paramConfig);
        $decoded = json_decode($json, true);

        $this->assertArrayHasKey('paramRepository', $decoded);
        $this->assertArrayHasKey('paramGitLab', $decoded);
        $this->assertArrayHasKey('paramPostman', $decoded);
        $this->assertArrayHasKey('paramNewRelic', $decoded);
    }
}