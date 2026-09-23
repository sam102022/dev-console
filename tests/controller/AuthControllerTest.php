<?php
declare(strict_types=1);

namespace App\tests\controller;

use App\controller\AuthController;
use App\service\RepositoryService;
use App\tests\AbstractTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

class AuthControllerTest extends AbstractTestCase
{
    private RepositoryService|MockObject $repositoryService;
    private AuthController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repositoryService = $this->createMock(RepositoryService::class);
        $this->controller = $this->getMockBuilder(AuthController::class)
            ->setConstructorArgs([
                $this->repositoryService,
                $this->twigMocked
            ])
            ->onlyMethods(['redirect'])
            ->getMock();

        // Reset superglobals
        $_POST = [];
        $_GET = [];
        $_SESSION = [];
    }

    /**
     * Test page rendering methods
     */
    public static function pageRenderProvider(): array
    {
        return [
            'login page' => [
                'method' => 'login',
                'template' => 'login.html.twig'
            ],
            'forgot password page' => [
                'method' => 'forgotPassword',
                'template' => 'forgot_password.html.twig'
            ]
        ];
    }

    #[DataProvider('pageRenderProvider')]
    public function testPageRendering(string $method, string $template): void
    {
        $this->twigMocked->expects($this->once())
            ->method('render')
            ->with($template, $this->anything())
            ->willReturn('rendered_html');

        ob_start();
        $this->controller->{$method}();
        $output = ob_get_clean();

        $this->assertEquals('rendered_html', $output);
    }

    /**
     * Test login submit failures (parameterized)
     */
    public static function loginSubmitFailureProvider(): array
    {
        return [
            'non-existent user' => [
                'email' => 'unknown@mdm.com',
                'password' => 'anypassword',
                'dbUser' => null,
                'expectedError' => 'E-mail ou mot de passe incorrect.'
            ],
            'wrong password' => [
                'email' => 'admin@mdm.com',
                'password' => 'wrong_password',
                'dbUser' => [
                    'id' => 1,
                    'email' => 'admin@mdm.com',
                    'password_hash' => password_hash('correct_password', PASSWORD_BCRYPT),
                    'role' => 'ROLE_ADMIN'
                ],
                'expectedError' => 'E-mail ou mot de passe incorrect.'
            ],
            'empty email and password' => [
                'email' => '',
                'password' => '',
                'dbUser' => null,
                'expectedError' => 'E-mail ou mot de passe incorrect.'
            ],
            'correct email but empty password' => [
                'email' => 'admin@mdm.com',
                'password' => '',
                'dbUser' => [
                    'id' => 1,
                    'email' => 'admin@mdm.com',
                    'password_hash' => password_hash('correct_password', PASSWORD_BCRYPT),
                    'role' => 'ROLE_ADMIN'
                ],
                'expectedError' => 'E-mail ou mot de passe incorrect.'
            ],
            'correct email but space-only password' => [
                'email' => 'admin@mdm.com',
                'password' => '    ',
                'dbUser' => [
                    'id' => 1,
                    'email' => 'admin@mdm.com',
                    'password_hash' => password_hash('correct_password', PASSWORD_BCRYPT),
                    'role' => 'ROLE_ADMIN'
                ],
                'expectedError' => 'E-mail ou mot de passe incorrect.'
            ],
            'SQL injection pattern email' => [
                'email' => "' OR '1'='1",
                'password' => 'any_pass',
                'dbUser' => null,
                'expectedError' => 'E-mail ou mot de passe incorrect.'
            ]
        ];
    }

    #[DataProvider('loginSubmitFailureProvider')]
    public function testLoginSubmitFailure(string $email, string $password, ?array $dbUser, string $expectedError): void
    {
        $_POST['email'] = $email;
        $_POST['password'] = $password;

        $this->repositoryService->expects($this->once())
            ->method('findUserByEmail')
            ->with($email)
            ->willReturn($dbUser);

        $this->twigMocked->expects($this->once())
            ->method('render')
            ->with('login.html.twig', $this->callback(function ($context) use ($expectedError, $email) {
                $this->assertEquals($expectedError, $context['error']);
                $this->assertEquals($email, $context['email']);
                return true;
            }))
            ->willReturn('error_rendered_html');

        ob_start();
        $this->controller->loginSubmit();
        $output = ob_get_clean();

        $this->assertEquals('error_rendered_html', $output);
        $this->assertEmpty($_SESSION['user_id'] ?? null);
    }

    /**
     * Test forgot password submit (parameterized)
     */
    public static function forgotPasswordSubmitProvider(): array
    {
        return [
            'existent user' => [
                'email' => 'user@mdm.com',
                'dbUser' => [
                    'id' => 1,
                    'email' => 'user@mdm.com',
                    'password_hash' => 'hash',
                    'role' => 'ROLE_USER'
                ],
                'userSaved' => true
            ],
            'non-existent user' => [
                'email' => 'unknown@mdm.com',
                'dbUser' => null,
                'userSaved' => false
            ],
            'empty email' => [
                'email' => '',
                'dbUser' => null,
                'userSaved' => false
            ],
            'email with spaces' => [
                'email' => '  some@mdm.com  ',
                'dbUser' => null,
                'userSaved' => false
            ]
        ];
    }

    #[DataProvider('forgotPasswordSubmitProvider')]
    public function testForgotPasswordSubmit(string $email, ?array $dbUser, bool $userSaved): void
    {
        $_POST['email'] = $email;

        $this->repositoryService->expects($this->once())
            ->method('findUserByEmail')
            ->with($email)
            ->willReturn($dbUser);

        if ($userSaved) {
            $this->repositoryService->expects($this->once())
                ->method('saveUser')
                ->with($this->callback(function ($user) use ($email) {
                    $this->assertEquals($email, $user['email']);
                    $this->assertNotNull($user['reset_token']);
                    $this->assertNotNull($user['reset_token_expires_at']);
                    return true;
                }));
        } else {
            $this->repositoryService->expects($this->never())
                ->method('saveUser');
        }

        $this->twigMocked->expects($this->once())
            ->method('render')
            ->with('forgot_password.html.twig', $this->callback(function ($context) {
                $this->assertStringContainsString('Si cet e-mail existe dans notre base de données', $context['success']);
                return true;
            }))
            ->willReturn('forgot_rendered_html');

        ob_start();
        $this->controller->forgotPasswordSubmit();
        $output = ob_get_clean();

        $this->assertEquals('forgot_rendered_html', $output);
    }

    /**
     * Test reset password rendering (parameterized)
     */
    public static function resetPasswordRenderProvider(): array
    {
        return [
            'valid token' => [
                'token' => 'valid_token_123',
                'dbUser' => [
                    'id' => 1,
                    'email' => 'user@mdm.com',
                    'reset_token' => 'valid_token_123'
                ],
                'expectedTemplate' => 'reset_password.html.twig',
                'expectError' => false
            ],
            'invalid token' => [
                'token' => 'invalid_token_456',
                'dbUser' => null,
                'expectedTemplate' => 'reset_password.html.twig',
                'expectError' => true
            ]
        ];
    }

    #[DataProvider('resetPasswordRenderProvider')]
    public function testResetPasswordRender(string $token, ?array $dbUser, string $expectedTemplate, bool $expectError): void
    {
        $_GET['token'] = $token;

        $this->repositoryService->expects($this->once())
            ->method('findUserByResetToken')
            ->with($token)
            ->willReturn($dbUser);

        $this->twigMocked->expects($this->once())
            ->method('render')
            ->with($expectedTemplate, $this->callback(function ($context) use ($expectError, $token) {
                if ($expectError) {
                    $this->assertEquals('Le jeton de réinitialisation est invalide ou a expiré.', $context['error']);
                } else {
                    $this->assertEquals($token, $context['token']);
                }
                return true;
            }))
            ->willReturn('reset_rendered_html');

        ob_start();
        $this->controller->resetPassword();
        $output = ob_get_clean();

        $this->assertEquals('reset_rendered_html', $output);
    }

    /**
     * Test reset password submit failures (parameterized)
     */
    public static function resetPasswordSubmitFailureProvider(): array
    {
        return [
            'invalid token' => [
                'token' => 'invalid_token',
                'password' => 'newpassword123',
                'confirmPassword' => 'newpassword123',
                'dbUser' => null,
                'expectedError' => 'Le jeton de réinitialisation est invalide ou a expiré.'
            ],
            'empty password' => [
                'token' => 'valid_token',
                'password' => '',
                'confirmPassword' => '',
                'dbUser' => [
                    'id' => 1,
                    'email' => 'user@mdm.com'
                ],
                'expectedError' => 'Le mot de passe ne peut pas être vide.'
            ],
            'passwords mismatch' => [
                'token' => 'valid_token',
                'password' => 'password123',
                'confirmPassword' => 'mismatch456',
                'dbUser' => [
                    'id' => 1,
                    'email' => 'user@mdm.com'
                ],
                'expectedError' => 'Les mots de passe ne correspondent pas.'
            ]
        ];
    }

    #[DataProvider('resetPasswordSubmitFailureProvider')]
    public function testResetPasswordSubmitFailure(
        string $token,
        string $password,
        string $confirmPassword,
        ?array $dbUser,
        string $expectedError
    ): void {
        $_POST['token'] = $token;
        $_POST['password'] = $password;
        $_POST['confirm_password'] = $confirmPassword;

        $this->repositoryService->expects($this->once())
            ->method('findUserByResetToken')
            ->with($token)
            ->willReturn($dbUser);

        $this->twigMocked->expects($this->once())
            ->method('render')
            ->with('reset_password.html.twig', $this->callback(function ($context) use ($expectedError, $token) {
                $this->assertEquals($expectedError, $context['error']);
                if ($context['error'] !== 'Le jeton de réinitialisation est invalide ou a expiré.') {
                    $this->assertEquals($token, $context['token']);
                }
                return true;
            }))
            ->willReturn('reset_error_rendered_html');

        ob_start();
        $this->controller->resetPasswordSubmit();
        $output = ob_get_clean();

        $this->assertEquals('reset_error_rendered_html', $output);
    }

    /**
     * Test reset password submit success (parameterized)
     */
    public static function resetPasswordSubmitSuccessProvider(): array
    {
        return [
            'standard password' => [
                'token' => 'success_token_123',
                'password' => 'SecurePassword123!',
                'dbUser' => [
                    'id' => 42,
                    'email' => 'user@example.com',
                    'reset_token' => 'success_token_123',
                    'reset_token_expires_at' => '2026-09-15 12:00:00'
                ]
            ],
            'password with special characters' => [
                'token' => 'success_token_456',
                'password' => 'Another_Pass_#_2026',
                'dbUser' => [
                    'id' => 101,
                    'email' => 'admin@example.com',
                    'reset_token' => 'success_token_456',
                    'reset_token_expires_at' => '2026-09-15 12:00:00'
                ]
            ]
        ];
    }

    #[DataProvider('resetPasswordSubmitSuccessProvider')]
    public function testResetPasswordSubmitSuccess(string $token, string $password, array $dbUser): void
    {
        $_POST['token'] = $token;
        $_POST['password'] = $password;
        $_POST['confirm_password'] = $password;

        $this->repositoryService->expects($this->once())
            ->method('findUserByResetToken')
            ->with($token)
            ->willReturn($dbUser);

        $this->repositoryService->expects($this->once())
            ->method('saveUser')
            ->with($this->callback(function ($user) use ($password) {
                $this->assertNull($user['reset_token']);
                $this->assertNull($user['reset_token_expires_at']);
                $this->assertTrue(password_verify($password, $user['password_hash']));
                return true;
            }))
            ->willReturn(1);

        $this->twigMocked->expects($this->once())
            ->method('render')
            ->with('login.html.twig', [
                'success' => 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.'
            ])
            ->willReturn('login_rendered_html');

        ob_start();
        $this->controller->resetPasswordSubmit();
        $output = ob_get_clean();

        $this->assertEquals('login_rendered_html', $output);
    }

    /**
     * Test successful login submissions (parameterized)
     */
    public static function loginSubmitSuccessProvider(): array
    {
        return [
            'login success without remember me' => [
                'email' => 'admin@mdm.com',
                'password' => 'password123',
                'dbUser' => [
                    'id' => 1,
                    'email' => 'admin@mdm.com',
                    'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
                    'role' => 'ROLE_ADMIN'
                ],
                'rememberMe' => false
            ],
            'login success with remember me' => [
                'email' => 'user@mdm.com',
                'password' => 'userpass',
                'dbUser' => [
                    'id' => 2,
                    'email' => 'user@mdm.com',
                    'password_hash' => password_hash('userpass', PASSWORD_BCRYPT),
                    'role' => 'ROLE_USER'
                ],
                'rememberMe' => true
            ]
        ];
    }

    #[DataProvider('loginSubmitSuccessProvider')]
    public function testLoginSubmitSuccess(string $email, string $password, array $dbUser, bool $rememberMe): void
    {
        $_POST['email'] = $email;
        $_POST['password'] = $password;
        if ($rememberMe) {
            $_POST['remember_me'] = 'on';
        }

        $this->repositoryService->expects($this->once())
            ->method('findUserByEmail')
            ->with($email)
            ->willReturn($dbUser);

        if ($rememberMe) {
            $this->repositoryService->expects($this->once())
                ->method('saveUser')
                ->with($this->callback(function ($user) use ($email) {
                    $this->assertEquals($email, $user['email']);
                    $this->assertNotNull($user['remember_token']);
                    return true;
                }))
                ->willReturn(1);
        } else {
            $this->repositoryService->expects($this->never())
                ->method('saveUser');
        }

        // Expect redirect to be called with ?page=index
        $this->controller->expects($this->once())
            ->method('redirect')
            ->with('?page=index');

        $this->controller->loginSubmit();

        $this->assertEquals($dbUser['id'], $_SESSION['user_id']);
        $this->assertEquals($dbUser['role'], $_SESSION['user_role']);
        $this->assertEquals($dbUser['email'], $_SESSION['user_email']);
    }

    /**
     * Test logout method with various initial session configurations (parameterized)
     */
    public static function logoutProvider(): array
    {
        return [
            'logout logged in user found in DB' => [
                'sessionUserId' => 42,
                'dbUser' => [
                    'id' => 42,
                    'email' => 'user@mdm.com',
                    'remember_token' => 'old_token'
                ],
                'expectSave' => true
            ],
            'logout logged in user not found in DB' => [
                'sessionUserId' => 42,
                'dbUser' => null,
                'expectSave' => false
            ],
            'logout guest user' => [
                'sessionUserId' => null,
                'dbUser' => null,
                'expectSave' => false
            ]
        ];
    }

    #[DataProvider('logoutProvider')]
    public function testLogout(?int $sessionUserId, ?array $dbUser, bool $expectSave): void
    {
        if ($sessionUserId !== null) {
            $_SESSION['user_id'] = $sessionUserId;
            $_SESSION['user_role'] = 'ROLE_USER';
            $_SESSION['user_email'] = 'user@mdm.com';

            $this->repositoryService->expects($this->once())
                ->method('findUserById')
                ->with($sessionUserId)
                ->willReturn($dbUser);

            if ($expectSave) {
                $this->repositoryService->expects($this->once())
                    ->method('saveUser')
                    ->with($this->callback(function ($user) {
                        $this->assertNull($user['remember_token']);
                        return true;
                    }))
                    ->willReturn(1);
            } else {
                $this->repositoryService->expects($this->never())
                    ->method('saveUser');
            }
        } else {
            $this->repositoryService->expects($this->never())
                ->method('findUserById');
            $this->repositoryService->expects($this->never())
                ->method('saveUser');
        }

        // Expect redirect to be called with ?page=login
        $this->controller->expects($this->once())
            ->method('redirect')
            ->with('?page=login');

        $this->controller->logout();

        $this->assertArrayNotHasKey('user_id', $_SESSION);
        $this->assertArrayNotHasKey('user_role', $_SESSION);
        $this->assertArrayNotHasKey('user_email', $_SESSION);
    }
}

