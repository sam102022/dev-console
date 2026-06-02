<?php
declare(strict_types=1);

namespace App\tests\repository\mapper;

use App\model\GitlabProject;
use App\repository\mapper\GitlabProjectMapper;
use App\repository\model\GitlabProjectEntity;
use App\tests\service\AbstractServiceCase;

class GitlabProjectMapperTest extends AbstractServiceCase
{

    public function testFromArray(): void
    {
        $data = [
            'id' => 1,
            'description' => 'A project',
            'name' => 'project-name',
            'name_with_namespace' => 'group / project-name',
            'path' => 'project-path',
            'path_with_namespace' => 'group/project-path',
            'created_at' => '2023-01-01',
            'default_branch' => 'main',
            'web_url' => 'http://localhost/project',
            'archived' => false,
        ];

        $entity = GitlabProjectMapper::fromArray($data);

        $this->assertInstanceOf(GitlabProjectEntity::class, $entity);
        $this->assertEquals(1, $entity->getId());
        $this->assertEquals('A project', $entity->getDescription());
        $this->assertEquals('project-name', $entity->getName());
        $this->assertEquals('group / project-name', $entity->getNameWithNamespace());
        $this->assertEquals('project-path', $entity->getPath());
        $this->assertEquals('group/project-path', $entity->getPathWithNamespace());
        $this->assertEquals('2023-01-01', $entity->getCreatedAt());
        $this->assertEquals('main', $entity->getDefaultBranch());
        $this->assertEquals('http://localhost/project', $entity->getWebUrl());
        $this->assertFalse($entity->isArchived());
    }

    public function testFromEntity(): void
    {
        $entity = new GitlabProjectEntity();
        $entity->setId(1);
        $entity->setDescription('A project');
        $entity->setName('project-name');
        $entity->setNameWithNamespace('group / project-name');
        $entity->setPath('project-path');
        $entity->setPathWithNamespace('group/project-path');
        $entity->setCreatedAt('2023-01-01');
        $entity->setDefaultBranch('main');
        $entity->setWebUrl('http://localhost/project');
        $entity->setArchived(false);

        $model = GitlabProjectMapper::fromEntity($entity);

        $this->assertInstanceOf(GitlabProject::class, $model);
        $this->assertEquals(1, $model->getId());
        $this->assertEquals('A project', $model->getDescription());
        $this->assertEquals('project-name', $model->getName());
        $this->assertEquals('group / project-name', $model->getNameWithNamespace());
        $this->assertEquals('project-path', $model->getPath());
        $this->assertEquals('group/project-path', $model->getPathWithNamespace());
        $this->assertEquals('2023-01-01', $model->getCreatedAt());
        $this->assertEquals('main', $model->getDefaultBranch());
        $this->assertEquals('http://localhost/project', $model->getWebUrl());
        $this->assertFalse($model->isArchived());
    }

    public function testToEntity(): void
    {
        $model = new GitlabProject();
        $model->setId(1);
        $model->setDescription('A project');
        $model->setName('project-name');
        $model->setNameWithNamespace('group / project-name');
        $model->setPath('project-path');
        $model->setPathWithNamespace('group/project-path');
        $model->setCreatedAt('2023-01-01');
        $model->setDefaultBranch('main');
        $model->setWebUrl('http://localhost/project');
        $model->setArchived(false);

        $entity = GitlabProjectMapper::toEntity($model);

        $this->assertInstanceOf(GitlabProjectEntity::class, $entity);
        $this->assertEquals(1, $entity->getId());
        $this->assertEquals('A project', $entity->getDescription());
        $this->assertEquals('project-name', $entity->getName());
        $this->assertEquals('group / project-name', $entity->getNameWithNamespace());
        $this->assertEquals('project-path', $entity->getPath());
        $this->assertEquals('group/project-path', $entity->getPathWithNamespace());
        $this->assertEquals('2023-01-01', $entity->getCreatedAt());
        $this->assertEquals('main', $entity->getDefaultBranch());
        $this->assertEquals('http://localhost/project', $entity->getWebUrl());
        $this->assertFalse($entity->isArchived());
    }

    public function testToModel(): void
    {
        $entity = new GitlabProjectEntity();
        $entity->setId(1);
        $entity->setDescription('A project');
        $entity->setName('project-name');
        $entity->setNameWithNamespace('group / project-name');
        $entity->setPath('project-path');
        $entity->setPathWithNamespace('group/project-path');
        $entity->setCreatedAt('2023-01-01');
        $entity->setDefaultBranch('main');
        $entity->setWebUrl('http://localhost/project');
        $entity->setArchived(false);

        $model = GitlabProjectMapper::toModel($entity);

        $this->assertInstanceOf(GitlabProject::class, $model);
        $this->assertEquals(1, $model->getId());
        $this->assertEquals('A project', $model->getDescription());
        $this->assertEquals('project-name', $model->getName());
        $this->assertEquals('group / project-name', $model->getNameWithNamespace());
        $this->assertEquals('project-path', $model->getPath());
        $this->assertEquals('group/project-path', $model->getPathWithNamespace());
        $this->assertEquals('2023-01-01', $model->getCreatedAt());
        $this->assertEquals('main', $model->getDefaultBranch());
        $this->assertEquals('http://localhost/project', $model->getWebUrl());
        $this->assertFalse($model->isArchived());
    }

    public function testToArray(): void
    {
        $entity = new GitlabProjectEntity();
        $entity->setId(1);
        $entity->setDescription('A project');
        $entity->setName('project-name');
        $entity->setNameWithNamespace('group / project-name');
        $entity->setPath('project-path');
        $entity->setPathWithNamespace('group/project-path');
        $entity->setCreatedAt('2023-01-01');
        $entity->setDefaultBranch('main');
        $entity->setWebUrl('http://localhost/project');
        $entity->setArchived(false);

        $data = GitlabProjectMapper::toArray($entity);

        $expected = [
            'id' => 1,
            'description' => 'A project',
            'name' => 'project-name',
            'name_with_namespace' => 'group / project-name',
            'path' => 'project-path',
            'path_with_namespace' => 'group/project-path',
            'created_at' => '2023-01-01',
            'default_branch' => 'main',
            'web_url' => 'http://localhost/project',
            'archived' => false,
        ];

        $this->assertEquals($expected, $data);
    }
}
