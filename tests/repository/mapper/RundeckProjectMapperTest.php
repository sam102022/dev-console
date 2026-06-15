<?php
declare(strict_types=1);

namespace App\tests\repository\mapper;

use App\repository\mapper\RundeckProjectMapper;
use App\tests\fixtures\RundeckProjectEntityFixtures;
use App\tests\service\AbstractServiceCase;

class RundeckProjectMapperTest extends AbstractServiceCase
{

    final public function testFromArray(): void
    {
        $data = RundeckProjectEntityFixtures::getRundeckProjectData();
        $entity = RundeckProjectMapper::fromArray($data);

        $this->assertEquals('prod', $entity->getEnv());
        $this->assertEquals('buyers', $entity->getSf());
        $this->assertEquals('Click & Collect', $entity->getCategory());
        $this->assertEquals('fc292753-de32-4745-8389-8db702e60410', $entity->getToken());
        $this->assertEquals('click_and_collect/batch_click_and_collect_reports', $entity->getPath());
        $this->assertEquals('batch_click_and_collect_reports', $entity->getProjectName());
        $this->assertEquals('Batch Click And Collect Reports', $entity->getNom());
    }

    final public function testFromEntity(): void
    {
        $entity = RundeckProjectEntityFixtures::getRundeckProjectEntity();
        $model = RundeckProjectMapper::fromEntity($entity);

        $this->assertEquals('Batch Click And Collect Reports', $model->getNom());
        $this->assertEquals('prod', $model->getEnv());
        $this->assertEquals('buyers', $model->getSf());
        $this->assertEquals('Click & Collect', $model->getCategory());
        $this->assertEquals('fc292753-de32-4745-8389-8db702e60410', $model->getToken());
        $this->assertEquals('click_and_collect/batch_click_and_collect_reports', $model->getPath());
        $this->assertEquals('batch_click_and_collect_reports', $model->getProjectName());
    }

    final public function testToEntity(): void
    {
        $model = RundeckProjectEntityFixtures::getRundeckProject();
        $entity = RundeckProjectMapper::toEntity($model);

        $this->assertEquals('Batch Click And Collect Reports', $entity->getNom());
        $this->assertEquals('prod', $entity->getEnv());
        $this->assertEquals('buyers', $entity->getSf());
        $this->assertEquals('Click & Collect', $entity->getCategory());
        $this->assertEquals('fc292753-de32-4745-8389-8db702e60410', $entity->getToken());
        $this->assertEquals('click_and_collect/batch_click_and_collect_reports', $entity->getPath());
        $this->assertEquals('batch_click_and_collect_reports', $entity->getProjectName());
    }

    final public function testToModel(): void
    {
        $entity = RundeckProjectEntityFixtures::getRundeckProjectEntity();
        $model = RundeckProjectMapper::toModel($entity);

        $this->assertEquals('Batch Click And Collect Reports', $model->getNom());
        $this->assertEquals('prod', $model->getEnv());
        $this->assertEquals('buyers', $model->getSf());
        $this->assertEquals('Click & Collect', $model->getCategory());
        $this->assertEquals('fc292753-de32-4745-8389-8db702e60410', $model->getToken());
        $this->assertEquals('click_and_collect/batch_click_and_collect_reports', $model->getPath());
        $this->assertEquals('batch_click_and_collect_reports', $model->getProjectName());
    }

    final public function testToArray(): void
    {
        $entity = RundeckProjectEntityFixtures::getRundeckProjectEntity();
        $data = RundeckProjectMapper::toArray($entity);

        $expected = RundeckProjectEntityFixtures::getRundeckProjectData();

        $this->assertEquals($expected, $data);
    }
}
