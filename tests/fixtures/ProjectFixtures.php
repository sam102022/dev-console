<?php
declare(strict_types=1);

namespace App\tests\fixtures;

use App\model\Project;

class ProjectFixtures
{
    public static function getProjectWithUrls(): Project
    {
        return Project::build('New Project', 'service-name', 'domain', 'domainName', 'subsf', false,
            '2.7.18', '21',  'java', null, 'http://url/a', false,
            ['dev' => 'http://url/dev', 'rec' => 'http://url/rec', 'pp' => 'http://url/pp', 'prod' => 'http://url/prod'], [], [], [], null, []);
    }

    public static function getMonitoringProject(string $name, bool $isCloudGcp): Project
    {
        return Project::build($name, null, 'domain', 'SF Name', 'subsf', $isCloudGcp,
            null, null, '', null, 'http://url', false, [], [], [], [], null, []);
    }
}