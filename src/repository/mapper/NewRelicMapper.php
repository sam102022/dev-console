<?php
declare(strict_types=1);

namespace App\repository\mapper;

use App\model\NewRelic;
use App\repository\model\NewRelicEntity;

class NewRelicMapper
{
    public static function toEntity(NewRelic $model): NewRelicEntity
    {
        $entity = new NewRelicEntity();
        $entity->setName($model->getName());
        $entity->setEnvironment($model->getEnvironment());
        $entity->setUrl($model->getUrl());
        return $entity;
    }

    public static function toModel(NewRelicEntity $entity): NewRelic
    {
        $model = new NewRelic();
        $model->setName($entity->getName());
        $model->setEnvironment($entity->getEnvironment());
        $model->setUrl($entity->getUrl());
        return $model;
    }
}