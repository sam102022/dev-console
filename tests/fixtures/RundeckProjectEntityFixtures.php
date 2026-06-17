<?php
declare(strict_types=1);

namespace App\tests\fixtures;

use App\model\RundeckProject;
use App\repository\model\RundeckProjectEntity;

class RundeckProjectEntityFixtures
{
    public static function getRundeckProjectEntity(): RundeckProjectEntity
    {
        $entity = new RundeckProjectEntity();
        $entity->setEnv('prod');
        $entity->setSf('buyers');
        $entity->setCategory('Click & Collect');
        $entity->setToken('fc292753-de32-4745-8389-8db702e60410');
        $entity->setPath('click_and_collect/batch_click_and_collect_reports');
        $entity->setProjectName('batch_click_and_collect_reports');
        $entity->setNom('Batch Click And Collect Reports');
        return $entity;
    }

    public static function getRundeckProjectEntityFromCache(): RundeckProjectEntity
    {
        $entity = new RundeckProjectEntity();
        $entity->setEnv('prod');
        $entity->setSf('buyers');
        $entity->setCategory('Click & Collect');
        $entity->setToken('fc292753-de32-4745-8389-8db702e60410');
        $entity->setPath('click_and_collect/batch_click_and_collect_reports');
        $entity->setProjectName('batch_click_and_collect_reports');
        $entity->setNom('Batch Click And Collect Reports');
        return $entity;
    }

    public static function getRundeckProjectData(): array
    {
        return [
            'env' => 'prod',
            'sf' => 'buyers',
            'category' => 'Click & Collect',
            'token' => 'fc292753-de32-4745-8389-8db702e60410',
            'path' => 'click_and_collect/batch_click_and_collect_reports',
            'projectName' => 'batch_click_and_collect_reports',
            'nom' => 'Batch Click And Collect Reports',
        ];
    }

    public static function getRundeckProject(): RundeckProject
    {
        $model = new RundeckProject();
        $model->setEnv('prod');
        $model->setSf('buyers');
        $model->setCategory('Click & Collect');
        $model->setToken('fc292753-de32-4745-8389-8db702e60410');
        $model->setPath('click_and_collect/batch_click_and_collect_reports');
        $model->setProjectName('batch_click_and_collect_reports');
        $model->setNom('Batch Click And Collect Reports');
        return $model;
    }
}