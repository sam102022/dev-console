<?php
declare(strict_types=1);

namespace App\tests\repository\mapper;

use App\model\Project;
use App\repository\mapper\ProjectMapper;
use App\repository\model\ProjectEntity;
use App\tests\AbstractTestCase;

class ProjectMapperTest extends AbstractTestCase
{
    private function getSampleProjectData(): array
    {
        return [
            'name' => 'api-orders',
            'serviceName' => 'orders-service',
            'domain' => 'ORDER',
            'domainName' => 'Commandes',
            'sf' => 'sf-order',
            'cloudGCP' => true,
            'springBoot' => '3.2.0',
            'java' => '21',
            'techno' => 'java',
            'subscriptionName' => 'sub-orders',
            'mdmWorkloadVersion' => '1.5.0',
            'pathLivenessProbe' => '/actuator/health/liveness',
            'webUrl' => 'https://gitlab.com/mdm/api-orders',
            'archived' => false,
            'urlHealthCheck' => ['prod' => 'https://api-orders/health'],
            'urlActuatorInfo' => ['prod' => 'https://api-orders/info'],
            'urlLogs' => ['prod' => 'https://logs/orders'],
            'urlFronts' => [],
            'urlPubsubs' => [],
            'urlsRundeck' => [],
            'urlsDeploymentGcp' => [],
            'tags' => ['paiement', 'checkout']
        ];
    }

    final public function testProjectEntityFromArray(): void
    {
        $data = $this->getSampleProjectData();
        $entity = ProjectMapper::projectEntityFromArray($data);

        $this->assertEquals('api-orders', $entity->getName());
        $this->assertEquals(['paiement', 'checkout'], $entity->getTags());
    }

    final public function testProjectFromArray(): void
    {
        $data = $this->getSampleProjectData();
        $project = ProjectMapper::projectFromArray($data);

        $this->assertEquals('api-orders', $project->getName());
        $this->assertEquals(['paiement', 'checkout'], $project->getTags());
    }

    final public function testFromEntity(): void
    {
        $data = $this->getSampleProjectData();
        $entity = ProjectMapper::projectEntityFromArray($data);
        $project = ProjectMapper::fromEntity($entity);

        $this->assertEquals('api-orders', $project->getName());
        $this->assertEquals(['paiement', 'checkout'], $project->getTags());
    }

    final public function testToEntity(): void
    {
        $data = $this->getSampleProjectData();
        $project = ProjectMapper::projectFromArray($data);
        $entity = ProjectMapper::toEntity($project);

        $this->assertEquals('api-orders', $entity->getName());
        $this->assertEquals(['paiement', 'checkout'], $entity->getTags());
    }

    final public function testToArray(): void
    {
        $data = $this->getSampleProjectData();
        $entity = ProjectMapper::projectEntityFromArray($data);
        $result = ProjectMapper::toArray($entity);

        $this->assertEquals('api-orders', $result['name']);
        $this->assertArrayHasKey('tags', $result);
        $this->assertEquals(['paiement', 'checkout'], $result['tags']);
    }

    final public function testTagNormalization(): void
    {
        $project = new Project();
        $project->setTags([' Tag1 ', 'TAG2', 'tag1', '  ', 'tag3']);
        $this->assertEquals(['tag1', 'tag2', 'tag3'], $project->getTags());

        $entity = new ProjectEntity();
        $entity->setTags([' ALPHA ', 'Beta', 'alpha', '', 'GAMMA']);
        $this->assertEquals(['alpha', 'beta', 'gamma'], $entity->getTags());
    }
}
