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

    final public function testUserRememberMeOperations(): void
    {
        $service = new RepositoryService(vfsStream::url('root'), self::$loggerFactory);

        $user = [
            'email' => 'test@mdm.com',
            'password_hash' => 'hash',
            'role' => 'ROLE_USER',
            'remember_token' => 'secure_random_remember_token_123'
        ];

        // Save new user
        $userId = $service->saveUser($user);
        $this->assertGreaterThan(0, $userId);

        // Find user by email
        $foundUser = $service->findUserByEmail('test@mdm.com');
        $this->assertNotNull($foundUser);
        $this->assertEquals($userId, (int)$foundUser['id']);
        $this->assertEquals('secure_random_remember_token_123', $foundUser['remember_token']);

        // Find user by id
        $foundUserById = $service->findUserById($userId);
        $this->assertNotNull($foundUserById);
        $this->assertEquals('test@mdm.com', $foundUserById['email']);

        // Find user by remember token
        $foundUserByToken = $service->findUserByRememberToken('secure_random_remember_token_123');
        $this->assertNotNull($foundUserByToken);
        $this->assertEquals($userId, (int)$foundUserByToken['id']);

        // Update user (change token)
        $foundUserByToken['remember_token'] = 'new_remember_token_456';
        $service->saveUser($foundUserByToken);

        // Assert old token doesn't find the user anymore
        $this->assertNull($service->findUserByRememberToken('secure_random_remember_token_123'));

        // Assert new token finds the user
        $foundUserByNewToken = $service->findUserByRememberToken('new_remember_token_456');
        $this->assertNotNull($foundUserByNewToken);
        $this->assertEquals($userId, (int)$foundUserByNewToken['id']);

        // Delete user
        $service->deleteUser($userId);
        $this->assertNull($service->findUserById($userId));
    }
}
