<?php
declare(strict_types=1);

namespace App\tests\service;

use App\client\GitLabClient;
use App\exception\TechnicalException;
use App\model\GitlabProject;
use App\parser\GradleParser;
use App\parser\MavenParser;
use App\repository\GitLabRepository;
use App\repository\mapper\ProjectMapper;
use App\repository\model\GitlabProjectEntity;
use App\repository\model\ProjectEntity;
use App\repository\ProjectRepository;
use App\service\GitlabService;
use GuzzleHttp\Exception\GuzzleException;

class GitlabServiceTest extends AbstractServiceCase
{
    private GitLabClient $client;
    private MavenParser $mavenParser;
    private GradleParser $gradleParser;
    private GitLabRepository $gitLabRepository;
    private ProjectRepository $projectRepository;
    private GitlabService $service;

    final protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(GitLabClient::class);
        $this->mavenParser = $this->createMock(MavenParser::class);
        $this->gradleParser = $this->createMock(GradleParser::class);
        $this->gitLabRepository = $this->createMock(GitLabRepository::class);
        $this->projectRepository = $this->createMock(ProjectRepository::class);

        $this->service = new GitlabService(
            $this->client,
            $this->mavenParser,
            $this->gradleParser,
            $this->gitLabRepository,
            $this->projectRepository,
            self::$appConfig,
            self::$loggerFactory
        );
    }

    final public function testPurgeCache(): void
    {
        $this->gitLabRepository->expects($this->once())->method('purgeAll');
        $this->projectRepository->expects($this->once())->method('purgeAll');
        $this->service->purgeCache();
    }

    final public function testGetProjectsFromApiWhenCacheIsEmpty(): void
    {
        $projects = [['id' => 1, 'description' => 'New Project', 'name' => 'New Project', 'name_with_namespace' => 'name-with-namespace', 'path' => 'path', 'path_with_namespace' => 'path-with-namespace', 'created_at' => '2023-01-01', 'default_branch' => 'main', 'web_url' => 'http://url']];
        $gitlabProjects = [GitlabProjectEntity::build(1, 'New Project', 'New Project', 'name-with-namespace', 'path', 'path-with-namespace', 'main', '2023-01-01', 'http://url', false)];
        $expectedProjects = [GitlabProject::build(1, 'New Project', 'New Project', 'name-with-namespace', 'path', 'path-with-namespace', 'main', '2023-01-01', 'http://url', false)];

        $this->gitLabRepository->method('findAll')->willReturn(null)->willReturn($gitlabProjects);
        $this->client->expects($this->never())->method('getAllProjects')->with('group/path')->willReturn($projects);
        $this->gitLabRepository->expects($this->never())->method('updateAll')->with($gitlabProjects);

        $result = $this->service->getProjects('group/path');
        $this->assertEquals($expectedProjects, $result);
    }

    final public function testGetProjectsFromCache(): void
    {
        $projectEntities = [GitlabProjectEntity::build(1, 'New Project', 'Project 1 from cache', 'name-with-namespace', 'path', 'path-with-namespace', 'main', '2023-01-01', 'http://url', false)];
        $expectedProjects = [GitlabProject::build(1, 'New Project', 'Project 1 from cache', 'name-with-namespace', 'path', 'path-with-namespace', 'main', '2023-01-01', 'http://url', false)];

        $this->gitLabRepository->method('findAll')->willReturn($projectEntities);
        $this->client->expects($this->never())->method('getAllProjects');

        $result = $this->service->getProjects('group/path');
        $this->assertEquals($expectedProjects, $result);
    }

    final public function testScanFromApiWhenCacheIsEmpty(): void
    {
        $this->projectRepository->method('findAll')->willThrowException(new TechnicalException("Le cache des projets Java est vide"));

        $projectEntities = [
            GitlabProjectEntity::build(1, 'New Project', 'project-a', 'a / b / c / d', 'a/b/c/d', 'a/b/c/d', 'main', '2023-01-01', 'http://url/a', false),
            GitlabProjectEntity::build(2, 'New Project', 'project-b', 'a / b / e / f', 'a/b/e/f', 'a/b/e/f', 'main', '2023-01-01', 'http://url/b', false),
            GitlabProjectEntity::build(3, 'New Project', 'excluded-project', 'a / b / g / h', 'a/b/g/h', 'a/b/g/h', 'main', '2023-01-01', 'http://url/c', false)
        ];

        $this->gitLabRepository->method('findAll')->willReturn($projectEntities);

        $this->client->method('getFile')
            ->willReturnMap([
                [1, 'pom.xml', true, 'main', '<pom1/>'],
                [1, 'chart/values.yaml', true, 'main', 'content'],
                [2, 'pom.xml', true, 'main', '<pom2/>'],
                [2, 'chart/values.yaml', true, 'main', null],
            ]);

        $this->mavenParser->method('parse')
            ->willReturnMap([
                ['<pom1/>', ['springBoot' => '2.0', 'java' => '11']],
                ['<pom2/>', ['springBoot' => '1.5', 'java' => '8']],
            ]);

        $this->projectRepository->expects($this->once())->method('updateAll');

        $results = $this->service->scan();

        $this->assertCount(3, $results);
        $this->assertEquals('project-a', $results[0]->getName());
        $this->assertEquals('project-b', $results[1]->getName());
        $this->assertTrue($results[0]->isCloudGCP());
        $this->assertFalse($results[1]->isCloudGCP());
        $this->assertEquals('11', $results[0]->getJava());
        $this->assertEquals('8', $results[1]->getJava());
    }

    /**
     * @throws GuzzleException
     * @throws TechnicalException
     */
    final public function testScanFromCache(): void
    {
        $projectEntities = [];
        $projectEntity = ProjectEntity::build('project-a', 'serviceName',
            'sf', 'sfName', 'subsf', true,
            '2.7.0', '17', 'java', 'subscriptionName', 'http://url', false);
        $project = ProjectMapper::fromEntity($projectEntity);
        $projectEntities[] = $projectEntity;

        $this->projectRepository->method('findAll')->willReturn($projectEntities);
        $this->client->expects($this->never())->method('getAllProjects');

        $results = $this->service->scan();
        $this->assertEquals([$project], $results);
    }

    /**
     * @throws GuzzleException
     */
    final public function testGetTree(): void
    {
        $expected = [['type' => 'tree', 'name' => 'src']];
        $this->client->expects($this->once())->method('listRepositoryTree')->with(123, 'src')->willReturn($expected);
        $result = $this->service->getTree(123, 'src');
        $this->assertEquals($expected, $result);
    }

    final public function testGetFile(): void
    {
        $expectedContent = 'file content';
        $this->client->expects($this->once())->method('getFile')->with(123, 'pom.xml', true, 'master')->willReturn($expectedContent);
        $result = $this->service->getFile(123, 'pom.xml');
        $this->assertEquals(['content' => $expectedContent], $result);
    }

    final public function testGetProjectByCodeFound(): void
    {
        $projectEntity = ProjectEntity::build('project-b', 'serviceName',
            'sf', 'sfName', 'subsf', true,
            '2.7.0', '17', 'java', 'subscriptionName', 'http://url', false);
        $expectedProject = ProjectMapper::fromEntity($projectEntity);

        $this->projectRepository->method('findByCode')->with('project-b')->willReturn($projectEntity);

        $result = $this->service->getProjectByCode('project-b');
        $this->assertEquals($expectedProject, $result);
    }

    final public function testGetProjectByCodeNotFound(): void
    {
        $this->projectRepository->method('findByCode')->with('project-c')
            ->willReturn(null);

        $result = $this->service->getProjectByCode('project-c');
        $this->assertNull($result);
    }

    final public function testGetProjectByCodeInitializesCache(): void
    {
        $this->projectRepository->method('findByCode')->willThrowException(new TechnicalException('Cache is empty'));

        $projectEntities = [
            GitlabProjectEntity::build(1, 'New Project', 'project-a', 'a / b / c / d', 'a/b/c/d', 'a/b/c/d', 'main', '2023-01-01', 'http://url/a', false),
            GitlabProjectEntity::build(2, 'New Project', 'project-b', 'a / b / e / f', 'a/b/e/f', 'a/b/e/f', 'main', '2023-01-01', 'http://url/b', false),
        ];
        $this->gitLabRepository->method('findAll')->willReturn($projectEntities);

        $this->client->method('getFile')->willReturn(null);

        $projectEntity = ProjectEntity::build('project-b', null,
            'e', ' e ', 'f', false,
            null, null, '', null, 'http://url/b', false);
        $expectedProject = ProjectMapper::fromEntity($projectEntity);

        $this->projectRepository->expects($this->once())->method('updateAll');

        $result = $this->service->getProjectByCode('project-b');
        $this->assertEquals($expectedProject, $result);
    }
}