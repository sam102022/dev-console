<?php
declare(strict_types=1);

namespace App\tests\repository;

use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\model\EnumEnvironment;
use App\repository\model\NewRelicEntity;
use App\repository\NewRelicRepository;
use App\service\FileService;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class NewRelicRepositoryTest extends TestCase
{
    private FileService $fileServiceMock;
    private NewRelicRepository $repository;

    protected function setUp(): void
    {
        $this->fileServiceMock = $this->createMock(FileService::class);
        $loggerFactoryMock = $this->createMock(LoggerFactory::class);
        $loggerFactoryMock->method('get')->willReturn($this->createMock(Logger::class));

        $this->repository = new NewRelicRepository($loggerFactoryMock);

        // Inject the mocked FileService
        $reflection = new ReflectionClass($this->repository);
        $property = $reflection->getProperty('fileService');
        $property->setAccessible(true);
        $property->setValue($this->repository, $this->fileServiceMock);
    }

    /**
     * @throws TechnicalException
     */
    final public function testFindReturnsNullWhenCacheFileDoesNotExist(): void
    {
        // Arrange
        $this->fileServiceMock->method('isFileExists')->willReturn(false);

        // Act
        $result = $this->repository->find('any-project', EnumEnvironment::PROD);

        // Assert
        $this->assertNull($result);
    }

    /**
     * @throws TechnicalException
     */
    #[DataProvider('findDataProvider')]
    final public function testFind(array $cache, string $project, EnumEnvironment $env, ?string $expectedUrl): void
    {
        // Arrange
        $this->fileServiceMock->method('isFileExists')->willReturn(true);
        $this->fileServiceMock->method('read')->willReturn($cache);

        // Act
        $result = $this->repository->find($project, $env);

        // Assert
        $this->assertSame($expectedUrl, $result?->getUrl());
    }

    public static function findDataProvider(): array
    {
        $cache = [
            'project-a' => [
                'rec' => 'http://url.rec/a',
                'prod' => 'http://url.prod/a',
            ],
            'project-b' => [
                'rec' => 'http://url.rec/b',
            ],
        ];

        return [
            'found' => [$cache, 'project-a', EnumEnvironment::PROD, 'http://url.prod/a'],
            'project not found' => [$cache, 'project-c', EnumEnvironment::PROD, null],
            'env not found' => [$cache, 'project-b', EnumEnvironment::PROD, null],
        ];
    }

    /**
     * @throws TechnicalException
     */
    final public function testSaveNewProjectInEmptyCache(): void
    {
        // Arrange
        $entity = new NewRelicEntity();
        $entity->setName('project-a');
        $entity->setEnvironment(EnumEnvironment::PROD);
        $entity->setUrl('http://new.url');

        $this->fileServiceMock->method('isFileExists')->willReturn(false);
        $this->fileServiceMock->method('read')->willReturn([]);

        $expectedCache = [
            'project-a' => [
                'prod' => 'http://new.url',
            ],
        ];

        $this->fileServiceMock->expects($this->once())
            ->method('save')
            ->with($expectedCache, 'new_relic_urls.json');

        // Act
        $this->repository->save($entity);
    }

    /**
     * @throws TechnicalException
     */
    final public function testSaveAddsEnvToExistingProject(): void
    {
        // Arrange
        $entity = new NewRelicEntity();
        $entity->setName('project-a');
        $entity->setEnvironment(EnumEnvironment::PROD);
        $entity->setUrl('http://new.prod.url');

        $initialCache = [
            'project-a' => [
                'rec' => 'http://url.rec/a',
            ],
        ];

        $this->fileServiceMock->method('isFileExists')->willReturn(true);
        $this->fileServiceMock->method('read')->willReturn($initialCache);

        $expectedCache = [
            'project-a' => [
                'rec' => 'http://url.rec/a',
                'prod' => 'http://new.prod.url',
            ],
        ];

        $this->fileServiceMock->expects($this->once())
            ->method('save')
            ->with($expectedCache, 'new_relic_urls.json');

        // Act
        $this->repository->save($entity);
    }

    /**
     * @throws TechnicalException
     */
    final public function testSaveUpdatesExistingUrl(): void
    {
        // Arrange
        $entity = new NewRelicEntity();
        $entity->setName('project-a');
        $entity->setEnvironment(EnumEnvironment::REC);
        $entity->setUrl('http://updated.rec.url');

        $initialCache = [
            'project-a' => [
                'rec' => 'http://original.url',
                'prod' => 'http://prod.url',
            ],
        ];

        $this->fileServiceMock->method('isFileExists')->willReturn(true);
        $this->fileServiceMock->method('read')->willReturn($initialCache);

        $expectedCache = [
            'project-a' => [
                'rec' => 'http://updated.rec.url',
                'prod' => 'http://prod.url',
            ],
        ];

        $this->fileServiceMock->expects($this->once())
            ->method('save')
            ->with($expectedCache, 'new_relic_urls.json');

        // Act
        $this->repository->save($entity);
    }
}