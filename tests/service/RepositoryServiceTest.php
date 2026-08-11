<?php
declare(strict_types=1);

namespace App\tests\service;

use App\exception\TechnicalException;
use App\service\RepositoryService;
use App\tests\AbstractTestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

final class RepositoryServiceTest extends AbstractTestCase
{
    private vfsStreamDirectory $root;

    final protected function setUp(): void
    {
        AbstractTestCase::setUp();
        $this->root = vfsStream::setup();
    }

    final public function testSaveAndReadFile(): void
    {
        $service = new RepositoryService(vfsStream::url('root'), self::$loggerFactory);
        $filename = 'test.json';
        $data = ['key' => 'value'];

        // Save data to cache
        $service->save($data, $filename);

        // Read data back
        $readData = $service->read($filename);

        // We assert using the service interface, since underlying structure is Symfony Cache
        $this->assertEquals($data, $readData);
    }

    final public function testReadFileNotExists(): void
    {
        $service = new RepositoryService(vfsStream::url('root'), self::$loggerFactory);
        $filename = 'non_existing.json';

        $readData = $service->read($filename);
        $this->assertEquals([], $readData);
    }

    final public function testIsFileExists(): void
    {
        $service = new RepositoryService(vfsStream::url('root'), self::$loggerFactory);
        $filename = 'existing.txt';

        $this->assertFalse($service->isFileExists($filename));

        $service->save(['test'], $filename);

        $this->assertTrue($service->isFileExists($filename));
    }

    final public function testDeleteFile(): void
    {
        $service = new RepositoryService(vfsStream::url('root'), self::$loggerFactory);
        $filename = 'to_delete.txt';

        $service->save(['data'], $filename);
        $this->assertTrue($service->isFileExists($filename));

        $service->delete($filename);
        $this->assertFalse($service->isFileExists($filename));
    }
}
