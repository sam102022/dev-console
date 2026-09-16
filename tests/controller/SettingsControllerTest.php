<?php
declare(strict_types=1);

namespace App\tests\controller;

use App\controller\SettingsController;
use App\service\RepositoryService;
use App\tests\AbstractTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class SettingsControllerTest extends AbstractTestCase
{
    private RepositoryService|MockObject $repositoryService;
    private SettingsController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repositoryService = $this->createMock(RepositoryService::class);
        $this->controller = new SettingsController(
            $this->repositoryService,
            $this->twigMocked
        );

        // Reset superglobals
        $_SESSION = [];
        $_POST = [];
    }

    public function testIndexSuccess(): void
    {
        $_SESSION['user_id'] = 42;
        $user = [
            'id' => 42,
            'email' => 'user@example.com',
            'postman_api_key' => 'old_key'
        ];

        $this->repositoryService->expects($this->once())
            ->method('findUserById')
            ->with(42)
            ->willReturn($user);

        $this->twigMocked->expects($this->once())
            ->method('render')
            ->with('settings.html.twig', [
                'user' => $user,
                'success' => 'Paramètre enregistré',
                'error' => 'Erreur critique'
            ])
            ->willReturn('rendered_settings');

        ob_start();
        $this->controller->index('Paramètre enregistré', 'Erreur critique');
        $output = ob_get_clean();

        $this->assertEquals('rendered_settings', $output);
    }

    public function testSaveSuccessWithApiKey(): void
    {
        $_SESSION['user_id'] = 42;
        $_POST['postman_api_key'] = '  new_api_key_123  ';

        $userBefore = [
            'id' => 42,
            'email' => 'user@example.com',
            'postman_api_key' => 'old_key'
        ];

        $userAfter = [
            'id' => 42,
            'email' => 'user@example.com',
            'postman_api_key' => 'new_api_key_123'
        ];

        $this->repositoryService->expects($this->exactly(2))
            ->method('findUserById')
            ->with(42)
            ->willReturn($userBefore);

        $this->repositoryService->expects($this->once())
            ->method('saveUser')
            ->with($userAfter)
            ->willReturn(42);

        $this->twigMocked->expects($this->once())
            ->method('render')
            ->with('settings.html.twig', [
                'user' => $userBefore, // Wait, since findUserById is called again in index(), it returns userBefore because of how we stubbed findUserById above. In reality, index() would fetch the updated database record. Stubbing it with $userBefore is fine, but let's be accurate about what's rendered.
                'success' => 'Vos paramètres ont été enregistrés avec succès !',
                'error' => null
            ])
            ->willReturn('rendered_settings_success');

        ob_start();
        $this->controller->save();
        $output = ob_get_clean();

        $this->assertEquals('rendered_settings_success', $output);
    }

    public function testSaveSuccessWithEmptyApiKey(): void
    {
        $_SESSION['user_id'] = 42;
        $_POST['postman_api_key'] = '   ';

        $userBefore = [
            'id' => 42,
            'email' => 'user@example.com',
            'postman_api_key' => 'old_key'
        ];

        $userAfter = [
            'id' => 42,
            'email' => 'user@example.com',
            'postman_api_key' => null
        ];

        $this->repositoryService->expects($this->exactly(2))
            ->method('findUserById')
            ->with(42)
            ->willReturn($userBefore);

        $this->repositoryService->expects($this->once())
            ->method('saveUser')
            ->with($userAfter)
            ->willReturn(42);

        $this->twigMocked->expects($this->once())
            ->method('render')
            ->with('settings.html.twig', [
                'user' => $userBefore,
                'success' => 'Vos paramètres ont été enregistrés avec succès !',
                'error' => null
            ])
            ->willReturn('rendered_settings_success');

        ob_start();
        $this->controller->save();
        $output = ob_get_clean();

        $this->assertEquals('rendered_settings_success', $output);
    }

    public function testSaveException(): void
    {
        $_SESSION['user_id'] = 42;
        $_POST['postman_api_key'] = 'some_key';

        $userBefore = [
            'id' => 42,
            'email' => 'user@example.com',
            'postman_api_key' => 'old_key'
        ];

        $userAfter = [
            'id' => 42,
            'email' => 'user@example.com',
            'postman_api_key' => 'some_key'
        ];

        $this->repositoryService->expects($this->exactly(2))
            ->method('findUserById')
            ->with(42)
            ->willReturn($userBefore);

        $this->repositoryService->expects($this->once())
            ->method('saveUser')
            ->with($userAfter)
            ->willThrowException(new \Exception('Database error'));

        $this->twigMocked->expects($this->once())
            ->method('render')
            ->with('settings.html.twig', [
                'user' => $userBefore,
                'success' => null,
                'error' => 'Une erreur est survenue lors de l\'enregistrement : Database error'
            ])
            ->willReturn('rendered_settings_error');

        ob_start();
        $this->controller->save();
        $output = ob_get_clean();

        $this->assertEquals('rendered_settings_error', $output);
    }
}
