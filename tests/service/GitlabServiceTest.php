<?php
declare(strict_types=1);

namespace App\tests\service;

use App\client\GitLabClient;
use App\exception\TechnicalException;
use App\model\GitlabProject;
use App\model\Project;
use App\parser\GradleParser;
use App\parser\MavenParser;
use App\repository\GitLabRepository;
use App\repository\mapper\ProjectMapper;
use App\repository\model\GitlabProjectEntity;
use App\repository\model\ProjectEntity;
use App\repository\ProjectRepository;
use App\service\GitlabService;

class GitlabServiceTest extends AbstractServiceCase
{
    private GitLabClient $client;
    private MavenParser $mavenParser;
    private GradleParser $gradleParser;
    private GitLabRepository $gitLabRepository;
    private ProjectRepository $projectRepository;
    private GitlabService $service;

    protected function setUp(): void
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

    public function testPurgeCache(): void
    {
        $this->gitLabRepository->expects($this->once())->method('purgeAll');
        $this->projectRepository->expects($this->once())->method('purgeAll');
        $this->service->purgeCache();
    }

    public function testGetProjectsFromApiWhenCacheIsEmpty(): void
    {
        $projects = [['id' => 1, 'description' => 'New Project', 'name' => 'New Project', 'name_with_namespace' => 'name-with-namespace', 'path' => 'path', 'path_with_namespace' => 'path-with-namespace', 'created_at' => '2023-01-01', 'default_branch' => 'main', 'web_url' => 'http://url']];
        $gitlabProjects = [GitlabProjectEntity::build(1, 'New Project', 'New Project', 'name-with-namespace', 'path', 'path-with-namespace', 'main', '2023-01-01', 'http://url')];
        $expectedProjects = [GitlabProject::build(1, 'New Project', 'New Project', 'name-with-namespace', 'path', 'path-with-namespace', 'main', '2023-01-01', 'http://url')];

        $this->gitLabRepository->method('findAll')->willReturn(null)->willReturn($gitlabProjects);
        $this->client->expects($this->once())->method('getAllProjects')->with('group/path')->willReturn($projects);
        $this->gitLabRepository->expects($this->once())->method('updateAll')->with($gitlabProjects);

        $result = $this->service->getProjects('group/path');
        $this->assertEquals($expectedProjects, $result);
    }

    public function testGetProjectsFromCache(): void
    {
        $projectEntities = [GitlabProjectEntity::build(1, 'New Project', 'Project 1 from cache', 'name-with-namespace', 'path', 'path-with-namespace', 'main', '2023-01-01', 'http://url')];
        $expectedProjects = [GitlabProject::build(1, 'New Project', 'Project 1 from cache', 'name-with-namespace', 'path', 'path-with-namespace', 'main', '2023-01-01', 'http://url')];

        $this->gitLabRepository->method('findAll')->willReturn($projectEntities);
        $this->client->expects($this->never())->method('getAllProjects');

        $result = $this->service->getProjects('group/path');
        $this->assertEquals($expectedProjects, $result);
    }

    public function testScanFromApiWhenCacheIsEmpty(): void
    {
        $this->projectRepository->method('findAll')->willThrowException(new TechnicalException("Le cache des projets Java est vide"));

        $projects1 = [
            ['id' => 1, 'name' => 'project-a', 'path_with_namespace' => 'a/b/c/d', 'name_with_namespace' => 'a / b / c / d'],
            ['id' => 2, 'name' => 'project-b', 'path_with_namespace' => 'a/b/e/f', 'name_with_namespace' => 'a / b / e / f'],
            ['id' => 3, 'name' => 'excluded-project', 'path_with_namespace' => 'a/b/g/h', 'name_with_namespace' => 'a / b / g / h'],
        ];
        $projectEntities = [
            GitlabProjectEntity::build(1, 'New Project', 'project-a', 'http://url', 'a/b/c/d', 'a / b / c / d', 'main', '2023-01-01', 'http://url'),
            GitlabProjectEntity::build(2, 'New Project', 'project-b', 'http://url', 'a/b/e/f', 'a / b / e / f', 'main', '2023-01-01', 'http://url'),
            GitlabProjectEntity::build(3, 'New Project', 'excluded-project', 'http://url', 'a/b/g/h', 'a / b / g / h', 'main', '2023-01-01', 'http://url')
        ];
        $projects = [
            GitlabProject::build(1, 'New Project', 'project-a', 'http://url', 'a/b/c/d', 'a / b / c / d', 'main', '2023-01-01', 'http://url'),
            GitlabProject::build(2, 'New Project', 'project-b', 'http://url', 'a/b/e/f', 'a / b / e / f', 'main', '2023-01-01', 'http://url'),
            GitlabProject::build(3, 'New Project', 'excluded-project', 'http://url', 'a/b/g/h', 'a / b / g / h', 'main', '2023-01-01', 'http://url')
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

        $this->assertCount(2, $results);
        $this->assertEquals('project-b', $results[1]->getName());
        $this->assertEquals('project-a', $results[0]->getName());
        $this->assertTrue($results[0]->isCloudGCP());
        $this->assertFalse($results[1]->isCloudGCP());
        $this->assertEquals('8', $results[1]->getJava());
    }

    public function testScanFromCache(): void
    {
        $projectEntities = [];
        $projectEntity = ProjectEntity::build('project-a', 'sf', 'sfName', 'subsf', true, '2.7.0', '17');
        $project = ProjectMapper::fromEntity($projectEntity);
        $projectEntities[] = $projectEntity;

        $this->projectRepository->method('findAll')->willReturn($projectEntities);
        $this->client->expects($this->never())->method('getAllProjects');

        $results = $this->service->scan();
        $this->assertEquals([$project], $results);
    }

    public function testGetTree(): void
    {
        $expected = [['type' => 'tree', 'name' => 'src']];
        $this->client->expects($this->once())->method('listRepositoryTree')->with(123, 'src')->willReturn($expected);
        $result = $this->service->getTree(123, 'src');
        $this->assertEquals($expected, $result);
    }

    public function testGetFile(): void
    {
        $expectedContent = 'file content';
        $this->client->expects($this->once())->method('getFile')->with(123, 'pom.xml', true, 'master')->willReturn($expectedContent);
        $result = $this->service->getFile(123, 'pom.xml');
        $this->assertEquals(['content' => $expectedContent], $result);
    }

    public function testGetProjectByCodeFound(): void
    {
        $projectEntity = ProjectEntity::build('project-b', 'sf', 'sfName', 'subsf', true, '2.7.0', '17');
        $expectedProject = ProjectMapper::fromEntity($projectEntity);
        
        $this->projectRepository->method('findByCode')->with('project-b')->willReturn($projectEntity);
        
        $result = $this->service->getProjectByCode('project-b');
        $this->assertEquals($expectedProject, $result);
    }

    public function testGetProjectByCodeNotFound(): void
    {
        $this->projectRepository->method('findByCode')->with('project-c')->willReturn(null);

        $result = $this->service->getProjectByCode('project-c');
        $this->assertNull($result);
    }
    
    public function testGetProjectByCodeInitializesCache(): void
    {
        $this->projectRepository->method('findByCode')->willThrowException(new TechnicalException('Cache is empty'));
        
        $projects1 = [
            ['id' => 1, 'name' => 'project-a', 'path_with_namespace' => 'a/b/c/d', 'name_with_namespace' => 'a / b / c / d'],
            ['id' => 2, 'name' => 'project-b', 'path_with_namespace' => 'a/b/e/f', 'name_with_namespace' => 'a / b / e / f'],
        ];
        $projectEntities = [
            GitlabProjectEntity::build(1, 'New Project', 'project-a', 'http://url', 'a/b/c/d', 'a / b / c / d', 'main', '2023-01-01', 'http://url'),
            GitlabProjectEntity::build(2, 'New Project', 'project-b', 'http://url', 'a/b/e/f', 'a / b / e / f', 'main', '2023-01-01', 'http://url'),
        ];
        $projects = [
            GitlabProject::build(1, 'New Project', 'project-a', 'http://url', 'a/b/c/d', 'a / b / c / d', 'main', '2023-01-01', 'http://url'),
            GitlabProject::build(2, 'New Project', 'project-b', 'http://url', 'a/b/e/f', 'a / b / e / f', 'main', '2023-01-01', 'http://url'),
        ];
        $this->gitLabRepository->method('findAll')->willReturn($projectEntities);

        //$this->client->method('getAllProjects')->willReturn($projects1); // Assume no poms for simplicity
        $this->client->method('getFile')->willReturn(null); // Assume no poms for simplicity

        $this->projectRepository->expects($this->once())->method('updateAll');
        $this->gitLabRepository->method('findAll')->willReturn($projectEntities);

        // The call should now find the project after initializing the cache
        $result = $this->service->getProjectByCode('project-b');
        $this->assertNull($result); // Null because scanPomXml will return null
    }
}
