<?php
declare(strict_types=1);

namespace App\tests\service;

use App\exception\TechnicalException;
use App\repository\RundeckRepository;
use App\service\RundeckService;
use App\tests\fixtures\RundeckProjectEntityFixtures;
use App\repository\mapper\RundeckProjectMapper;

class RundeckServiceTest extends AbstractServiceCase
{
    private RundeckRepository $rundeckRepository;
    private RundeckService $service;

    final protected function setUp(): void
    {
        parent::setUp();

        $this->rundeckRepository = $this->createMock(RundeckRepository::class);

        $this->service = new RundeckService(
            $this->rundeckRepository,
            self::$loggerFactory
        );
    }

    /**
     * @throws TechnicalException
     */
    final public function testFindAll(): void
    {
        $projectEntity = RundeckProjectEntityFixtures::getRundeckProjectEntity();
        $expectedProject = RundeckProjectMapper::toModel($projectEntity);

        $this->rundeckRepository->method('findAll')->willReturn([$projectEntity]);

        $results = $this->service->findAll();

        $this->assertCount(1, $results);
        $this->assertEquals($expectedProject, $results[0]);
    }

    /**
     * @throws TechnicalException
     */
    final public function testFindAllReturnsEmptyArrayWhenNull(): void
    {
        $this->rundeckRepository->method('findAll')->willReturn(null);

        $results = $this->service->findAll();

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /**
     * @throws TechnicalException
     */
    final public function testFindByProjectNameFound(): void
    {
        $projectEntity1 = RundeckProjectEntityFixtures::getRundeckProjectEntity();
        
        $projectEntity2 = RundeckProjectEntityFixtures::getRundeckProjectEntity();
        $projectEntity2->setProjectName('another_project');
        
        $expectedProject = RundeckProjectMapper::toModel($projectEntity1);

        $this->rundeckRepository->method('findAll')->willReturn([$projectEntity1, $projectEntity2]);

        $result = $this->service->findByProjectName('batch_click_and_collect_reports');
        $this->assertEquals($expectedProject, $result);
    }

    /**
     * @throws TechnicalException
     */
    final public function testFindByProjectNameNotFound(): void
    {
        $projectEntity = RundeckProjectEntityFixtures::getRundeckProjectEntity();
        $this->rundeckRepository->method('findAll')->willReturn([$projectEntity]);

        $result = $this->service->findByProjectName('non_existent_project');
        $this->assertNull($result);
    }
}
