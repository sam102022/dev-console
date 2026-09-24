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
    private RepositoryService $repositoryService;

    final protected function setUp(): void
    {
        AbstractTestCase::setUp();
        $this->root = vfsStream::setup();
        $this->repositoryService = new RepositoryService(vfsStream::url('root'), self::$loggerFactory);
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

    final public function testProjectOperations(): void
    {
        $service = new RepositoryService(vfsStream::url('root'), self::$loggerFactory);

        $projects = [
            [
                'name' => 'proj-a',
                'serviceName' => 'service-a',
                'domain' => 'domain-a',
                'domainName' => 'domainName-a',
                'sf' => 'sf-a',
                'cloudGCP' => true,
                'springBoot' => '2.5',
                'java' => '11',
                'techno' => 'java',
                'subscriptionName' => 'sub-a',
                'mdmWorkloadVersion' => '1.0',
                'pathLivenessProbe' => '/health',
                'webUrl' => 'http://url-a',
                'archived' => false,
                'urlHealthCheck' => ['dev' => 'http://dev'],
                'urlActuatorInfo' => ['dev' => 'http://info'],
                'urlLogs' => ['dev' => 'http://logs'],
                'urlFronts' => [],
                'urlPubsubs' => [],
                'urlsRundeck' => [],
                'urlsDeploymentGcp' => []
            ]
        ];

        // Save projects
        $service->saveProjects($projects);

        // Get projects back
        $loadedProjects = $service->getProjects();
        $this->assertCount(1, $loadedProjects);
        $this->assertEquals('proj-a', $loadedProjects[0]['name']);
        $this->assertTrue($loadedProjects[0]['cloudGCP']);
        $this->assertEquals(['dev' => 'http://dev'], $loadedProjects[0]['urlHealthCheck']);

        // Find single project by name
        $foundProj = $service->findProjectByName('proj-a');
        $this->assertNotNull($foundProj);
        $this->assertEquals('service-a', $foundProj['serviceName']);

        // Find non-existent project
        $this->assertNull($service->findProjectByName('non-existent'));

        // Save single project (update)
        $foundProj['serviceName'] = 'updated-service';
        $foundProj['urlFronts'] = ['prod' => 'http://front'];
        $service->saveProject($foundProj);

        $reloadedProj = $service->findProjectByName('proj-a');
        $this->assertEquals('updated-service', $reloadedProj['serviceName']);
        $this->assertEquals(['prod' => 'http://front'], $reloadedProj['urlFronts']);
    }

    final public function testGitlabProjectOperations(): void
    {
        $service = new RepositoryService(vfsStream::url('root'), self::$loggerFactory);

        $gitlabProjects = [
            [
                'id' => 123,
                'name' => 'gitlab-proj',
                'description' => 'A gitlab proj',
                'path' => 'gitlab-path',
                'web_url' => 'http://gitlab-url',
                'archived' => true
            ]
        ];

        // Save gitlab projects
        $service->saveGitlabProjects($gitlabProjects);

        // Read gitlab projects back
        $loadedGitlabProjs = $service->getGitlabProjects();
        $this->assertCount(1, $loadedGitlabProjs);
        $this->assertEquals(123, $loadedGitlabProjs[0]['id']);
        $this->assertEquals('gitlab-proj', $loadedGitlabProjs[0]['name']);
        $this->assertTrue($loadedGitlabProjs[0]['archived']);
    }

    final public function testRundeckProjectOperations(): void
    {
        $service = new RepositoryService(vfsStream::url('root'), self::$loggerFactory);

        $rundeckProjects = [
            [
                'name' => 'rundeck-proj',
                'domain' => 'domain-a',
                'sf' => 'sf-a',
                'category' => 'cat-a',
                'token' => ['token1', 'token2'],
                'path' => 'path/to/job',
                'projectName' => 'rundeck-real-name'
            ]
        ];

        // Save rundeck projects
        $service->saveRundeckProjects($rundeckProjects);

        // Read rundeck projects back
        $loadedRundeckProjs = $service->getRundeckProjects();
        $this->assertCount(1, $loadedRundeckProjs);
        $this->assertEquals('rundeck-proj', $loadedRundeckProjs[0]['name']);
        $this->assertEquals(['token1', 'token2'], $loadedRundeckProjs[0]['token']);
        $this->assertEquals('path/to/job', $loadedRundeckProjs[0]['path']);
    }

    final public function testUserResetTokenAndAllUsers(): void
    {
        $service = new RepositoryService(vfsStream::url('root'), self::$loggerFactory);

        $user1 = [
            'email' => 'user1@mdm.com',
            'password_hash' => 'hash1',
            'role' => 'ROLE_USER',
            'reset_token' => 'token_valid_1',
            'reset_token_expires_at' => date('Y-m-d H:i:s', strtotime('+2 hours'))
        ];

        $user2 = [
            'email' => 'user2@mdm.com',
            'password_hash' => 'hash2',
            'role' => 'ROLE_ADMIN',
            'reset_token' => 'token_expired_2',
            'reset_token_expires_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
        ];

        $service->saveUser($user1);
        $service->saveUser($user2);

        // Fetch all users and verify count
        $allUsers = $service->getAllUsers();
        // Database is pre-populated with an admin, so we expect 3 users total
        $this->assertCount(3, $allUsers);

        // Find user by valid reset token
        $foundUser1 = $service->findUserByResetToken('token_valid_1');
        $this->assertNotNull($foundUser1);
        $this->assertEquals('user1@mdm.com', $foundUser1['email']);

        // Find user by expired reset token (should return null)
        $foundUser2 = $service->findUserByResetToken('token_expired_2');
        $this->assertNull($foundUser2);

        // Find user by non-existent reset token
        $this->assertNull($service->findUserByResetToken('non-existent'));
    }

    final public function testFilesystemCacheFallback(): void
    {
        $service = new RepositoryService(vfsStream::url('root'), self::$loggerFactory);

        // Force SQLite to false to trigger filesystem cache fallback path
        $reflection = new \ReflectionClass($service);
        $useSqliteProp = $reflection->getProperty('useSqlite');
        $useSqliteProp->setValue($service, false);

        $cacheProp = $reflection->getProperty('cache');
        $cacheProp->setValue($service, new \Symfony\Component\Cache\Adapter\FilesystemAdapter('dev_console_data', 0, vfsStream::url('root')));

        $filename = 'fallback_cache.json';
        $data = ['fallback' => 'data_value'];

        // Save using fallback filesystem cache
        $service->save($data, $filename);

        // Read using fallback filesystem cache
        $readData = $service->read($filename);
        $this->assertEquals($data, $readData);

        // Delete using fallback filesystem cache
        $service->delete($filename);
        $this->assertEquals([], $service->read($filename));
    }

    public function testProjectTagsCrud(): void
    {
        // 1. Initialement vide
        $tags = $this->repositoryService->getTagsForProject('api-orders');
        $this->assertEmpty($tags);

        // 2. Ajout de tags
        $this->assertTrue($this->repositoryService->addProjectTag('api-orders', 'paiement'));
        $this->assertTrue($this->repositoryService->addProjectTag('api-orders', 'checkout'));
        $this->assertTrue($this->repositoryService->addProjectTag('flow-orders', 'paiement'));

        // 3. Récupération par projet
        $ordersTags = $this->repositoryService->getTagsForProject('api-orders');
        $this->assertCount(2, $ordersTags);
        $this->assertContains('paiement', $ordersTags);
        $this->assertContains('checkout', $ordersTags);

        // 4. Récupération groupée
        $allGrouped = $this->repositoryService->getTagsByProject();
        $this->assertArrayHasKey('api-orders', $allGrouped);
        $this->assertArrayHasKey('flow-orders', $allGrouped);
        $this->assertContains('paiement', $allGrouped['flow-orders']);

        // 5. Récupération de tous les tags uniques
        $uniqueTags = $this->repositoryService->getAllTags();
        $this->assertEquals(['checkout', 'paiement'], $uniqueTags);

        // 6. Suppression d'un tag
        $this->assertTrue($this->repositoryService->removeProjectTag('api-orders', 'checkout'));
        $ordersTagsAfter = $this->repositoryService->getTagsForProject('api-orders');
        $this->assertCount(1, $ordersTagsAfter);
        $this->assertNotContains('checkout', $ordersTagsAfter);

        // 7. Doublon ignoré (idempotence)
        $this->assertTrue($this->repositoryService->addProjectTag('api-orders', 'paiement'));
        $ordersTagsDedup = $this->repositoryService->getTagsForProject('api-orders');
        $this->assertCount(1, $ordersTagsDedup);
    }

    public function testProjectTagsEdgeCasesAndFallback(): void
    {
        // Edge cases: empty tag and empty project
        $this->assertFalse($this->repositoryService->addProjectTag('', 'tag'));
        $this->assertFalse($this->repositoryService->addProjectTag('api-orders', ''));
        $this->assertFalse($this->repositoryService->addProjectTag('api-orders', '   '));

        // Fallback when SQLite is disabled
        $reflection = new \ReflectionClass($this->repositoryService);
        $useSqliteProp = $reflection->getProperty('useSqlite');
        $useSqliteProp->setValue($this->repositoryService, false);

        $this->assertEquals([], $this->repositoryService->getTagsByProject());
        $this->assertEquals([], $this->repositoryService->getTagsForProject('api-orders'));
        $this->assertFalse($this->repositoryService->addProjectTag('api-orders', 'test'));
        $this->assertFalse($this->repositoryService->removeProjectTag('api-orders', 'test'));
        $this->assertEquals([], $this->repositoryService->getAllTags());
    }
}
