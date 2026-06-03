<?php
declare(strict_types=1);

namespace App\tests\util;

use App\model\EnumEnvironment;
use App\model\Project;
use App\tests\fixtures\ProjectFixtures;
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
                ProjectFixtures::getMonitoringProject('my-project', true),
                EnumEnvironment::DEV,
                [], // projectsInGke (doesn't matter if cloudGCP is true)
                'https://management-my-project.dev.mdm-int.net/actuator/health'
            ],
            'Rancher non-prod' => [
                ProjectFixtures::getMonitoringProject('my-project', false),
                EnumEnvironment::REC,
                [],
                'https://management-my-project.app-rec.xm/actuator/health'
            ],
            'Rancher prod, migrated to GKE' => [
                ProjectFixtures::getMonitoringProject('migrated-project', false),
                EnumEnvironment::PROD,
                ['migrated-project'],
                'https://management-migrated-project.prod.mdm-int.net/actuator/health'
            ],
            'Rancher prod, not migrated' => [
                ProjectFixtures::getMonitoringProject('my-project', false),
                EnumEnvironment::PROD,
                [],
                'https://management-my-project.app.xm/actuator/health'
            ],
            'API project' => [
                ProjectFixtures::getMonitoringProject('api-my-project', true),
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

    public static function buildPubSubUrlProvider(): array
    {
        return [
            'dev environment' => [
                ProjectFixtures::getMonitoringProject('my-project', true)->setSubscriptionName('my-subscription'),
                EnumEnvironment::DEV,
                'https://console.cloud.google.com/cloudpubsub/topic/detail/my-subscription_ops?project=dev-mdm-subsf&inv=1&invt=Ab5XmQ&tab=messages'
            ],
            'rec environment' => [
                ProjectFixtures::getMonitoringProject('my-project', true)->setSubscriptionName('my-subscription'),
                EnumEnvironment::REC,
                'https://console.cloud.google.com/cloudpubsub/topic/detail/my-subscription_ops?project=rec-mdm-subsf&inv=1&invt=Ab5XmQ&tab=messages'
            ],
            'prod environment' => [
                ProjectFixtures::getMonitoringProject('my-project', true)->setSubscriptionName('my-subscription'),
                EnumEnvironment::PROD,
                'https://console.cloud.google.com/cloudpubsub/topic/detail/my-subscription_ops?project=mdm-subsf&inv=1&invt=Ab5XmQ&tab=messages'
            ],
        ];
    }

    #[DataProvider('buildPubSubUrlProvider')]
    public function testBuildPubSubUrl(Project $project, EnumEnvironment $env, string $expectedUrl): void
    {
        $this->assertEquals($expectedUrl, MonitoringUtils::buildPubSubUrl($project, $env));
    }

    public static function buildKibanaLogUrlProvider(): array
    {
        return [
            'prod environment' => [
                ProjectFixtures::getMonitoringProject('my-project', false),
                EnumEnvironment::PROD,
                "http://kibana.gestionlogs.app.xm/app/kibana#/dashboard/410c2c80-8dd8-11e9-bab3-47b86eb95c19?_g=(filters:!(),refreshInterval:(pause:!t,value:0),time:(from:now-30m,to:now))&_a=(description:'',filters:!(('\$state':(store:appState),meta:(alias:!n,controlledBy:'1560429562986',disabled:!f,index:'703f0680-e486-11e9-915a-d563e49bee67',key:kubernetes.namespace.keyword,negate:!f,params:(query:subsf-prod),type:phrase),query:(match_phrase:(kubernetes.namespace.keyword:subsf-prod))),('\$state':(store:appState),meta:(alias:!n,controlledBy:'1560429093025',disabled:!f,index:'703f0680-e486-11e9-915a-d563e49bee67',key:kubernetes.container.name.keyword,negate:!f,params:(query:my-project),type:phrase),query:(match_phrase:(kubernetes.container.name.keyword:my-project)))),fullScreenMode:!f,options:(hidePanelTitles:!f,useMargins:!t),query:(language:kuery,query:''),tags:!(),timeRestore:!f,title:'Logs%20MdM%20(Kubernetes)',viewMode:view)"
            ],
            'dev environment' => [
                ProjectFixtures::getMonitoringProject('my-project', false),
                EnumEnvironment::DEV,
                "http://kibana.gestionlogs.app-dev.xm/app/dashboards#/view/410c2c80-8dd8-11e9-bab3-47b86eb95c19?_g=(filters:!(),refreshInterval:(pause:!t,value:0),time:(from:now-30m,to:now))&_a=(description:'',filters:!(('\$state':(store:appState),meta:(alias:!n,controlledBy:'1560429562986',disabled:!f,index:'703f0680-e486-11e9-915a-d563e49bee67',key:kubernetes.namespace.keyword,negate:!f,params:(query:subsf-dev),type:phrase),query:(match_phrase:(kubernetes.namespace.keyword:subsf-dev))),('\$state':(store:appState),meta:(alias:!n,controlledBy:'1560429093025',disabled:!f,index:'703f0680-e486-11e9-915a-d563e49bee67',key:kubernetes.container.name.keyword,negate:!f,params:(query:my-project),type:phrase),query:(match_phrase:(kubernetes.container.name.keyword:my-project)))),fullScreenMode:!f,options:(hidePanelTitles:!f,useMargins:!t),query:(language:kuery,query:''),tags:!(),timeRestore:!f,title:'Logs%20MdM%20(Kubernetes)',viewMode:view)"
            ],
            'null environment' => [
                ProjectFixtures::getMonitoringProject('my-project', false),
                null,
                "http://kibana.gestionlogs.app%s.xm410c2c80-8dd8-11e9-bab3-47b86eb95c19?_g=(filters:!(),refreshInterval:(pause:!t,value:0),time:(from:now-30m,to:now))&_a=(description:'',filters:!(('\$state':(store:appState),meta:(alias:!n,controlledBy:'1560429562986',disabled:!f,index:'703f0680-e486-11e9-915a-d563e49bee67',key:kubernetes.namespace.keyword,negate:!f,params:(query:subsf-%s),type:phrase),query:(match_phrase:(kubernetes.namespace.keyword:subsf-%s))),('\$state':(store:appState),meta:(alias:!n,controlledBy:'1560429093025',disabled:!f,index:'703f0680-e486-11e9-915a-d563e49bee67',key:kubernetes.container.name.keyword,negate:!f,params:(query:my-project),type:phrase),query:(match_phrase:(kubernetes.container.name.keyword:my-project)))),fullScreenMode:!f,options:(hidePanelTitles:!f,useMargins:!t),query:(language:kuery,query:''),tags:!(),timeRestore:!f,title:'Logs%20MdM%20(Kubernetes)',viewMode:view)"
            ]
        ];
    }

    #[DataProvider('buildKibanaLogUrlProvider')]
    public function testBuildKibanaLogUrl(Project $project, ?EnumEnvironment $env, string $expectedUrl): void
    {
        $this->assertEquals($expectedUrl, MonitoringUtils::buildKibanaLogUrl($project, $env));
    }

    public static function buildGCPLogUrlProvider(): array
    {
        return [
            'api project in dev' => [
                ProjectFixtures::getMonitoringProject('api-my-project', true),
                EnumEnvironment::DEV,
                '/https:\/\/console\.cloud\.google\.com\/logs\/query;query=resource\.labels\.namespace_name%3D%22subsf%22%0Alabels\.k8s-pod%2Fapp_kubernetes_io%2Finstance%3D%22api-my-project%22%0Aresource\.labels\.container_name%3D%22app-java-api%22;storageScope=storage,projects%2Fmdm-observability-dev%2Flocations%2Feu%2Fbuckets%2Fmdm-observability-dev\.common_logs%2Fviews%2F_AllLogs,projects%2Fmdm-observability-dev%2Flocations%2Feu%2Fbuckets%2Fmdm-observability-dev\.infra_logs%2Fviews%2F_AllLogs,projects%2Fmdm-observability-dev%2Flocations%2Fglobal%2Fbuckets%2F_Default%2Fviews%2F_AllLogs,projects%2Fmdm-observability-dev%2Flocations%2Fglobal%2Fbuckets%2F_Default%2Fviews%2F_Default,projects%2Fmdm-observability-dev%2Flocations%2Fglobal%2Fbuckets%2F_Required%2Fviews%2F_AllLogs;cursorTimestamp=.*?;histogramBreakdownField=severity;duration=P14D\?invt=AbtxOw&project=mdm-observability-dev/'
            ],
            'front project in rec' => [
                ProjectFixtures::getMonitoringProject('front-my-project', true),
                EnumEnvironment::REC,
                '/https:\/\/console\.cloud\.google\.com\/logs\/query;query=resource\.labels\.namespace_name%3D%22subsf%22%0Alabels\.k8s-pod%2Fapp_kubernetes_io%2Finstance%3D%22front-my-project%22%0Aresource\.labels\.container_name%3D;storageScope=storage,projects%2Fmdm-observability-rec%2Flocations%2Feu%2Fbuckets%2Fmdm-observability-rec\.common_logs%2Fviews%2F_AllLogs,projects%2Fmdm-observability-rec%2Flocations%2Feu%2Fbuckets%2Fmdm-observability-rec\.infra_logs%2Fviews%2F_AllLogs,projects%2Fmdm-observability-rec%2Flocations%2Fglobal%2Fbuckets%2F_Default%2Fviews%2F_AllLogs,projects%2Fmdm-observability-rec%2Flocations%2Fglobal%2Fbuckets%2F_Default%2Fviews%2F_Default,projects%2Fmdm-observability-rec%2Flocations%2Fglobal%2Fbuckets%2F_Required%2Fviews%2F_AllLogs;cursorTimestamp=.*?;histogramBreakdownField=severity;duration=P14D\?invt=AbtxOw&project=mdm-observability-rec/'
            ],
        ];
    }

    #[DataProvider('buildGCPLogUrlProvider')]
    public function testBuildGCPLogUrl(Project $project, ?EnumEnvironment $env, string $expectedRegex): void
    {
        $this->assertMatchesRegularExpression($expectedRegex, MonitoringUtils::buildGCPLogUrl($project, $env));
    }

    public function testBuildLogUrl(): void
    {
        $projectGcp = ProjectFixtures::getMonitoringProject('my-project', true);
        $projectRancher = ProjectFixtures::getMonitoringProject('my-project', false);

        $this->assertStringContainsString('console.cloud.google.com', MonitoringUtils::buildLogUrl($projectGcp, EnumEnvironment::DEV));
        $this->assertStringContainsString('kibana.gestionlogs.app', MonitoringUtils::buildLogUrl($projectRancher, EnumEnvironment::DEV));
    }

    public static function buildFrontReactUrlProvider(): array
    {
        return [
            'normal rancher dev' => [
                ProjectFixtures::getMonitoringProject('front-my-project', false),
                EnumEnvironment::DEV,
                'my-token',
                'https://front-my-project.subsf.app-dev.xm/?lk=my-token'
            ],
            'normal rancher prod' => [
                ProjectFixtures::getMonitoringProject('front-my-project', false),
                EnumEnvironment::PROD,
                'my-token',
                'https://front-my-project.subsf.app.xm/?lk=my-token'
            ],
            'gcp dev' => [
                ProjectFixtures::getMonitoringProject('front-my-project', true),
                EnumEnvironment::DEV,
                'my-token',
                'https://front-my-project.dev.mdm-int.net/?lk=my-token'
            ],
            'special project front-store-reception-gap' => [
                ProjectFixtures::getMonitoringProject('front-store-reception-gap', false),
                EnumEnvironment::DEV,
                'my-token',
                'https://front-store-reception-arbitration.subsf.app-dev.xm/?lk=my-token'
            ],
            'special project front-store-till-contact' => [
                ProjectFixtures::getMonitoringProject('front-store-till-contact', false),
                EnumEnvironment::DEV,
                'my-token',
                'https://front-store-till-contact.subsf.app-dev.xm/?lk=my-token&idMag=124&CodeLng=fr'
            ],
            'special project front-dossier-client' => [
                ProjectFixtures::getMonitoringProject('front-dossier-client', false),
                EnumEnvironment::DEV,
                'my-token',
                'https://front-dossier-client.subsf.app-dev.xm/?lk=my-token&nobl=75226562'
            ],
        ];
    }

    #[DataProvider('buildFrontReactUrlProvider')]
    public function testBuildFrontReactUrl(Project $project, EnumEnvironment $env, string $token, string $expectedUrl): void
    {
        $this->assertEquals($expectedUrl, MonitoringUtils::buildFrontReactUrl($project, $env, $token));
    }

    public static function buildFrontPhpUrlProvider(): array
    {
        return [
            'dev env' => [
                ProjectFixtures::getMonitoringProject('zend-my-project', false),
                EnumEnvironment::DEV,
                ''
            ],
            'pp env' => [
                ProjectFixtures::getMonitoringProject('zend-my-project', false),
                EnumEnvironment::PP,
                ''
            ],
            'rec env' => [
                ProjectFixtures::getMonitoringProject('zend-my-project', false),
                EnumEnvironment::REC,
                'https://intranet-rec.siege.xm/portail/public/zend-my-project/index'
            ],
            'prod env' => [
                ProjectFixtures::getMonitoringProject('zend-my-project', false),
                EnumEnvironment::PROD,
                'https://intranet.siege.xm/portail/public/zend-my-project/index'
            ],
        ];
    }

    #[DataProvider('buildFrontPhpUrlProvider')]
    public function testBuildFrontPhpUrl(Project $project, EnumEnvironment $env, string $expectedUrl): void
    {
        $this->assertEquals($expectedUrl, MonitoringUtils::buildFrontPhpUrl($project, $env));
    }

    public static function buildRundeckUrlProvider(): array
    {
        return [
            'dev env' => [
                ProjectFixtures::getMonitoringProject('batch-my-project', false),
                EnumEnvironment::DEV,
                'https://rundeck-dev.siege.xm/project/subsf/jobs'
            ],
            'prod env' => [
                ProjectFixtures::getMonitoringProject('batch-my-project', false),
                EnumEnvironment::PROD,
                'https://rundeck-prod.siege.xm/project/subsf/jobs'
            ]
        ];
    }

    #[DataProvider('buildRundeckUrlProvider')]
    public function testBuildRundeckUrl(Project $project, EnumEnvironment $env, string $expectedUrl): void
    {
        $this->assertEquals($expectedUrl, MonitoringUtils::buildRundeckUrl($project, $env));
    }
}
