<?php
declare(strict_types=1);

namespace App\tests\service;

use App\exception\TechnicalException;
use App\model\EnumEnvironment;
use App\model\NewRelic;
use App\repository\mapper\NewRelicMapper;
use App\repository\NewRelicRepository;
use App\service\NewRelicService;
use App\tests\AbstractTestCase;

class NewRelicServiceTest extends AbstractTestCase
{
    private NewRelicRepository $repositoryMock;
    private NewRelicService $service;

    protected function setUp(): void
    {
        $this->repositoryMock = $this->createMock(NewRelicRepository::class);

        $this->service = new NewRelicService(
            $this->repositoryMock,
            self::$loggerFactory
        );
    }

    /**
     * @throws TechnicalException
     */
    final public function testFindSuccess(): void
    {
        // Arrange
        $projectName = 'test-project';
        $env = EnumEnvironment::PROD;
        $url = 'http://newrelic.url';
        $entity = NewRelicMapper::toEntity(NewRelic::build($projectName, $env, $url));

        $this->repositoryMock->expects($this->once())
            ->method('find')
            ->with($projectName, $env)
            ->willReturn($entity);

        // Act
        $result = $this->service->find($projectName, $env);

        // Assert
        $this->assertInstanceOf(NewRelic::class, $result);
        $this->assertEquals($projectName, $result->getName());
        $this->assertEquals($env, $result->getEnvironment());
        $this->assertEquals($url, $result->getUrl());
    }

    /**
     * @throws TechnicalException
     */
    final public function testFindNotFound(): void
    {
        // Arrange
        $this->repositoryMock->method('find')->willReturn(null);

        // Act
        $result = $this->service->find('any-project', EnumEnvironment::PROD);

        // Assert
        $this->assertNull($result);
    }

    /**
     * @throws TechnicalException
     */
    final public function testSave(): void
    {
        // Arrange
        $model = NewRelic::build('test-project', EnumEnvironment::PROD, 'http://some.url');
        $entity = NewRelicMapper::toEntity($model);

        $this->repositoryMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function ($savedEntity) use ($entity) {
                return $savedEntity->getName() === $entity->getName() &&
                       $savedEntity->getEnvironment() === $entity->getEnvironment() &&
                       $savedEntity->getUrl() === $entity->getUrl();
            }));

        // Act
        $this->service->save($model);
    }
}