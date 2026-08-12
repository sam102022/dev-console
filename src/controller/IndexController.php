<?php
declare(strict_types=1);

namespace App\controller;

use App\context\IndexContext;
use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\service\GitlabService;
use App\util\UtilsLog;
use App\viewModel\IndexViewModelFactory;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Logger;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Classe IndexController
 *
 * Contrôleur principal pour la section public de l'application.
 */
class IndexController
{
    public const string ROUTE_INDEX = 'index';

    /**
     * @var Logger L'instance du logger pour cette classe.
     */
    private readonly Logger $logger;

    /**
     * Constructeur de la classe AdminController.
     *
     * @param IndexViewModelFactory $viewModelFactory Usine pour créer le modèle de vue de public.
     * @param IndexContext $context Le contexte de la session public.
     * @param GitlabService $gitlabService Service gitlab.
     * @param Environment $twig L'environnement Twig pour le rendu des templates.
     * @param LoggerFactory $loggerFactory Usine pour créer le logger.
     */
    public function __construct(
        private readonly IndexViewModelFactory $viewModelFactory,
        private readonly IndexContext $context,
        private readonly GitlabService $gitlabService,
        private readonly Environment $twig,
        LoggerFactory $loggerFactory
    ) {
        $this->logger = $loggerFactory->get(self::class);
    }

    /**
     * Affiche la page principale.
     *
     * @param array $messages Un tableau de messages à afficher à l'utilisateur (notifications, erreurs, etc.).
     */
    public function index(array $messages): void
    {
        try {
            $response = $this->gitlabService->scan();
            $this->viewModelFactory->setResults($response);
        } catch (GuzzleException | Exception $e) {
            $this->logger->error(UtilsLog::prefixLog(self::class, __FUNCTION__, __LINE__) . $e->getMessage());
            $messages[MESSAGES_SCAN_RESULTS] = [
                LEVEL_LOG_ERROR => [
                    'Une erreur est survenue lors du scan des projets GitLab.'
                ]
            ];
        }

        $this->render($messages);
    }

    public function purgeCache(array $messages): void
    {
        $this->gitlabService->purgeCache();

        // On force le reload de la page d'accueil avec message
        $messages[MESSAGES_SCAN_RESULTS] = [
            LEVEL_LOG_INFO => [
                'Cache supprimé avec succès.'
            ]
        ];

        $this->index($messages);
    }

    private function render(array $messages): void
    {
        try {
            $viewModel = $this->viewModelFactory->build($this->context, $messages);
            $viewModel['current_route'] = self::ROUTE_INDEX;
            $viewModel['results'] = []; // Vidé initialement pour chargement ultra-rapide
            echo $this->twig->render(
                'index.html.twig',
                $viewModel
            );
        } catch (LoaderError | RuntimeError | SyntaxError | TechnicalException $e) {
            $this->logger->error(UtilsLog::prefixLog(self::class, __FUNCTION__, __LINE__) . $e->getMessage());
        }
    }

    public function handleRequest(string $action): string
    {
        $input = json_decode(file_get_contents("php://input"), true) ?? [];

        try {
            http_response_code(200);
            switch ($action) {
                case ACTION_GET_DATAGRID_ROWS:
                    $results = $this->gitlabService->scan();
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
                    
                    $html = $this->twig->render('common/_index_rows.html.twig', [
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

                default:
                    http_response_code(400);
                    $response = ['error' => 'Action inconnue'];
            }
        } catch (\Throwable $e) {
            http_response_code(500);
            $response = ['error' => $e->getMessage()];
        }

        return json_encode($response);
    }

}