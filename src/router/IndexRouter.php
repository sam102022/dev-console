<?php
declare(strict_types=1);

namespace App\router;

use App\context\IndexContext;
use App\controller\AuthController;
use App\controller\GitlabController;
use App\controller\IndexController;
use App\controller\MonitoringController;
use App\controller\PostmanController;
use App\controller\RundeckController;
use App\controller\UserAdminController;
use App\controller\SettingsController;
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
        private readonly AuthController       $authController,
        private readonly UserAdminController  $userAdminController,
        private readonly SettingsController   $settingsController,
        private readonly Environment          $twig,
        private readonly IndexContext         $indexContext,
        LoggerFactory                         $loggerFactory,
    )
    {
        $this->logger = $loggerFactory->get(self::class);
        $this->messages = $this->indexContext->initMessages();
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        $this->twig->addGlobal('session', $_SESSION);
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

        $isLoggedIn = isset($_SESSION['user_id']);
        $role = $_SESSION['user_role'] ?? 'ROLE_USER';

        $page = $_REQUEST['page'] ?? 'index';
        $action = $_REQUEST['action'] ?? null;

        $publicPages = [AuthController::ROUTE_LOGIN, AuthController::ROUTE_FORGOT_PASSWORD, AuthController::ROUTE_RESET_PASSWORD];
        $publicActions = [AuthController::ACTION_LOGIN_SUBMIT, AuthController::ACTION_FORGOT_PASSWORD_SUBMIT, AuthController::ACTION_RESET_PASSWORD_SUBMIT];

        if (!$isLoggedIn && !in_array($page, $publicPages) && !in_array($action, $publicActions)) {
            header('Location: ?page=' . AuthController::ROUTE_LOGIN);
            exit;
        }

        if ($isLoggedIn && $role !== 'ROLE_ADMIN') {
            if ($page === UserAdminController::ROUTE_USERS || in_array($action, [UserAdminController::ACTION_CREATE_USER, UserAdminController::ACTION_UPDATE_USER, UserAdminController::ACTION_DELETE_USER])) {
                http_response_code(403);
                echo "403 Forbidden";
                exit;
            }
        }

        if (isset($_REQUEST['action'])) {
            $action = $_REQUEST['action'];

            switch ($action) {
                case ACTION_PURGE_CACHE:
                    $this->indexController->purgeCache($messages);
                    return;
                case AuthController::ACTION_LOGIN_SUBMIT:
                    $this->authController->loginSubmit();
                    return;
                case AuthController::ACTION_FORGOT_PASSWORD_SUBMIT:
                    $this->authController->forgotPasswordSubmit();
                    return;
                case AuthController::ACTION_RESET_PASSWORD_SUBMIT:
                    $this->authController->resetPasswordSubmit();
                    return;
                case SettingsController::ACTION_SAVE_SETTINGS:
                    $this->settingsController->save();
                    return;
                case UserAdminController::ACTION_CREATE_USER:
                case UserAdminController::ACTION_UPDATE_USER:
                case UserAdminController::ACTION_DELETE_USER:
                    echo $this->userAdminController->handleRequest($action);
                    return;
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
                case ACTION_SAVE_COLUMNS_PREFS:
                    $page = $_REQUEST['page'] ?? '';
                    if ($page === RundeckController::ROUTE_RUNDECK) {
                        echo $this->rundeckController->handleRequest($action);
                    } else {
                        echo $this->monitoringController->handleRequest($action);
                    }
                    break;
                case ACTION_MONITORING_GET_DATA:
                    echo $this->monitoringController->handleRequest($action);
                    break;
                case ACTION_GET_DATAGRID_ROWS:
                    $page = $_REQUEST['page'] ?? 'index';
                    switch ($page) {
                        case MonitoringController::ROUTE_MONITORING:
                            echo $this->monitoringController->handleRequest($action);
                            break;
                        case RundeckController::ROUTE_RUNDECK:
                            echo $this->rundeckController->handleRequest($action);
                            break;
                        case UserAdminController::ROUTE_USERS:
                            echo $this->userAdminController->handleRequest($action);
                            break;
                        default:
                            echo $this->indexController->handleRequest($action);
                            break;
                    }
                    break;
                default:
                    $this->notFound($action);
            }
            return;
        }

        if (isset($_REQUEST['page'])) {
            $page = $_REQUEST['page'];
            switch ($page) {
                case AuthController::ROUTE_LOGIN:
                    $this->authController->login();
                    break;
                case AuthController::ROUTE_LOGOUT:
                    $this->authController->logout();
                    break;
                case AuthController::ROUTE_FORGOT_PASSWORD:
                    $this->authController->forgotPassword();
                    break;
                case AuthController::ROUTE_RESET_PASSWORD:
                    $this->authController->resetPassword();
                    break;
                case SettingsController::ROUTE_SETTINGS:
                    $this->settingsController->index();
                    break;
                case UserAdminController::ROUTE_USERS:
                    $this->userAdminController->index($messages);
                    break;
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
        } else {
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
