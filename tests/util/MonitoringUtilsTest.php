<?php
declare(strict_types=1);

namespace App\tests\util;

use App\model\EnumEnvironment;
use App\model\Project;
use App\util\MonitoringUtils;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MonitoringUtilsTest extends TestCase
{
    public static function parseServiceNameProvider(): array
    {
        return [
            'valid deploy.yaml' => [
                'yamlContent' => <<<YAML
apiVersion: apps/v1
kind: Deployment
metadata:
  name: api-click-and-collect-v1
  labels:
    app: my-app
YAML,
                'expectedName' => 'api-click-and-collect-v1'
            ],
            'deploy.yaml with different spacing' => [
                'yamlContent' => <<<YAML
metadata:
    name:   my-custom-service-name
spec:
    replicas: 1
YAML,
                'expectedName' => 'my-custom-service-name'
            ],
            'missing name in metadata' => [
                'yamlContent' => <<<YAML
apiVersion: apps/v1
kind: Deployment
metadata:
  labels:
    app: my-app
YAML,
                'expectedName' => null
            ],
            'empty content' => [
                'yamlContent' => '',
                'expectedName' => null
            ],
            'null content' => [
                'yamlContent' => null,
                'expectedName' => null
            ],
            'invalid yaml format but matching regex' => [
                'yamlContent' => 'metadata: name: invalid-yaml-but-matches',
                'expectedName' => 'invalid-yaml-but-matches'
            ]
        ];
    }

    #[DataProvider('parseServiceNameProvider')]
    public function testParseServiceName(?string $yamlContent, ?string $expectedName): void
    {
        $actualName = MonitoringUtils::parseServiceName($yamlContent);
        $this->assertEquals($expectedName, $actualName);
    }

    public static function buildUrlProvider(): array
    {
        return [
            'Cloud GCP project' => [
                Project::build('my-project', null, 'sf', 'SF Name', 'subsf', true, null, null, '', false, [], []),
                EnumEnvironment::DEV,
                [], // projectsInGke (doesn't matter if cloudGCP is true)
                'https://management-my-project.dev.mdm-int.net/actuator/health'
            ],
            'Rancher non-prod' => [
                Project::build('my-project', null, 'sf', 'SF Name', 'subsf', false, null, null, '', false, [], []),
                EnumEnvironment::REC,
                [],
                'https://management-my-project.app-rec.xm/actuator/health'
            ],
            'Rancher prod, migrated to GKE' => [
                Project::build('migrated-project', null, 'sf', 'SF Name', 'subsf', false, null, null, '', false, [], []),
                EnumEnvironment::PROD,
                ['migrated-project'],
                'https://management-migrated-project.prod.mdm-int.net/actuator/health'
            ],
            'Rancher prod, not migrated' => [
                Project::build('my-project', null, 'sf', 'SF Name', 'subsf', false, null, null, '', false, [], []),
                EnumEnvironment::PROD,
                [],
                'https://management-my-project.app.xm/actuator/health'
            ],
            'API project' => [
                Project::build('api-my-project', null, 'sf', 'SF Name', 'subsf', true, null, null, '', false, [], []),
                EnumEnvironment::DEV,
                [],
                'https://management-api-my-project.dev.mdm-int.net/v1/actuator/health'
            ],
        ];
    }

    #[DataProvider('buildUrlProvider')]
    public function testBuildUrlHealthCheck(Project $project, EnumEnvironment $env, array $projectsInGke, string $expectedUrl): void
    {
        $url = MonitoringUtils::buildUrlHealthCheck($project, $env, $projectsInGke);

        $this->assertEquals($expectedUrl, $url);
    }
}
