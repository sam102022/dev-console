<?php
declare(strict_types=1);

namespace App\tests\repository;

use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\repository\mapper\ProjectMapper;
use App\repository\model\ProjectEntity;
use App\repository\ProjectRepository;
use App\service\FileService;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ProjectRepositoryTest extends TestCase
{
    private FileService $fileService;
    private ProjectRepository $repository;

    protected function setUp(): void
    {
        $this->fileService = $this->createMock(FileService::class);

        $loggerFactory = $this->createMock(LoggerFactory::class);
        $loggerFactory->method('get')->willReturn($this->createMock(Logger::class));

        $this->repository = new ProjectRepository($loggerFactory);

        // Inject the mocked FileService
        $reflection = new ReflectionClass($this->repository);
        $property = $reflection->getProperty('fileService');
        $property->setAccessible(true);
        $property->setValue($this->repository, $this->fileService);
    }

    final public function testFindAllThrowsExceptionWhenCacheIsEmpty(): void
    {
        $this->fileService->method('isFileExists')->willReturn(false);
        $this->expectException(TechnicalException::class);
        $this->repository->findAll();
    }

    /**
     * @throws TechnicalException
     */
    final public function testFindAllReturnsProjectObjects(): void
    {
        $projectsData = [
            ['name' => 'project-a', 'serviceName' => null, 'domain' => 'sf-a', 'domainName' => 'SF A', 'subsf' => 'sub-a', 'java' => '11', 'archived' => false, 'urlsRundeck' => [], 'deploymentGcpUrl' => []],
            ['name' => 'project-b', 'serviceName' => null, 'domain' => 'sf-b', 'domainName' => 'SF B', 'subsf' => 'sub-b', 'java' => '17', 'archived' => true, 'urlsRundeck' => [], 'deploymentGcpUrl' => []]
        ];
        $this->fileService->method('isFileExists')->willReturn(true);
        $this->fileService->method('read')->willReturn($projectsData);

        $result = $this->repository->findAll();

        $this->assertCount(2, $result);
        $this->assertInstanceOf(ProjectEntity::class, $result[0]);
        $this->assertEquals('project-a', $result[0]->getName());
        $this->assertEquals('17', $result[1]->getJavaVersion());
        $this->assertFalse($result[0]->isArchived());
        $this->assertTrue($result[1]->isArchived());
    }

    final public function testFindByCodeThrowsExceptionWhenCacheIsEmpty(): void
    {
        $this->fileService->method('isFileExists')->willReturn(false);
        $this->expectException(TechnicalException::class);
        $this->repository->findByCode('any-code');
    }

    /**
     * @throws TechnicalException
     */
    final public function testFindByCodeReturnsProjectWhenFound(): void
    {
        $projectsData = [
            ['name' => 'project-a', 'serviceName' => null, 'domain' => 'sf-a', 'domainName' => 'SF A', 'subsf' => 'sub-a', 'urlsRundeck' => [], 'deploymentGcpUrl' => []],
            ['name' => 'project-b', 'serviceName' => null, 'domain' => 'sf-b', 'domainName' => 'SF B', 'subsf' => 'sub-b', 'urlsRundeck' => [], 'deploymentGcpUrl' => []]
        ];
        $this->fileService->method('isFileExists')->willReturn(true);
        $this->fileService->method('read')->willReturn($projectsData);

        $result = $this->repository->findByCode('project-b');

        $this->assertInstanceOf(ProjectEntity::class, $result);
        $this->assertEquals('project-b', $result->getName());
        $this->assertEquals('sf-b', $result->getDomain());
    }

    /**
     * @throws TechnicalException
     */
    final public function testFindByCodeReturnsNullWhenNotFound(): void
    {
        $projectsData = [['name' => 'project-a', 'serviceName' => null, 'domain' => 'sf-a', 'domainName' => 'SF A', 'subsf' => 'sub-a', 'urlsRundeck' => [], 'deploymentGcpUrl' => []]];

        $this->fileService->method('isFileExists')->willReturn(true);
        $this->fileService->method('read')->willReturn($projectsData);

        $result = $this->repository->findByCode('project-c');
        $this->assertNull($result);
    }

    final public function testUpdateAll(): void
    {
        $projectEntity1 = ProjectMapper::projectEntityFromArray(['name' => 'p1', 'domain' => 's', 'domainName' => 'sn', 'subsf' => 'ss', 'urlsRundeck' => [], 'deploymentGcpUrl' => []]);
        $projectEntity2 = ProjectMapper::projectEntityFromArray(['name' => 'p2', 'domain' => 's', 'domainName' => 'sn', 'subsf' => 'ss', 'urlsRundeck' => [], 'deploymentGcpUrl' => []]);
        $projectEntities = [$projectEntity1, $projectEntity2];

        $this->fileService->expects($this->once())
            ->method('save');

        $this->repository->updateAll($projectEntities);
    }

    final public function testPurgeAll(): void
    {
        $this->fileService->expects($this->once())
            ->method('delete')
            ->with(ProjectRepository::FILE_JAVA_PROJECTS);

        $this->repository->purgeAll();
    }
}
