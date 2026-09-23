<?php
declare(strict_types=1);

namespace App\tests\repository\mapper;

use App\model\RundeckProject;
use App\repository\mapper\RundeckProjectMapper;
use App\repository\model\RundeckProjectEntity;
use App\tests\AbstractTestCase;
use App\tests\fixtures\RundeckProjectEntityFixtures;

class RundeckProjectMapperTest extends AbstractTestCase
{

    final public function testFromArray(): void
    {
        $data = RundeckProjectEntityFixtures::getRundeckProjectData();
        $data['tags'] = ['paiement', 'batch'];
        $entity = RundeckProjectMapper::fromArray($data);

        $this->assertEquals('buyers', $entity->getSf());
        $this->assertEquals('Click & Collect', $entity->getCategory());
        $this->assertEquals([['dev' => '', 'prod' => 'fc292753-de32-4745-8389-8db702e60410']], $entity->getToken());
        $this->assertEquals('click_and_collect/batch_click_and_collect_reports', $entity->getPath());
        $this->assertEquals('batch_click_and_collect_reports', $entity->getProjectName());
        $this->assertEquals('Batch Click And Collect Reports', $entity->getName());
        $this->assertEquals('example.com', $entity->getDomain());
        $this->assertEquals(['paiement', 'batch'], $entity->getTags());
    }

    final public function testToEntity(): void
    {
        $model = RundeckProjectEntityFixtures::getRundeckProject();
        $model->setTags(['flux', 'caisse']);
        $entity = RundeckProjectMapper::toEntity($model);

        $this->assertEquals('Batch Click And Collect Reports', $entity->getName());
        $this->assertEquals('example.com', $entity->getDomain());
        $this->assertEquals('buyers', $entity->getSf());
        $this->assertEquals('Click & Collect', $entity->getCategory());
        $this->assertEquals([['dev' => '', 'prod' => 'fc292753-de32-4745-8389-8db702e60410']], $entity->getToken());
        $this->assertEquals('click_and_collect/batch_click_and_collect_reports', $entity->getPath());
        $this->assertEquals('batch_click_and_collect_reports', $entity->getProjectName());
        $this->assertEquals(['flux', 'caisse'], $entity->getTags());
    }

    final public function testToModel(): void
    {
        $entity = RundeckProjectEntityFixtures::getRundeckProjectEntity();
        $entity->setTags(['api', 'orders']);
        $model = RundeckProjectMapper::toModel($entity);

        $this->assertEquals('Batch Click And Collect Reports', $model->getName());
        $this->assertEquals('example.com', $model->getDomain());
        $this->assertEquals('buyers', $model->getSf());
        $this->assertEquals('Click & Collect', $model->getCategory());
        $this->assertEquals([['dev' => '', 'prod' => 'fc292753-de32-4745-8389-8db702e60410']], $model->getToken());
        $this->assertEquals('click_and_collect/batch_click_and_collect_reports', $model->getPath());
        $this->assertEquals('batch_click_and_collect_reports', $model->getProjectName());
        $this->assertEquals(['api', 'orders'], $model->getTags());
    }

    final public function testToArray(): void
    {
        $entity = RundeckProjectEntityFixtures::getRundeckProjectEntity();
        $data = RundeckProjectMapper::toArray($entity);

        $expected = RundeckProjectEntityFixtures::getRundeckProjectData();

        $this->assertEquals($expected, $data);
    }

    final public function testTagNormalization(): void
    {
        $project = new RundeckProject();
        $project->setTags([' Tag1 ', 'TAG2', 'tag1', '  ', 'TAG3']);
        $this->assertEquals(['tag1', 'tag2', 'tag3'], $project->getTags());

        $entity = new RundeckProjectEntity();
        $entity->setTags([' TagA ', 'TAGB', 'taga', '', 'tagC']);
        $this->assertEquals(['taga', 'tagb', 'tagc'], $entity->getTags());
    }
}
