<?php
declare(strict_types=1);

namespace App\tests\service;

use App\client\GitLabClient;
use App\config\AppConfig;
use App\factory\LoggerFactory;
use App\model\ParamConfig;
use App\parser\GradleParser;
use App\parser\MavenParser;
use App\service\FileService;
use App\service\GitlabService;
use PHPUnit\Framework\TestCase;

class GitlabServiceTest extends AbstractServiceCase
{
    private GitLabClient $client;
    private MavenParser $mavenParser;
    private GradleParser $gradleParser;
    private FileService $fileService;
    private GitlabService $service;

    protected function setUp(): void
    {
        $this->client = $this->createMock(GitLabClient::class);
        $this->mavenParser = $this->createMock(MavenParser::class);
        $this->gradleParser = $this->createMock(GradleParser::class);
        $this->fileService = $this->createMock(FileService::class);

        $paramConfig = $this->createMock(ParamConfig::class);
        $paramConfig->method('getExcludeProjects')->willReturn(['excluded-project']);
        $paramConfig->method('getGitlabPathGroupDefault')->willReturn('default/group');

        $this->service = new GitlabService(
            $this->client,
            $this->mavenParser,
            $this->gradleParser,
            $this->fileService,
            self::$appConfig,
            self::$loggerFactory
        );
    }

    public function testPurgeCache(): void
    {
        $this->fileService->expects($this->exactly(2))
            ->method('delete')
            ->with($this->logicalOr('gitlabProjects.json', 'javaProjects.json'));

        $this->service->purgeCache();
    }

    public function testGetProjectsFromApiWhenCacheIsEmpty(): void
    {
        $this->fileService->method('isFileExists')->with('gitlabProjects.json')->willReturn(false);
        
        $projects = [['id' => 1, 'name' => 'Project 1']];
        $this->client->expects($this->once())->method('getAllProjects')->with('group/path')->willReturn($projects);
        $this->fileService->expects($this->once())->method('save')->with($projects, 'gitlabProjects.json');

        $result = $this->service->getProjects('group/path');
        $this->assertEquals($projects, $result);
    }

    public function testGetProjectsFromCache(): void
    {
        $this->fileService->method('isFileExists')->with('gitlabProjects.json')->willReturn(true);
        
        $projects = [['id' => 1, 'name' => 'Project 1 from cache']];
        $this->fileService->expects($this->once())->method('read')->with('gitlabProjects.json')->willReturn($projects);
        $this->client->expects($this->never())->method('getAllProjects');

        $result = $this->service->getProjects('group/path');
        $this->assertEquals($projects, $result);
    }

    public function testScanFromApiWhenCacheIsEmpty(): void
    {
        $this->fileService->method('isFileExists')->willReturn(false);

        $projects = [
            ['id' => 1, 'name' => 'project-a', 'path_with_namespace' => 'a/b/c/d', 'name_with_namespace' => 'a / b / c / d'],
            ['id' => 2, 'name' => 'project-b', 'path_with_namespace' => 'a/b/e/f', 'name_with_namespace' => 'a / b / e / f'],
            ['id' => 3, 'name' => 'excluded-project', 'path_with_namespace' => 'a/b/g/h', 'name_with_namespace' => 'a / b / g / h'],
        ];
        $this->client->method('getAllProjects')->willReturn($projects);

        $this->client->method('getFile')
            ->willReturnMap([
                [1, 'pom.xml', true, 'main', '<pom1/>'],
                [1, 'chart/values.yaml', true, 'main', 'content'], // cloudGCP = true
                [2, 'pom.xml', true, 'main', '<pom2/>'],
                [2, 'chart/values.yaml', true, 'main', null], // cloudGCP = false
            ]);

        $this->mavenParser->method('parse')
            ->willReturnMap([
                ['<pom1/>', ['springBoot' => '2.0', 'java' => '11']],
                ['<pom2/>', ['springBoot' => '1.5', 'java' => '8']],
            ]);

        $this->fileService->method('save');

        $results = $this->service->scan();

        $this->assertCount(2, $results);
        $this->assertEquals('project-b', $results[1]['name']);
        $this->assertEquals('project-a', $results[0]['name']); // Sorted by subsf, then name
        $this->assertTrue($results[0]['cloudGCP']);
        $this->assertFalse($results[1]['cloudGCP']);
        $this->assertEquals('8', $results[1]['java']);
    }

    public function testScanFromCache(): void
    {
        $this->fileService->method('isFileExists')->with('javaProjects.json')->willReturn(true);
        $cachedData = [['name' => 'cached-project']];
        $this->fileService->expects($this->once())->method('read')->with('javaProjects.json')->willReturn($cachedData);
        $this->client->expects($this->never())->method('getAllProjects');

        $results = $this->service->scan();
        $this->assertEquals($cachedData, $results);
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

    public function testGetProjectByCode(): void
    {
        $scanResults = [
            ['name' => 'project-a', 'data' => '...'],
            ['name' => 'project-b', 'data' => '...'],
        ];
        // This is tricky because scan() calls getProjects which might be cached.
        // We can mock the internal call to scan() but that's testing implementation.
        // Let's just mock the file read from scan().
        $this->fileService->method('isFileExists')->with('javaProjects.json')->willReturn(true);
        $this->fileService->method('read')->with('javaProjects.json')->willReturn($scanResults);

        $result = $this->service->getProjectByCode('project-b');
        $this->assertEquals($scanResults[1], $result);

        $resultNotFound = $this->service->getProjectByCode('project-c');
        $this->assertNull($resultNotFound);
    }
}
