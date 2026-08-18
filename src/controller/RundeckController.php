<?php
declare(strict_types=1);

namespace App\controller;

use App\context\IndexContext;
use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\model\EnumEnvironment;
use App\service\RundeckService;
use App\service\UserPreferencesService;
use App\util\UtilsLog;
use App\viewModel\RundeckViewModelFactory;
use Monolog\Logger;
use Throwable;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class RundeckController
{
    public const string ROUTE_RUNDECK = 'rundeck';

    /**
     * @var Logger L'instance du logger pour cette classe.
     */
    private readonly Logger $logger;

    /**
     * Constructeur de la classe RundeckController.
     *
     * @param RundeckViewModelFactory $viewModelFactory Usine pour créer le modèle de vue de public.
     * @param RundeckService $rundeckService Service rundeck.
     * @param UserPreferencesService $userPreferencesService Service de préférences utilisateur.
     * @param IndexContext $context Le contexte de la session public.
     * @param Environment $twig L'environnement Twig pour le rendu des templates.
     * @param LoggerFactory $loggerFactory Usine pour créer le logger.
     */
    public function __construct(
        private readonly RundeckViewModelFactory $viewModelFactory,
        private readonly RundeckService          $rundeckService,
        private readonly UserPreferencesService  $userPreferencesService,
        private readonly IndexContext            $context,
        private readonly Environment             $twig,
        LoggerFactory                            $loggerFactory,
    )
    {
        $this->logger = $loggerFactory->get(self::class);
    }

    /**
     * Affiche la page pour gérer les collections rundeck.
     *
     * @param array $messages Un tableau de messages à afficher à l'utilisateur (notifications, erreurs, etc.).
     * @throws TechnicalException
     */
    public function index(array $messages): void
    {
        $response = $this->rundeckService->findAll();
        $this->viewModelFactory->setResults($response);

        $this->render($messages);
    }

    private function render(array $messages): void
    {
        try {
            $viewModel = $this->viewModelFactory->build($this->context, $messages);
            $viewModel['current_route'] = self::ROUTE_RUNDECK;
            $viewModel['columns_prefs'] = $this->userPreferencesService->get('rundeck_columns', []);
            $viewModel['results'] = []; // Vidé initialement pour chargement ultra-rapide

            echo $this->twig->render(
                'rundeck.html.twig',
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
                case ACTION_GET_DATAGRID_ROWS:
                    $results = $this->rundeckService->findAll();
                    $this->viewModelFactory->setResults($results);
                    $viewModel = $this->viewModelFactory->build($this->context, []);
                    
                    $filters = [];
                    foreach ($_REQUEST as $key => $val) {
                        if (str_starts_with($key, 'filter_')) {
                            $filters[str_replace('filter_', '', $key)] = $val;
                        }
                    }
                    $sortCol = $_REQUEST['sort_column'] ?? '';
                    $sortDir = $_REQUEST['sort_dir'] ?? 'asc';
                    $page = (int)($_REQUEST['p'] ?? 1);
                    $limit = (int)($_REQUEST['rows_per_page'] ?? 15);
                    if ($limit === 1000) $limit = 999999;
                    
                    $paginated = \App\util\DatagridHelper::process($viewModel['results'], $filters, $sortCol, $sortDir, $page, $limit);
                    
                    $html = $this->twig->render('common/_rundeck_rows.html.twig', [
                        'results' => $paginated['items'],
                        'offset' => ($page - 1) * $limit
                    ]);
                    
                    $domainFilter = $filters['domain'] ?? 'all';
                    $allowedSfs = [];
                    foreach ($viewModel['results'] as $item) {
                        if (($domainFilter === 'all' || $domainFilter === '' || $item['domain'] === $domainFilter) && !empty($item['sf'])) {
                            $allowedSfs[] = $item['sf'];
                        }
                    }
                    $allowedSfs = array_values(array_unique($allowedSfs));
                    
                    $response = [
                        'success' => true,
                        'html' => $html,
                        'totalRows' => $paginated['totalRows'],
                        'allowedSfs' => $allowedSfs
                    ];
                    break;
                case ACTION_SAVE_COLUMNS_PREFS:
                    $columns = $input['columns'] ?? [];
                    $this->userPreferencesService->set('rundeck_columns', $columns);
                    $response = ['success' => true];
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