<?php
declare(strict_types=1);

namespace App\controller;

use App\context\IndexContext;
use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\service\PostmanService;
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
    /**
     * @var Logger L'instance du logger pour cette classe.
     */
    private readonly Logger $logger;

    /**
     * Constructeur de la classe AdminController.
     *
     * @param IndexViewModelFactory $viewModelFactory Usine pour créer le modèle de vue de public.
     * @param PostmanService $postmanService Service Postman.
     * @param IndexContext $context Le contexte de la session public.
     * @param Environment $twig L'environnement Twig pour le rendu des templates.
     */
    public function __construct(
        private readonly IndexViewModelFactory $viewModelFactory,
        private readonly PostmanService $postmanService,
        private readonly IndexContext          $context,
        private readonly Environment           $twig,
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

    private function render(array $messages): void
    {
        try {
            $viewModel = $this->viewModelFactory->build($this->context, $messages);
            $viewModel['current_route'] = 'postman';
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
                    $response = $this->postmanService->createEnvironment(
                        $input['workspaceId'] ?? '',
                        $input['name'] ?? '',
                        $input['variables'] ?? []
                    );
                    break;

                case ACTION_POSTMAN_IMPORT_OPENAPI:
                    $response = $this->postmanService->importOpenApi(
                        $input['workspaceId'] ?? '',
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