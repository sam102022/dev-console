<?php
declare(strict_types=1);

namespace App\tests\viewModel;

use App\context\IndexContext;
use App\model\RundeckProject;
use App\service\GitlabService;
use App\tests\AbstractTestCase;
use App\viewModel\RundeckViewModelFactory;

class RundeckViewModelFactoryTest extends AbstractTestCase
{
    private RundeckViewModelFactory $factory;
    private GitlabService $gitlabService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gitlabService = $this->createMock(GitlabService::class);
        $this->factory = new RundeckViewModelFactory(self::$twig, self::$appConfig, $this->gitlabService);
    }

    public function testBuildIncludesTags(): void
    {
        $project = new RundeckProject();
        $project->setName('batch-orders');
        $project->setDomain('pdv');
        $project->setSf('buyers');
        $project->setTags(['batch', 'commandes']);

        $this->factory->setResults([$project]);

        $context = new IndexContext();
        $result = $this->factory->build($context, []);

        $this->assertArrayHasKey('results', $result);
        $this->assertCount(1, $result['results']);
        $this->assertEquals('batch-orders', $result['results'][0]['name']);
        $this->assertArrayHasKey('tags', $result['results'][0]);
        $this->assertEquals(['batch', 'commandes'], $result['results'][0]['tags']);
    }
}
