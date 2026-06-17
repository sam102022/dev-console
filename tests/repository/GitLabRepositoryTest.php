<?php
declare(strict_types=1);

namespace App\tests\repository;

use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\repository\GitLabRepository;
use App\service\RepositoryService;
use App\tests\fixtures\GitlabProjectEntityFixtures;
use Monolog\Logger;
use ReflectionClass;

class GitLabRepositoryTest extends AbstractRepositoryCase
{
    private RepositoryService $repositoryServiceMock;
    private GitLabRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repositoryServiceMock = $this->createMock(RepositoryService::class);

        $loggerFactory = $this->createMock(LoggerFactory::class);
        $loggerFactory->method('get')->willReturn($this->createMock(Logger::class));

        $this->repository = new GitLabRepository(self::$appConfig, $loggerFactory);

        // Inject the mocked FileService
        $reflection = new ReflectionClass($this->repository);
        $property = $reflection->getProperty('repositoryService');
        $property->setAccessible(true);
        $property->setValue($this->repository, $this->repositoryServiceMock);
    }

    /**
     * @throws TechnicalException
     */
    public function testGetProjectsWhenCacheExists(): void
    {
        $projects = [['id' => 1, 'description' => 'New Project', 'name' => 'Project From Cache', 'name_with_namespace' => 'name-with-namespace', 'path' => 'path', 'path_with_namespace' => 'path-with-namespace', 'created_at' => '2023-01-01', 'default_branch' => 'main', 'web_url' => 'http://url', 'archived' => false]];
        $expectedProjects = [GitlabProjectEntityFixtures::getGitlabProjectEntityFromCache()];
        $this->repositoryServiceMock->method('isFileExists')->with(GitLabRepository::FILE_GITLAB_PROJECTS)->willReturn(true);
        $this->repositoryServiceMock->method('read')->with(GitLabRepository::FILE_GITLAB_PROJECTS)->willReturn($projects);

        $result = $this->repository->findAll();

        $this->assertEquals($expectedProjects, $result);
    }

    /**
     * @throws TechnicalException
     */
    public function testGetProjectsWhenCacheDoesNotExist(): void
    {
        $this->repositoryServiceMock->method('isFileExists')->with(GitLabRepository::FILE_GITLAB_PROJECTS)->willReturn(false);
        $this->repositoryServiceMock->expects($this->never())->method('read');

        $result = $this->repository->findAll();

        $this->assertNull($result);
    }

    public function testSaveProjects(): void
    {
        $expectedProjects = [GitlabProjectEntityFixtures::getGitlabProjectData()];
        $projects = [GitlabProjectEntityFixtures::getGitlabProjectEntity()];
        $this->repositoryServiceMock->expects($this->once())
            ->method('save')
            ->with($expectedProjects, GitLabRepository::FILE_GITLAB_PROJECTS);

        $this->repository->updateAll($projects);
    }

    public function testPurgeAll(): void
    {
        $this->repositoryServiceMock->expects($this->once())
            ->method('delete')
            ->with($this->logicalOr(GitLabRepository::FILE_GITLAB_PROJECTS));

        $this->repository->purgeAll();
    }
}
