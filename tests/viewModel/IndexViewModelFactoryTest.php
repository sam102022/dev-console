<?php
declare(strict_types=1);

namespace App\tests\viewModel;

use App\context\IndexContext;
use App\model\Project;
use App\tests\AbstractTestCase;
use App\viewModel\IndexViewModelFactory;

class IndexViewModelFactoryTest extends AbstractTestCase
{
    private IndexViewModelFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new IndexViewModelFactory(self::$twig, self::$appConfig);
    }

    public function testBuildIncludesTags(): void
    {
        $project = new Project();
        $project->setName('api-orders');
        $project->setDomain('pdv');
        $project->setDomainName('Point de Vente');
        $project->setSf('orders');
        $project->setCloudGCP(true);
        $project->setSpringBoot('3.2.0');
        $project->setJava('21');
        $project->setWebUrl('http://url');
        $project->setArchived(false);
        $project->setTags(['paiement', 'checkout']);

        $this->factory->setResults([$project]);

        $context = new IndexContext();
        $result = $this->factory->build($context, []);

        $this->assertArrayHasKey('results', $result);
        $this->assertCount(1, $result['results']);
        $this->assertEquals('api-orders', $result['results'][0]['name']);
        $this->assertArrayHasKey('tags', $result['results'][0]);
        $this->assertEquals(['paiement', 'checkout'], $result['results'][0]['tags']);
    }
}
