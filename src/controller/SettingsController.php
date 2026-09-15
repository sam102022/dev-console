<?php
declare(strict_types=1);

namespace App\controller;

use App\service\RepositoryService;
use Twig\Environment;

class SettingsController
{
    public const string ROUTE_SETTINGS = 'settings';
    public const string ACTION_SAVE_SETTINGS = 'saveSettings';

    public function __construct(
        private readonly RepositoryService $repositoryService,
        private readonly Environment $twig
    ) {}

    public function index(?string $successMessage = null, ?string $errorMessage = null): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ?page=login');
            exit;
        }

        $user = $this->repositoryService->findUserById((int)$_SESSION['user_id']);
        if (!$user) {
            header('Location: ?page=logout');
            exit;
        }

        echo $this->twig->render('settings.html.twig', [
            'user' => $user,
            'success' => $successMessage,
            'error' => $errorMessage
        ]);
    }

    public function save(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ?page=login');
            exit;
        }

        $user = $this->repositoryService->findUserById((int)$_SESSION['user_id']);
        if (!$user) {
            header('Location: ?page=logout');
            exit;
        }

        $postmanApiKey = trim($_POST['postman_api_key'] ?? '');

        // Update the user array
        $user['postman_api_key'] = $postmanApiKey ?: null;

        try {
            $this->repositoryService->saveUser($user);
            $this->index('Vos paramètres ont été enregistrés avec succès !');
        } catch (\Exception $e) {
            $this->index(null, 'Une erreur est survenue lors de l\'enregistrement : ' . $e->getMessage());
        }
    }
}
