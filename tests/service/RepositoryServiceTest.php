<?php
declare(strict_types=1);


use App\exception\TechnicalException;
use App\service\RepositoryService;
use App\tests\service\AbstractServiceCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;


class RepositoryServiceTest extends AbstractServiceCase
{
    private vfsStreamDirectory $root;

    final protected function setUp(): void
    {
        AbstractServiceCase::setUp();
        $this->root = vfsStream::setup();
    }

    final public function testSaveAndReadFile(): void
    {
        $service = new RepositoryService(vfsStream::url('root'), self::$loggerFactory);
        $filename = 'test.json';
        $data = ['key' => 'value'];

        $service->save($data, $filename);

        $this->assertTrue($this->root->hasChild($filename));
        $this->assertEquals(json_encode($data), $this->root->getChild($filename)->getContent());
    }

    final public function testReadFile(): void
    {
        $service = new RepositoryService(vfsStream::url('root'), self::$loggerFactory);
        $filename = 'test.json';
        $data = ['key' => 'value'];

        vfsStream::newFile($filename)
            ->withContent(json_encode($data))
            ->at($this->root);

        $readData = $service->read($filename);
        $this->assertEquals($data, $readData);
    }

    final public function testReadInvalidJson(): void
    {
        $this->expectException(TechnicalException::class);

        $service = new RepositoryService(vfsStream::url('root'), self::$loggerFactory);
        $filename = 'invalid.json';

        vfsStream::newFile($filename)
            ->withContent('{ "key": "value" ') // Invalid JSON
            ->at($this->root);

        $service->read($filename);
    }

    final public function testIsFileExists(): void
    {
        $service = new RepositoryService(vfsStream::url('root'), self::$loggerFactory);
        $filename = 'existing.txt';

        vfsStream::newFile($filename)->at($this->root);

        $this->assertTrue($service->isFileExists($filename));
        $this->assertFalse($service->isFileExists('non_existing.txt'));
    }

    final public function testDeleteFile(): void
    {
        $service = new RepositoryService(vfsStream::url('root'), self::$loggerFactory);
        $filename = 'to_delete.txt';

        vfsStream::newFile($filename)->at($this->root);
        $this->assertTrue($this->root->hasChild($filename));

        $service->delete($filename);
        $this->assertFalse($this->root->hasChild($filename));
    }
}
