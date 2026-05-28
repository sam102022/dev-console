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
    public static function buildUrlProvider(): array
    {
        return [
            'Cloud GCP project' => [
                Project::build('my-project', 'sf', 'sfName', 'subsf', true, null, null),
                EnumEnvironment::DEV,
                [], // projectsInGke (doesn't matter if cloudGCP is true)
                'https://management-my-project.dev.mdm-int.net/actuator/health'
            ],
            'Rancher non-prod' => [
                Project::build('my-project', 'sf', 'sfName', 'subsf', false, null, null),
                EnumEnvironment::REC,
                [],
                'https://management-my-project.app-rec.xm/actuator/health'
            ],
            'Rancher prod, migrated to GKE' => [
                Project::build('migrated-project', 'sf', 'sfName', 'subsf', false, null, null),
                EnumEnvironment::PROD,
                ['migrated-project'],
                'https://management-migrated-project.prod.mdm-int.net/actuator/health'
            ],
            'Rancher prod, not migrated' => [
                Project::build('my-project', 'sf', 'sfName', 'subsf', false, null, null),
                EnumEnvironment::PROD,
                [],
                'https://management-my-project.app.xm/actuator/health'
            ],
            'API project' => [
                Project::build('api-my-project', 'sf', 'sfName', 'subsf', true, null, null),
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
