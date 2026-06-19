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
        $entity->setSf('buyers');
        $entity->setCategory('Click & Collect');
        $entity->setToken([['dev' => '', 'prod' => 'fc292753-de32-4745-8389-8db702e60410']]);
        $entity->setPath('click_and_collect/batch_click_and_collect_reports');
        $entity->setProjectName('batch_click_and_collect_reports');
        $entity->setName('Batch Click And Collect Reports');
        $entity->setDomain('example.com');
        return $entity;
    }

    public static function getRundeckProjectEntityFromCache(): RundeckProjectEntity
    {
        $entity = new RundeckProjectEntity();
        $entity->setSf('buyers');
        $entity->setCategory('Click & Collect');
        $entity->setToken([['dev' => '', 'prod' => 'fc292753-de32-4745-8389-8db702e60410']]);
        $entity->setPath('click_and_collect/batch_click_and_collect_reports');
        $entity->setProjectName('batch_click_and_collect_reports');
        $entity->setName('Batch Click And Collect Reports');
        $entity->setDomain('example.com');
        return $entity;
    }

    public static function getRundeckProjectData(): array
    {
        return [
            'sf' => 'buyers',
            'category' => 'Click & Collect',
            'token' => [['dev' => '', 'prod' => 'fc292753-de32-4745-8389-8db702e60410']],
            'path' => 'click_and_collect/batch_click_and_collect_reports',
            'projectName' => 'batch_click_and_collect_reports',
            'name' => 'Batch Click And Collect Reports',
            'domain' => 'example.com',
        ];
    }

    public static function getRundeckProject(): RundeckProject
    {
        $model = new RundeckProject();
        $model->setSf('buyers');
        $model->setCategory('Click & Collect');
        $model->setToken([['dev' => '', 'prod' => 'fc292753-de32-4745-8389-8db702e60410']]);
        $model->setPath('click_and_collect/batch_click_and_collect_reports');
        $model->setProjectName('batch_click_and_collect_reports');
        $model->setName('Batch Click And Collect Reports');
        $model->setDomain('example.com');
        return $model;
    }
}