<?php
declare(strict_types=1);

namespace App\tests\util;

use App\model\EnumEnvironment;
use App\util\MonitoringUtils;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MonitoringUtilsTest extends TestCase
{
    public static function buildUrlProvider(): array
    {
        return [
            'Cloud GCP project' => [
                ['name' => 'my-project', 'cloudGCP' => true],
                EnumEnvironment::DEV,
                [], // projectsInGke (doesn't matter if cloudGCP is true)
                'https://management-my-project.dev.mdm-int.net/actuator/health'
            ],
            'Rancher non-prod' => [
                ['name' => 'my-project', 'cloudGCP' => false],
                EnumEnvironment::REC,
                [],
                'https://management-my-project.app-rec.xm/actuator/health'
            ],
            'Rancher prod, migrated to GKE' => [
                ['name' => 'migrated-project', 'cloudGCP' => false],
                EnumEnvironment::PROD,
                ['migrated-project'],
                'https://management-migrated-project.prod.mdm-int.net/actuator/health'
            ],
            'Rancher prod, not migrated' => [
                ['name' => 'my-project', 'cloudGCP' => false],
                EnumEnvironment::PROD,
                [],
                'https://management-my-project.app.xm/actuator/health'
            ],
            'API project' => [
                ['name' => 'api-my-project', 'cloudGCP' => true],
                EnumEnvironment::DEV,
                [],
                'https://management-api-my-project.dev.mdm-int.net/v1/actuator/health'
            ],
        ];
    }

    #[DataProvider('buildUrlProvider')]
    public function testBuildUrlHealthCheck(array $project, EnumEnvironment $env, array $projectsInGke, string $expectedUrl): void
    {
        $url = MonitoringUtils::buildUrlHealthCheck($project, $env, $projectsInGke);

        $this->assertEquals($expectedUrl, $url);
    }
}
