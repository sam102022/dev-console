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

    final public function testStaticFilesAreTreatedAsStatic(): void
    {
        $service = new RepositoryService(vfsStream::url('root'), self::$loggerFactory);
        $filename = 'rundeckObjects.json';
        $data = ['some' => 'rundeck_data'];

        // Write directly to the virtual file system to simulate the file being edited manually on disk
        $filePath = vfsStream::url('root') . '/' . $filename;
        file_put_contents($filePath, json_encode($data));

        // It should exist
        $this->assertTrue($service->isFileExists($filename));

        // It should read the data directly from the file
        $readData = $service->read($filename);
        $this->assertEquals($data, $readData);

        // Modifying it via the service should write back to the file
        $newData = ['updated' => 'rundeck_data'];
        $service->save($newData, $filename);

        $fileContent = json_decode(file_get_contents($filePath), true);
        $this->assertEquals($newData, $fileContent);
    }
}
