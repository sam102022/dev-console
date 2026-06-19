<?php
declare(strict_types=1);

namespace App\router;

use App\context\IndexContext;
use App\controller\GitlabController;
use App\controller\IndexController;
use App\controller\MonitoringController;
use App\controller\PostmanController;
use App\controller\RundeckController;
use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\service\UtilsService;
use App\util\UtilsLog;
use Monolog\Logger;
use Twig\Environment;

/**
 * Classe IndexRouter
 *
 * Routeur principal pour la section public.
 * Il gère l'état de la session, distribue les actions aux contrôleurs
 * et déclenche l'affichage de la page public.
 */
final class IndexRouter
{
    private Logger $logger;
    private array $messages;

    public function __construct(
        private readonly IndexController      $indexController,
        private readonly GitlabController     $gitlabController,
        private readonly MonitoringController $monitoringController,
        private readonly PostmanController    $postmanController,
        private readonly RundeckController    $rundeckController,
        private readonly Environment          $twig,
        private readonly IndexContext         $indexContext,
        LoggerFactory                         $loggerFactory,
    )
    {
        $this->logger = $loggerFactory->get(self::class);
        $this->messages = $this->indexContext->initMessages();
    }

    /**
     * Gère la requête entrante pour la section d'administration.
     * Met à jour l'état de la session, route les actions et affiche la page.
     * @throws TechnicalException
     */
    public function dispatch(): void
    {
        $_SESSION['theme'] = $_SESSION['theme'] ?? THEME_DEFAULT;
        if (isset($_GET['theme'])) {
            $_SESSION['theme'] = $_GET['theme'];
        }

        $messages = $this->indexContext->initMessages();

        if (isset($_REQUEST['action'])) {
            $action = $_REQUEST['action'];

            switch ($action) {
                case ACTION_PURGE_CACHE:
                    $this->indexController->purgeCache($messages);
                    return; // Fin de l'exécution après le rendu
                case ACTION_GITLAB_FILE:
                case ACTION_GITLAB_SCAN:
                case ACTION_GITLAB_TREE:
                case ACTION_NEW_RELIC_URL:
                    echo $this->gitlabController->handleRequest($action);
                    break;
                case ACTION_POSTMAN_WORKSPACES:
                case ACTION_POSTMAN_CREATE_WORKSPACE:
                case ACTION_POSTMAN_CREATE_ENVIRONMENT:
                case ACTION_POSTMAN_IMPORT_OPENAPI:
                case ACTION_POSTMAN_GET_WORKSPACE_DETAILS:
                    echo $this->postmanController->handleRequest($action);
                    break;
                case ACTION_MONITORING_GET_DATA:
                    echo $this->monitoringController->handleRequest($action);
                    break;
                default:
                    $this->notFound($action);
            }
        }
        if (isset($_REQUEST['page'])) {
            $page = $_REQUEST['page'];
            switch ($page) {
                case MonitoringController::ROUTE_MONITORING:
                    $this->monitoringController->index($messages);
                    break;
                case PostmanController::ROUTE_POSTMAN:
                    $this->postmanController->index($messages);
                    break;
                case RundeckController::ROUTE_RUNDECK:
                    $this->rundeckController->index($messages);
                    break;
                default:
                    $this->indexController->index($messages);
            }
        } else if (!isset($_REQUEST['action'])) {
            // Si pas d'action (ou si c'était pas un purge_cache) et pas de page, on affiche l'index
            $this->indexController->index($messages);
        }
    }

    /**
     * Gère les actions non trouvées.
     * @param string $action L'action non trouvée.
     * @throws TechnicalException
     */
    private function notFound(string $action): void
    {
        http_response_code(404);

        $msg = "Action '$action' non disponible";
        $this->logger->error(UtilsLog::prefixLog(__CLASS__, __FUNCTION__, __LINE__) . $msg);
        echo UtilsService::buildAlertHtml($this->twig, [
            LEVEL_LOG_INFO => [
                $msg
            ]
        ]);
    }
}