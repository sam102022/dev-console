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
use App\service\RundeckService;
use App\tests\AbstractTestCase;
use App\tests\fixtures\GitlabProjectEntityFixtures;
use App\tests\fixtures\GitlabProjectFixtures;
use App\tests\fixtures\ProjectEntityFixtures;
use App\model\EnumEnvironment;
use PHPUnit\Framework\Attributes\DataProvider;
use DateMalformedStringException;
use GuzzleHttp\Exception\GuzzleException;

class GitlabServiceTest extends AbstractTestCase
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

    final public function testScanHydratesTags(): void
    {
        $entity = ProjectEntityFixtures::getProjectAEntity();
        $this->projectRepository->method('findAll')->willReturn([$entity]);
        $this->projectRepository->method('getTagsByProject')->willReturn(['project-a' => ['paiement', 'orders']]);

        $projects = $this->service->scan();

        $this->assertNotNull($projects);
        $this->assertCount(1, $projects);
        $this->assertEquals(['paiement', 'orders'], $projects[0]->getTags());
    }

    final public function testGetProjectByCodeHydratesTags(): void
    {
        $entity = ProjectEntityFixtures::getProjectAEntity();
        $this->projectRepository->method('findByCode')->with('project-a')->willReturn($entity);
        $this->projectRepository->method('getTagsForProject')->with('project-a')->willReturn(['paiement']);

        $project = $this->service->getProjectByCode('project-a');

        $this->assertNotNull($project);
        $this->assertEquals(['paiement'], $project->getTags());
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

        $this->client->method('getFileAsync')
            ->willReturnCallback(function ($id, $path, $raw, $branch) {
                $content = null;
                if ($id === 1 && $path === 'pom.xml') $content = '<pom1/>';
                if ($id === 1 && $path === 'chart/Chart.yaml') $content = 'content';
                if ($id === 2 && $path === 'pom.xml') $content = '<pom2/>';

                return \GuzzleHttp\Promise\Create::promiseFor($content);
            });

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

        $this->client->method('getFileAsync')->willReturn(\GuzzleHttp\Promise\Create::promiseFor(null));

        $projectEntity = ProjectEntityFixtures::getProjectBEntity();
        $expectedProject = ProjectMapper::fromEntity($projectEntity);

        $this->projectRepository->expects($this->once())->method('updateAll');

        $result = $this->service->getProjectByCode('project-b');
        $this->assertEquals($expectedProject, $result);
    }

    /**
     * Provider for buildNewRelicUrl testing (parameterized)
     */
    public static function buildNewRelicUrlProvider(): array
    {
        return [
            'guid found' => [
                'projectName' => 'project-a',
                'env' => EnumEnvironment::DEV,
                'guid' => 'entity-guid-123',
                'expectedUrl' => 'https://one.newrelic.com/redirect/entity/entity-guid-123'
            ],
            'guid not found' => [
                'projectName' => 'project-a',
                'env' => EnumEnvironment::PROD,
                'guid' => null,
                'expectedUrl' => null
            ]
        ];
    }

    #[DataProvider('buildNewRelicUrlProvider')]
    final public function testBuildNewRelicUrl(string $projectName, EnumEnvironment $env, ?string $guid, ?string $expectedUrl): void
    {
        $projectMock = $this->createMock(\App\model\Project::class);
        $projectMock->method('getName')->willReturn($projectName);

        $this->newRelicClient->expects($this->once())
            ->method('getEntityGuid')
            ->with($projectName, $env)
            ->willReturn($guid);

        if ($guid !== null) {
            $this->newRelicClient->expects($this->once())
                ->method('generateEntityUrl')
                ->with($guid)
                ->willReturn($expectedUrl);
        } else {
            $this->newRelicClient->expects($this->never())
                ->method('generateEntityUrl');
        }

        $result = $this->service->buildNewRelicUrl($projectMock, $env);
        $this->assertEquals($expectedUrl, $result);
    }

    final public function testGetProjectsWithForceRefreshesFromGitlabApi(): void
    {
        $cachedEntities = [GitlabProjectEntityFixtures::getGitlabProjectEntityA()];
        $apiProjects = [
            [
                'id' => 10,
                'description' => 'Fresh Project',
                'name' => 'fresh-project',
                'name_with_namespace' => 'Core / Dev / Point de Vente / receipt / fresh-project',
                'path' => 'fresh-project',
                'path_with_namespace' => 'core/dev/pdv/receipt/fresh-project',
                'created_at' => '2026-01-01',
                'default_branch' => 'main',
                'web_url' => 'http://url/fresh',
                'archived' => false
            ]
        ];

        // Même si le cache existe, force=true doit interroger l'API GitLab et mettre à jour le repository
        $this->gitLabRepository->method('findAll')->willReturn($cachedEntities);
        $this->client->expects($this->once())->method('getAllProjects')->with('group/path')->willReturn($apiProjects);
        $this->gitLabRepository->expects($this->once())->method('updateAll');

        $result = $this->service->getProjects('group/path', true);
        $this->assertCount(1, $result);
        $this->assertEquals('fresh-project', $result[0]->getName());
    }

    final public function testScanWithForceUpdatesGitlabAndProjectRepositories(): void
    {
        $existingProjectEntities = [ProjectEntityFixtures::getProjectAEntity()];
        $this->projectRepository->method('findAll')->willReturn($existingProjectEntities);

        $apiProjects = [
            [
                'id' => 1,
                'description' => 'Project A',
                'name' => 'project-a',
                'name_with_namespace' => 'Core / Dev / Point de Vente / sf / project-a',
                'path' => 'project-a',
                'path_with_namespace' => 'core/dev/pdv/sf/project-a',
                'created_at' => '2026-01-01',
                'default_branch' => 'main',
                'web_url' => 'http://url/a',
                'archived' => false
            ]
        ];
        // Quand force=true, l'API GitLab doit être interrogée et les repositories mis à jour
        $this->client->expects($this->once())->method('getAllProjects')->willReturn($apiProjects);
        $this->gitLabRepository->expects($this->once())->method('updateAll');
        $this->projectRepository->expects($this->once())->method('updateAll');

        $this->client->method('getFileAsync')->willReturn(\GuzzleHttp\Promise\Create::promiseFor(null));

        $results = $this->service->scan(force: true);

        $this->assertNotNull($results);
        $this->assertCount(1, $results);
        $this->assertEquals('project-a', $results[0]->getName());
    }

    final public function testScanWithSelectiveSfOnlyScansMatchingProjectsAndMergesCache(): void
    {
        $existingOtherSf = ProjectEntityFixtures::getProjectBEntity(); // sf = 'f'
        $existingOldReceipt = ProjectEntityFixtures::getProjectAEntity(); // sf = 'sf'

        $this->projectRepository->method('findAll')->willReturn([$existingOtherSf, $existingOldReceipt]);

        $apiProjects = [
            [
                'id' => 10,
                'description' => 'Receipt Project',
                'name' => 'project-receipt',
                'name_with_namespace' => 'Core / Dev / Point de Vente / receipt / project-receipt',
                'path' => 'project-receipt',
                'path_with_namespace' => 'core/dev/pdv/receipt/project-receipt',
                'created_at' => '2026-01-01',
                'default_branch' => 'main',
                'web_url' => 'http://url/receipt',
                'archived' => false
            ],
            [
                'id' => 20,
                'description' => 'Other Project',
                'name' => 'project-other',
                'name_with_namespace' => 'Core / Dev / Point de Vente / f / project-other',
                'path' => 'project-other',
                'path_with_namespace' => 'core/dev/pdv/f/project-other',
                'created_at' => '2026-01-01',
                'default_branch' => 'main',
                'web_url' => 'http://url/other',
                'archived' => false
            ]
        ];

        $this->client->method('getAllProjects')->willReturn($apiProjects);

        // getFileAsync ne doit être appelé QUE pour le projet du SF 'receipt' (id 10), JAMAIS pour 'f' (id 20)
        $this->client->expects($this->atLeastOnce())
            ->method('getFileAsync')
            ->willReturnCallback(function($id, $path, $raw, $branch) {
                $this->assertEquals(10, $id, 'Seuls les fichiers des projets du SF sélectionné doivent être scannés');
                return \GuzzleHttp\Promise\Create::promiseFor(null);
            });

        // Le cache sauvegardé doit fusionner le projet préservé (project-b) et le nouveau projet scanné (project-receipt)
        $this->projectRepository->expects($this->once())
            ->method('updateAll')
            ->with($this->callback(function (array $entities) {
                $names = array_map(fn($e) => $e->getName(), $entities);
                return in_array('project-b', $names, true) && in_array('project-receipt', $names, true);
            }));

        $results = $this->service->scan(sf: 'receipt', force: true);

        $this->assertNotNull($results);
        $this->assertCount(1, $results);
        $this->assertEquals('project-receipt', $results[0]->getName());
        $this->assertEquals('receipt', $results[0]->getSf());
    }

    final public function testScanWithoutForceFiltersBySfFromCache(): void
    {
        $projectReceipt = ProjectEntityFixtures::getProjectAEntity(); // sf = 'sf'
        $projectOther = ProjectEntityFixtures::getProjectBEntity();   // sf = 'f'

        $this->projectRepository->method('findAll')->willReturn([$projectReceipt, $projectOther]);
        $this->client->expects($this->never())->method('getAllProjects');

        $results = $this->service->scan(sf: 'sf', force: false);

        $this->assertNotNull($results);
        $this->assertCount(1, $results);
        $this->assertEquals('project-a', $results[0]->getName());
    }
}