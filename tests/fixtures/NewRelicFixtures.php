<?php
declare(strict_types=1);

namespace App\tests\fixtures;

use App\model\EnumEnvironment;
use App\model\NewRelic;
use App\repository\model\NewRelicEntity;

class NewRelicFixtures
{
    public static function getNewRelicEntity(): NewRelicEntity
    {
        return NewRelicEntity::build('New Project', EnumEnvironment::DEV, 'http://url/a');
    }

    public static function getNewRelic(): NewRelic
    {
        return NewRelic::build('New Project', EnumEnvironment::DEV, 'http://url/a');
    }
}