<?php
declare(strict_types=1);

namespace App\controller;

use App\context\IndexContext;
use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\service\PostmanService;
use App\service\RepositoryService;
use App\util\UtilsLog;
use App\viewModel\IndexViewModelFactory;
use Monolog\Logger;
use Throwable;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class PostmanController
{
    public const string ROUTE_POSTMAN = 'postman';

    /**
     * @var Logger L'instance du logger pour cette classe.
     */
    private readonly Logger $logger;

    /**
     * Constructeur de la classe PostmanController.
     *
     * @param IndexViewModelFactory $viewModelFactory Usine pour créer le modèle de vue de public.
     * @param PostmanService $postmanService Service Postman.
     * @param IndexContext $context Le contexte de la session public.
     * @param Environment $twig L'environnement Twig pour le rendu des templates.
     * @param RepositoryService $repositoryService Service de gestion des données.
     * @param LoggerFactory $loggerFactory Fabrique de loggers.
     */
    public function __construct(
        private readonly IndexViewModelFactory $viewModelFactory,
        private readonly PostmanService $postmanService,
        private readonly IndexContext          $context,
        private readonly Environment           $twig,
        private readonly RepositoryService     $repositoryService,
        LoggerFactory                          $loggerFactory
    )
    {
        $this->logger = $loggerFactory->get(self::class);
    }

    /**
     * Affiche la page pour gérer les collections postman.
     *
     * @param array $messages Un tableau de messages à afficher à l'utilisateur (notifications, erreurs, etc.).
     */
    public function index(array $messages): void
    {
        $this->render($messages);
    }

    private function hasUserPostmanApiKey(): bool
    {
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId === null) {
            return false;
        }
        $user = $this->repositoryService->findUserById((int)$userId);
        return !empty(trim((string)($user['postman_api_key'] ?? '')));
    }

    private function render(array $messages): void
    {
        try {
            $viewModel = $this->viewModelFactory->build($this->context, $messages);
            $viewModel['current_route'] = self::ROUTE_POSTMAN;
            $viewModel['has_postman_api_key'] = $this->hasUserPostmanApiKey();
            echo $this->twig->render(
                'postman.html.twig',
                $viewModel
            );
        } catch (LoaderError|RuntimeError|SyntaxError|TechnicalException $e) {
            $this->logger->error(UtilsLog::prefixLog(self::class, __FUNCTION__, __LINE__) . $e->getMessage());
        }
    }

    public function handleRequest(string $action, ?string $rawInput = null): string
    {
        if (!$this->hasUserPostmanApiKey()) {
            http_response_code(403);
            return json_encode(['error' => 'Clé API Postman manquante. Veuillez la configurer dans Mon compte > Paramètres.']);
        }

        $rawInput = $rawInput ?? file_get_contents("php://input");
        $input = json_decode($rawInput, true) ?? [];

        try {
            http_response_code(200);
            switch ($action) {
                case ACTION_POSTMAN_WORKSPACES:
                    $response = $this->postmanService->getWorkspaces();
                    break;

                case ACTION_POSTMAN_CREATE_WORKSPACE:
                    $response = $this->postmanService->createWorkspace(
                        $input['name'] ?? '',
                        $input['description'] ?? ''
                    );
                    break;

                case ACTION_POSTMAN_CREATE_ENVIRONMENT:
                    $workspaceId = trim((string)($input['workspaceId'] ?? ''));
                    if ($workspaceId === '') {
                        http_response_code(400);
                        return json_encode(['error' => 'Le workspace est obligatoire.']);
                    }
                    $response = $this->postmanService->createEnvironment(
                        $workspaceId,
                        $input['name'] ?? '',
                        $input['variables'] ?? []
                    );
                    break;

                case ACTION_POSTMAN_IMPORT_OPENAPI:
                    $workspaceId = trim((string)($input['workspaceId'] ?? ''));
                    if ($workspaceId === '') {
                        http_response_code(400);
                        return json_encode(['error' => 'Le workspace est obligatoire.']);
                    }
                    $response = $this->postmanService->importOpenApi(
                        $workspaceId,
                        $input['fileContent'] ?? ''
                    );
                    break;

                case ACTION_POSTMAN_GET_WORKSPACE_DETAILS:
                    $response = $this->postmanService->getWorkspaceDetails($_GET['id'] ?? '');
                    break;

                default:
                    http_response_code(400);
                    $response = ['error' => 'Action inconnue'];
            }
        } catch (Throwable $e) {
            http_response_code(500);
            $response = ['error' => $e->getMessage()];
        }

        return json_encode($response);
    }
}
