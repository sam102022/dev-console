<?php
declare(strict_types=1);

namespace App\tests\controller;

use App\controller\UserAdminController;
use App\service\RepositoryService;
use App\tests\AbstractTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class UserAdminControllerTest extends AbstractTestCase
{
    private RepositoryService|MockObject $repositoryService;
    private UserAdminController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repositoryService = $this->createMock(RepositoryService::class);
        $this->controller = new UserAdminController(
            $this->repositoryService,
            $this->twigMocked
        );

        // Reset superglobals
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_REQUEST = [];
    }

    public function testIndexSuccess(): void
    {
        $messages = [];
        $this->twigMocked->expects($this->once())
            ->method('render')
            ->with('users.html.twig', [
                'current_route' => 'users'
            ])
            ->willReturn('rendered_users');

        ob_start();
        $this->controller->index($messages);
        $output = ob_get_clean();

        $this->assertEquals('rendered_users', $output);
    }

    public function testHandleRequestUnknownAction(): void
    {
        $response = $this->controller->handleRequest('non_existent_action');
        $data = json_decode($response, true);

        $this->assertFalse($data['success']);
        $this->assertEquals('Action inconnue: non_existent_action', $data['error']);
    }

    public function testGetDatagridRows(): void
    {
        $users = [
            ['id' => 1, 'email' => 'admin@mdm.com', 'role' => 'ROLE_ADMIN', 'created_at' => '2026-09-15 10:00:00'],
            ['id' => 2, 'email' => 'user@mdm.com', 'role' => 'ROLE_USER', 'created_at' => '2026-09-15 10:05:00']
        ];

        $_REQUEST = [
            'filter_email' => 'user',
            'sort_column' => 'created_at',
            'sort_dir' => 'desc',
            'p' => '1',
            'rows_per_page' => '15'
        ];

        $this->repositoryService->expects($this->once())
            ->method('getAllUsers')
            ->willReturn($users);

        $this->twigMocked->expects($this->once())
            ->method('render')
            ->with('common/_users_rows.html.twig', [
                'results' => [
                    ['id' => 2, 'email' => 'user@mdm.com', 'role' => 'ROLE_USER', 'created_at' => '2026-09-15 10:05:00']
                ],
                'offset' => 0
            ])
            ->willReturn('rows_rendered_html');

        $response = $this->controller->handleRequest('getDatagridRows');
        $data = json_decode($response, true);

        $this->assertTrue($data['success']);
        $this->assertEquals('rows_rendered_html', $data['html']);
        $this->assertEquals(1, $data['totalRows']);
    }

    public function testCreateUserMissingData(): void
    {
        $_POST = ['email' => '', 'password' => 'some_pass'];

        $response = $this->controller->handleRequest('createUser');
        $data = json_decode($response, true);

        $this->assertFalse($data['success']);
        $this->assertEquals('Email et mot de passe requis.', $data['error']);
    }

    public function testCreateUserAlreadyExists(): void
    {
        $_POST = ['email' => 'existing@mdm.com', 'password' => 'some_pass'];

        $this->repositoryService->expects($this->once())
            ->method('findUserByEmail')
            ->with('existing@mdm.com')
            ->willReturn(['id' => 1, 'email' => 'existing@mdm.com']);

        $response = $this->controller->handleRequest('createUser');
        $data = json_decode($response, true);

        $this->assertFalse($data['success']);
        $this->assertEquals('Cet email est déjà utilisé.', $data['error']);
    }

    public function testCreateUserSuccess(): void
    {
        $_POST = [
            'email' => 'new@mdm.com',
            'password' => 'secret_password',
            'role' => 'ROLE_USER'
        ];

        $this->repositoryService->expects($this->once())
            ->method('findUserByEmail')
            ->with('new@mdm.com')
            ->willReturn(null);

        $this->repositoryService->expects($this->once())
            ->method('saveUser')
            ->with($this->callback(function ($user) {
                $this->assertEquals('new@mdm.com', $user['email']);
                $this->assertEquals('ROLE_USER', $user['role']);
                $this->assertTrue(password_verify('secret_password', $user['password_hash']));
                return true;
            }))
            ->willReturn(123);

        $response = $this->controller->handleRequest('createUser');
        $data = json_decode($response, true);

        $this->assertTrue($data['success']);
        $this->assertEquals(123, $data['userId']);
    }

    public function testUpdateUserInvalidData(): void
    {
        $_POST = ['id' => 0, 'email' => 'updated@mdm.com'];

        $response = $this->controller->handleRequest('updateUser');
        $data = json_decode($response, true);

        $this->assertFalse($data['success']);
        $this->assertEquals('Données invalides.', $data['error']);
    }

    public function testUpdateUserNotFound(): void
    {
        $_POST = ['id' => 123, 'email' => 'updated@mdm.com'];

        $this->repositoryService->expects($this->once())
            ->method('findUserById')
            ->with(123)
            ->willReturn(null);

        $response = $this->controller->handleRequest('updateUser');
        $data = json_decode($response, true);

        $this->assertFalse($data['success']);
        $this->assertEquals('Utilisateur introuvable.', $data['error']);
    }

    public function testUpdateUserEmailAlreadyUsed(): void
    {
        $_POST = ['id' => 123, 'email' => 'existing@mdm.com', 'role' => 'ROLE_USER'];

        $existingUserInDb = [
            'id' => 123,
            'email' => 'old_email@mdm.com',
            'role' => 'ROLE_USER'
        ];

        $this->repositoryService->expects($this->once())
            ->method('findUserById')
            ->with(123)
            ->willReturn($existingUserInDb);

        $this->repositoryService->expects($this->once())
            ->method('findUserByEmail')
            ->with('existing@mdm.com')
            ->willReturn(['id' => 456, 'email' => 'existing@mdm.com']);

        $response = $this->controller->handleRequest('updateUser');
        $data = json_decode($response, true);

        $this->assertFalse($data['success']);
        $this->assertEquals('Cet email est déjà utilisé.', $data['error']);
    }

    public function testUpdateUserSuccessWithoutPassword(): void
    {
        $_SESSION['user_id'] = 456; // different user logged in

        $_POST = [
            'id' => 123,
            'email' => 'updated@mdm.com',
            'role' => 'ROLE_ADMIN'
        ];

        $userInDb = [
            'id' => 123,
            'email' => 'old_email@mdm.com',
            'password_hash' => 'some_hash',
            'role' => 'ROLE_USER'
        ];

        $this->repositoryService->expects($this->once())
            ->method('findUserById')
            ->with(123)
            ->willReturn($userInDb);

        $this->repositoryService->expects($this->once())
            ->method('findUserByEmail')
            ->with('updated@mdm.com')
            ->willReturn(null);

        $this->repositoryService->expects($this->once())
            ->method('saveUser')
            ->with($this->callback(function ($user) {
                $this->assertEquals(123, $user['id']);
                $this->assertEquals('updated@mdm.com', $user['email']);
                $this->assertEquals('ROLE_ADMIN', $user['role']);
                $this->assertEquals('some_hash', $user['password_hash']);
                return true;
            }))
            ->willReturn(123);

        $response = $this->controller->handleRequest('updateUser');
        $data = json_decode($response, true);

        $this->assertTrue($data['success']);
    }

    public function testUpdateUserSuccessWithPasswordAndSessionRefresh(): void
    {
        $_SESSION['user_id'] = 123; // same user updating their own profile
        $_SESSION['user_role'] = 'ROLE_USER';
        $_SESSION['user_email'] = 'old_email@mdm.com';

        $_POST = [
            'id' => 123,
            'email' => 'updated@mdm.com',
            'password' => 'new_password',
            'role' => 'ROLE_ADMIN'
        ];

        $userInDb = [
            'id' => 123,
            'email' => 'old_email@mdm.com',
            'password_hash' => 'old_hash',
            'role' => 'ROLE_USER'
        ];

        $this->repositoryService->expects($this->once())
            ->method('findUserById')
            ->with(123)
            ->willReturn($userInDb);

        $this->repositoryService->expects($this->once())
            ->method('findUserByEmail')
            ->with('updated@mdm.com')
            ->willReturn(null);

        $this->repositoryService->expects($this->once())
            ->method('saveUser')
            ->with($this->callback(function ($user) {
                $this->assertEquals(123, $user['id']);
                $this->assertEquals('updated@mdm.com', $user['email']);
                $this->assertEquals('ROLE_ADMIN', $user['role']);
                $this->assertTrue(password_verify('new_password', $user['password_hash']));
                return true;
            }))
            ->willReturn(123);

        $response = $this->controller->handleRequest('updateUser');
        $data = json_decode($response, true);

        $this->assertTrue($data['success']);
        $this->assertEquals('ROLE_ADMIN', $_SESSION['user_role']);
        $this->assertEquals('updated@mdm.com', $_SESSION['user_email']);
    }

    public function testDeleteUserInvalidId(): void
    {
        $_POST = ['id' => 0];

        $response = $this->controller->handleRequest('deleteUser');
        $data = json_decode($response, true);

        $this->assertFalse($data['success']);
        $this->assertEquals('Identifiant invalide.', $data['error']);
    }

    public function testDeleteUserSelfDeletionBlocked(): void
    {
        $_SESSION['user_id'] = 123;
        $_POST = ['id' => 123];

        $response = $this->controller->handleRequest('deleteUser');
        $data = json_decode($response, true);

        $this->assertFalse($data['success']);
        $this->assertEquals('Vous ne pouvez pas supprimer votre propre compte.', $data['error']);
    }

    public function testDeleteUserSuccessPost(): void
    {
        $_SESSION['user_id'] = 456;
        $_POST = ['id' => 123];

        $this->repositoryService->expects($this->once())
            ->method('deleteUser')
            ->with(123);

        $response = $this->controller->handleRequest('deleteUser');
        $data = json_decode($response, true);

        $this->assertTrue($data['success']);
    }

    public function testDeleteUserSuccessGet(): void
    {
        $_SESSION['user_id'] = 456;
        $_GET = ['id' => 123];

        $this->repositoryService->expects($this->once())
            ->method('deleteUser')
            ->with(123);

        $response = $this->controller->handleRequest('deleteUser');
        $data = json_decode($response, true);

        $this->assertTrue($data['success']);
    }
}
