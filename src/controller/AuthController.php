<?php
declare(strict_types=1);

namespace App\controller;

use App\service\RepositoryService;
use Twig\Environment;

class AuthController
{
    public const string ROUTE_LOGIN = 'login';
    public const string ROUTE_LOGOUT = 'logout';
    public const string ROUTE_FORGOT_PASSWORD = 'forgotPassword';
    public const string ROUTE_RESET_PASSWORD = 'resetPassword';

    public const string ACTION_LOGIN_SUBMIT = 'loginSubmit';
    public const string ACTION_FORGOT_PASSWORD_SUBMIT = 'forgotPasswordSubmit';
    public const string ACTION_RESET_PASSWORD_SUBMIT = 'resetPasswordSubmit';

    public function __construct(
        private readonly RepositoryService $repositoryService,
        private readonly Environment $twig
    ) {}

    public function login(): void
    {
        if (isset($_SESSION['user_id'])) {
            header('Location: ?page=index');
            exit;
        }
        echo $this->twig->render('login.html.twig');
    }

    public function loginSubmit(): void
    {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $user = $this->repositoryService->findUserByEmail($email);
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_email'] = $user['email'];
            header('Location: ?page=index');
            exit;
        }

        echo $this->twig->render('login.html.twig', [
            'error' => 'E-mail ou mot de passe incorrect.',
            'email' => $email
        ]);
    }

    public function logout(): void
    {
        unset($_SESSION['user_id']);
        unset($_SESSION['user_role']);
        unset($_SESSION['user_email']);
        header('Location: ?page=login');
        exit;
    }

    public function forgotPassword(): void
    {
        echo $this->twig->render('forgot_password.html.twig');
    }

    public function forgotPasswordSubmit(): void
    {
        $email = $_POST['email'] ?? '';
        $user = $this->repositoryService->findUserByEmail($email);

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $user['reset_token'] = $token;
            $user['reset_token_expires_at'] = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $this->repositoryService->saveUser($user);

            $logDir = dirname(dirname(__DIR__)) . '/var/logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0777, true);
            }
            $logFile = $logDir . '/forgot_password.log';
            $resetLink = "?page=resetPassword&token=" . $token;
            $content = sprintf("[%s] Reset link for %s: %s\n", date('Y-m-d H:i:s'), $email, $resetLink);
            file_put_contents($logFile, $content, FILE_APPEND);
        }

        echo $this->twig->render('forgot_password.html.twig', [
            'success' => 'Si cet e-mail existe dans notre base de données, un lien de réinitialisation vous a été envoyé.'
        ]);
    }

    public function resetPassword(): void
    {
        $token = $_GET['token'] ?? '';
        $user = $this->repositoryService->findUserByResetToken($token);

        if (!$user) {
            echo $this->twig->render('reset_password.html.twig', [
                'error' => 'Le jeton de réinitialisation est invalide ou a expiré.'
            ]);
            return;
        }

        echo $this->twig->render('reset_password.html.twig', [
            'token' => $token
        ]);
    }

    public function resetPasswordSubmit(): void
    {
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $user = $this->repositoryService->findUserByResetToken($token);
        if (!$user) {
            echo $this->twig->render('reset_password.html.twig', [
                'error' => 'Le jeton de réinitialisation est invalide ou a expiré.'
            ]);
            return;
        }

        if (empty($password)) {
            echo $this->twig->render('reset_password.html.twig', [
                'token' => $token,
                'error' => 'Le mot de passe ne peut pas être vide.'
            ]);
            return;
        }

        if ($password !== $confirmPassword) {
            echo $this->twig->render('reset_password.html.twig', [
                'token' => $token,
                'error' => 'Les mots de passe ne correspondent pas.'
            ]);
            return;
        }

        $user['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
        $user['reset_token'] = null;
        $user['reset_token_expires_at'] = null;
        $this->repositoryService->saveUser($user);

        echo $this->twig->render('login.html.twig', [
            'success' => 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.'
        ]);
    }
}
