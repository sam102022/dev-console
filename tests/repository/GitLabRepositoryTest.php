<?php
declare(strict_types=1);

namespace App\tests\repository;

use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\repository\GitLabRepository;
use App\service\FileService;
use App\tests\fixtures\GitlabProjectEntityFixtures;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class GitLabRepositoryTest extends TestCase
{
    private FileService $fileService;
    private GitLabRepository $repository;

    protected function setUp(): void
    {
        $this->fileService = $this->createMock(FileService::class);

        $loggerFactory = $this->createMock(LoggerFactory::class);
        $loggerFactory->method('get')->willReturn($this->createMock(Logger::class));

        $this->repository = new GitLabRepository($loggerFactory);

        // Inject the mocked FileService
        $reflection = new ReflectionClass($this->repository);
        $property = $reflection->getProperty('fileService');
        $property->setAccessible(true);
        $property->setValue($this->repository, $this->fileService);
    }

    /**
     * @throws TechnicalException
     */
    public function testGetProjectsWhenCacheExists(): void
    {
        $projects = [['id' => 1, 'description' => 'New Project', 'name' => 'Project From Cache', 'name_with_namespace' => 'name-with-namespace', 'path' => 'path', 'path_with_namespace' => 'path-with-namespace', 'created_at' => '2023-01-01', 'default_branch' => 'main', 'web_url' => 'http://url', 'archived' => false]];
        $expectedProjects = [GitlabProjectEntityFixtures::getGitlabProjectEntityFromCache()];
        $this->fileService->method('isFileExists')->with(GitLabRepository::FILE_GITLAB_PROJECTS)->willReturn(true);
        $this->fileService->method('read')->with(GitLabRepository::FILE_GITLAB_PROJECTS)->willReturn($projects);

        $result = $this->repository->findAll();

        $this->assertEquals($expectedProjects, $result);
    }

    /**
     * @throws TechnicalException
     */
    public function testGetProjectsWhenCacheDoesNotExist(): void
    {
        $this->fileService->method('isFileExists')->with(GitLabRepository::FILE_GITLAB_PROJECTS)->willReturn(false);
        $this->fileService->expects($this->never())->method('read');

        $result = $this->repository->findAll();

        $this->assertNull($result);
    }

    public function testSaveProjects(): void
    {
        $expectedProjects = [['id' => 1, 'description' => 'New Project', 'name' => 'New Project', 'name_with_namespace' => 'name-with-namespace', 'path' => 'path', 'path_with_namespace' => 'path-with-namespace', 'created_at' => '2023-01-01', 'default_branch' => 'main', 'web_url' => 'http://url', 'archived' => false]];
        $projects = [GitlabProjectEntityFixtures::getGitlabProjectEntity()];
        $this->fileService->expects($this->once())
            ->method('save')
            ->with($expectedProjects, GitLabRepository::FILE_GITLAB_PROJECTS);

        $this->repository->updateAll($projects);
    }

    public function testPurgeAll(): void
    {
        $this->fileService->expects($this->once())
            ->method('delete')
            ->with($this->logicalOr(GitLabRepository::FILE_GITLAB_PROJECTS));

        $this->repository->purgeAll();
    }
}
