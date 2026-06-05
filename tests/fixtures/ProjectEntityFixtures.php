<?php
declare(strict_types=1);

namespace App\tests\fixtures;

use App\repository\model\ProjectEntity;

class ProjectEntityFixtures
{
    public static function getProjectAEntity(): ProjectEntity
    {
        return ProjectEntity::build('project-a', 'serviceName',
            'domain', 'domainName', 'subsf', true,
            '2.7.0', '17', 'java',
            'subscriptionName', 'http://url', false,
            [], [], [], [], null, []);
    }

    public static function getProjectBEntity(): ProjectEntity
    {
        return ProjectEntity::build('project-b', null,
            'e', ' e ', 'f', false,
            null, null, '', null,
            'http://url/b', false,
            [], [], [], [], null, []);
    }
}