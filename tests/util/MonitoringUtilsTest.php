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

    public static function parseSubscriptionNameProvider(): array
    {
        return [
            'valid subscription name' => [
                'yamlContent' => 'mdm.core.subscriber.subscription.name: test-subscription',
                'expectedName' => 'test-subscription'
            ],
            'valid subscription name with spaces' => [
                'yamlContent' => 'mdm.core.subscriber.subscription.name:    test-subscription  ',
                'expectedName' => 'test-subscription'
            ],
            'no subscription name' => [
                'yamlContent' => 'mdm.core.subscriber.something.else: test-subscription',
                'expectedName' => null
            ],
            'empty content' => [
                'yamlContent' => '',
                'expectedName' => null
            ],
            'null content' => [
                'yamlContent' => null,
                'expectedName' => null
            ]
        ];
    }

    #[DataProvider('parseSubscriptionNameProvider')]
    public function testParseSubscriptionName(?string $yamlContent, ?string $expectedName): void
    {
        $this->assertEquals($expectedName, MonitoringUtils::parseSubscriptionName($yamlContent));
    }

    public static function parseVariableInValuesFileProvider(): array
    {
        return [
            'valid variable name' => [
                'yamlContent' => "CLICK_AND_COLLECT_REPORTS_SUBSCRIPTION_NAME: \"my-subscription-name\"",
                'variableName' => 'CLICK_AND_COLLECT_REPORTS_SUBSCRIPTION_NAME',
                'expectedValue' => 'my-subscription-name'
            ],
            'valid variable name without quotes' => [
                'yamlContent' => "CLICK_AND_COLLECT_REPORTS_SUBSCRIPTION_NAME: my-subscription-name",
                'variableName' => 'CLICK_AND_COLLECT_REPORTS_SUBSCRIPTION_NAME',
                'expectedValue' => 'my-subscription-name'
            ],
            'valid variable name with spaces' => [
                'yamlContent' => "CLICK_AND_COLLECT_REPORTS_SUBSCRIPTION_NAME:   \"my-subscription-name\"  ",
                'variableName' => 'CLICK_AND_COLLECT_REPORTS_SUBSCRIPTION_NAME',
                'expectedValue' => 'my-subscription-name'
            ],
            'no variable name' => [
                'yamlContent' => "OTHER_VARIABLE: my-subscription-name",
                'variableName' => 'CLICK_AND_COLLECT_REPORTS_SUBSCRIPTION_NAME',
                'expectedValue' => null
            ]
        ];
    }

    #[DataProvider('parseVariableInValuesFileProvider')]
    public function testParseVariableInValuesFile(string $yamlContent, string $variableName, ?string $expectedValue): void
    {
        $this->assertEquals($expectedValue, MonitoringUtils::parseVariableInValuesFile($yamlContent, $variableName));
    }

    public static function parsePackageProvider(): array
    {
        return [
            'with nuxt dependency' => [
                'packageContent' => '{"dependencies": {"nuxt": "^2.15.0"}}',
                'expected' => 'nuxt'
            ],
            'with react dependency' => [
                'packageContent' => '{"dependencies": {"react": "^17.0.0"}}',
                'expected' => 'react'
            ],
            'with both, nuxt first' => [
                'packageContent' => '{"dependencies": {"nuxt": "2.0", "react": "17.0"}}',
                'expected' => 'nuxt'
            ],
            'with both, react first' => [
                'packageContent' => '{"dependencies": {"react": "17.0", "nuxt": "2.0"}}',
                'expected' => 'nuxt' // nuxt is checked first in the function
            ],
            'no relevant dependencies' => [
                'packageContent' => '{"dependencies": {"vue": "^3.0.0"}}',
                'expected' => null
            ],
            'empty content' => [
                'packageContent' => '',
                'expected' => null
            ],
            'null content' => [
                'packageContent' => null,
                'expected' => null
            ]
        ];
    }

    #[DataProvider('parsePackageProvider')]
    public function testParsePackage(?string $packageContent, ?string $expected): void
    {
        $this->assertEquals($expected, MonitoringUtils::parsePackage($packageContent));
    }

    public static function buildUrlProvider(): array
    {
        return [
            'Cloud GCP project' => [
                Project::build('my-project', null, 'sf', 'SF Name', 'subsf', true, null, null, '', null, false, [], [], []),
                EnumEnvironment::DEV,
                [], // projectsInGke (doesn't matter if cloudGCP is true)
                'https://management-my-project.dev.mdm-int.net/actuator/health'
            ],
            'Rancher non-prod' => [
                Project::build('my-project', null, 'sf', 'SF Name', 'subsf', false, null, null, '', null, false, [], [], []),
                EnumEnvironment::REC,
                [],
                'https://management-my-project.app-rec.xm/actuator/health'
            ],
            'Rancher prod, migrated to GKE' => [
                Project::build('migrated-project', null, 'sf', 'SF Name', 'subsf', false, null, null, '', null, false, [], [], []),
                EnumEnvironment::PROD,
                ['migrated-project'],
                'https://management-migrated-project.prod.mdm-int.net/actuator/health'
            ],
            'Rancher prod, not migrated' => [
                Project::build('my-project', null, 'sf', 'SF Name', 'subsf', false, null, null, '', null, false, [], [], []),
                EnumEnvironment::PROD,
                [],
                'https://management-my-project.app.xm/actuator/health'
            ],
            'API project' => [
                Project::build('api-my-project', null, 'sf', 'SF Name', 'subsf', true, null, null, '', null, false, [], [], []),
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