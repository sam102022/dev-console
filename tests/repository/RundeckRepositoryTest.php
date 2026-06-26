<?php
declare(strict_types=1);

namespace App\tests\repository;

use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\repository\RundeckRepository;
use App\service\RepositoryService;
use App\tests\AbstractTestCase;
use App\tests\fixtures\RundeckProjectEntityFixtures;
use Monolog\Logger;
use ReflectionClass;

class RundeckRepositoryTest extends AbstractTestCase
{
    private RepositoryService $repositoryServiceMock;
    private RundeckRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repositoryServiceMock = $this->createMock(RepositoryService::class);

        $loggerFactory = $this->createMock(LoggerFactory::class);
        $loggerFactory->method('get')->willReturn($this->createMock(Logger::class));

        $this->repository = new RundeckRepository(self::$appConfig, $loggerFactory);

        // Inject the mocked FileService
        $reflection = new ReflectionClass($this->repository);
        $property = $reflection->getProperty('repositoryService');
        $property->setAccessible(true);
        $property->setValue($this->repository, $this->repositoryServiceMock);
    }

    /**
     * @throws TechnicalException
     */
    final public function testGetProjectsWhenCacheExists(): void
    {
        $projects = [RundeckProjectEntityFixtures::getRundeckProjectData()];
        $expectedProjects = [RundeckProjectEntityFixtures::getRundeckProjectEntityFromCache()];
        $this->repositoryServiceMock->method('isFileExists')->with(RundeckRepository::FILE_RUNDECK_PROJECTS)->willReturn(true);
        $this->repositoryServiceMock->method('read')->with(RundeckRepository::FILE_RUNDECK_PROJECTS)->willReturn($projects);

        $result = $this->repository->findAll();

        $this->assertEquals($expectedProjects, $result);
    }

    /**
     * @throws TechnicalException
     */
    final public function testGetProjectsWhenCacheDoesNotExist(): void
    {
        $this->repositoryServiceMock->method('isFileExists')->with(RundeckRepository::FILE_RUNDECK_PROJECTS)->willReturn(false);
        $this->repositoryServiceMock->expects($this->never())->method('read');

        $result = $this->repository->findAll();

        $this->assertNull($result);
    }

    final public function testSaveProjects(): void
    {
        $expectedProjects = [RundeckProjectEntityFixtures::getRundeckProjectData()];
        $projects = [RundeckProjectEntityFixtures::getRundeckProjectEntity()];
        $this->repositoryServiceMock->expects($this->once())
            ->method('save')
            ->with($expectedProjects, RundeckRepository::FILE_RUNDECK_PROJECTS);

        $this->repository->updateAll($projects);
    }

    final public function testPurgeAll(): void
    {
        $this->repositoryServiceMock->expects($this->once())
            ->method('delete')
            ->with($this->logicalOr(RundeckRepository::FILE_RUNDECK_PROJECTS));

        $this->repository->purgeAll();
    }
}