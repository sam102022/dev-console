<?php
declare(strict_types=1);

namespace App\tests\service;

use App\client\GitLabClient;
use App\client\NewRelicClient;
use App\exception\TechnicalException;
use App\parser\ChartParser;
use App\parser\MavenParser;
use App\repository\GitLabRepository;
use App\repository\mapper\ProjectMapper;
use App\repository\ProjectRepository;
use App\service\GitlabService;
use App\service\NewRelicService;
use App\service\RundeckService;
use App\tests\fixtures\GitlabProjectEntityFixtures;
use App\tests\fixtures\GitlabProjectFixtures;
use App\tests\fixtures\ProjectEntityFixtures;
use DateMalformedStringException;
use GuzzleHttp\Exception\GuzzleException;

class GitlabServiceTest extends AbstractServiceCase
{
    private GitLabClient $client;
    private MavenParser $mavenParser;
    private ChartParser $chartParser;
    private RundeckService $rundeckService;
    private GitLabRepository $gitLabRepository;
    private ProjectRepository $projectRepository;
    private NewRelicClient $newRelicClient;
    private GitlabService $service;

    final protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(GitLabClient::class);
        $this->mavenParser = $this->createMock(MavenParser::class);
        $this->chartParser = $this->createMock(ChartParser::class);
        $this->rundeckService = $this->createMock(RundeckService::class);
        $this->gitLabRepository = $this->createMock(GitLabRepository::class);
        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->newRelicClient = $this->createMock(NewRelicClient::class);

        $this->service = new GitlabService(
            $this->client,
            $this->mavenParser,
            $this->chartParser,
            $this->rundeckService,
            $this->gitLabRepository,
            $this->projectRepository,
            $this->newRelicClient,
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
        $gitlabProjects = [];
        $expectedProjects = [];

        $this->gitLabRepository->method('findAll')->willReturn(null)->willReturn($gitlabProjects);
        $this->client->expects($this->once())->method('getAllProjects')->with('group/path')->willReturn($projects);
        $this->gitLabRepository->expects($this->once())->method('updateAll');

        $result = $this->service->getProjects('group/path');
        $this->assertEquals($expectedProjects, $result);
    }

    /**
     * @throws TechnicalException
     */
    final public function testGetProjectsFromApiWhenCacheIsNotEmpty(): void
    {
        $projects = [['id' => 1, 'description' => 'New Project', 'name' => 'New Project', 'name_with_namespace' => 'name-with-namespace', 'path' => 'path', 'path_with_namespace' => 'path-with-namespace', 'created_at' => '2023-01-01', 'default_branch' => 'main', 'web_url' => 'http://url']];
        $gitlabProjects = [GitlabProjectEntityFixtures::getGitlabProjectEntityA()];
        $expectedProjects = [GitlabProjectFixtures::getGitlabProjectA()];
        $chartContent = "dependencies:\n  - name: mdm-workload\n    version: 1.5.0";

        $this->gitLabRepository->method('findAll')->willReturn(null)->willReturn($gitlabProjects);
        $this->client->expects($this->never())->method('getAllProjects')->with('group/path')->willReturn($projects);
        $this->chartParser->expects($this->never())->method('parseChartYaml')->with($chartContent)->willReturn('1.5.0');
        $this->gitLabRepository->expects($this->never())->method('updateAll');

        $result = $this->service->getProjects('group/path');
        $this->assertEquals($expectedProjects, $result);
    }

    final public function testGetProjectsFromCache(): void
    {
        $projectEntities = [GitlabProjectEntityFixtures::getGitlabProjectEntityA()];
        $expectedProjects = [GitlabProjectFixtures::getGitlabProjectA()];

        $this->gitLabRepository->method('findAll')->willReturn($projectEntities);
        $this->client->expects($this->never())->method('getAllProjects');

        $result = $this->service->getProjects('group/path');
        $this->assertEquals($expectedProjects, $result);
    }

    final public function testScanFromApiWhenCacheIsEmpty(): void
    {
        $this->projectRepository->method('findAll')->willThrowException(new TechnicalException("Le cache des projets Java est vide"));

        $projectEntities = [
            GitlabProjectEntityFixtures::getGitlabProjectEntityA(),
            GitlabProjectEntityFixtures::getGitlabProjectEntityB(),
            GitlabProjectEntityFixtures::getGitlabProjectEntityExcluded(),
        ];

        $this->gitLabRepository->method('findAll')->willReturn($projectEntities);

        $this->client->method('getFile')
            ->willReturnMap([
                [1, 'pom.xml', true, 'main', '<pom1/>'],
                [1, 'chart/Chart.yaml', true, 'main', 'content'],
                [2, 'pom.xml', true, 'main', '<pom2/>'],
                [2, 'chart/Chart.yaml', true, 'main', null],
            ]);

        $this->mavenParser->method('parsePomXml')
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
     * @throws TechnicalException|DateMalformedStringException
     */
    final public function testScanFromCache(): void
    {
        $projectEntities = [];
        $projectEntity = ProjectEntityFixtures::getProjectAEntity();
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
        $projectEntity = ProjectEntityFixtures::getProjectBEntity();
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
            GitlabProjectEntityFixtures::getGitlabProjectEntityA(),
            GitlabProjectEntityFixtures::getGitlabProjectEntityB(),
        ];
        $this->gitLabRepository->method('findAll')->willReturn($projectEntities);

        $this->client->method('getFile')->willReturn(null);

        $projectEntity = ProjectEntityFixtures::getProjectBEntity();
        $expectedProject = ProjectMapper::fromEntity($projectEntity);

        $this->projectRepository->expects($this->once())->method('updateAll');

        $result = $this->service->getProjectByCode('project-b');
        $this->assertEquals($expectedProject, $result);
    }
}