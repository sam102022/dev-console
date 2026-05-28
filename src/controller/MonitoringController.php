<?php
declare(strict_types=1);

namespace App\controller;

use App\context\IndexContext;
use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\model\EnumEnvironment;
use App\service\GitlabService;
use App\service\MonitoringService;
use App\util\UtilsLog;
use App\viewModel\IndexViewModelFactory;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Logger;
use Throwable;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class MonitoringController
{
    /**
     * @var Logger L'instance du logger pour cette classe.
     */
    private readonly Logger $logger;

    /**
     * Constructeur de la classe AdminController.
     *
     * @param IndexViewModelFactory $viewModelFactory Usine pour créer le modèle de vue de public.
     * @param GitlabService $gitlabService Service gitlab.
     * @param MonitoringService $monitoringService Service monitoring.
     * @param IndexContext $context Le contexte de la session public.
     * @param Environment $twig L'environnement Twig pour le rendu des templates.
     * @param LoggerFactory $loggerFactory Usine pour créer le logger.
     */
    public function __construct(
        private readonly IndexViewModelFactory $viewModelFactory,
        private readonly GitlabService         $gitlabService,
        private readonly MonitoringService     $monitoringService,
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
     * @throws GuzzleException|TechnicalException
     */
    public function index(array $messages): void
    {
        $response = $this->gitlabService->scan();
        $this->viewModelFactory->setResults($response);

        $this->render($messages);
    }

    private function render(array $messages): void
    {
        try {
            $viewModel = $this->viewModelFactory->build($this->context, $messages);
            $viewModel['current_route'] = 'monitoring';
            echo $this->twig->render(
                'monitoring.html.twig',
                $viewModel
            );
        } catch (LoaderError|RuntimeError|SyntaxError|TechnicalException $e) {
            $this->logger->error(UtilsLog::prefixLog(self::class, __FUNCTION__, __LINE__) . $e->getMessage());
        }
    }

    public function handleRequest(string $action): string
    {
        $input = json_decode(file_get_contents("php://input"), true) ?? [];

        // Si ce n'est pas du JSON, on essaie via GET/POST
        $project = $input['project'] ?? $_REQUEST['project'] ?? '';
        $envString = $input['env'] ?? $_REQUEST['env'] ?? '';

        $env = EnumEnvironment::tryFrom($envString);

        try {
            http_response_code(200);
            switch ($action) {
                case ACTION_MONITORING_GET_DATA:
                    $data = $this->monitoringService->getMonitoringData($project, $env);
                    $response = [
                        'success' => true,
                        'health' => $data['health'],
                        'urls' => $data['urls'],
                    ];
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
