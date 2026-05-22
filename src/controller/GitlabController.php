<?php
declare(strict_types=1);

namespace App\controller;

use App\config\AppConfig;
use App\factory\LoggerFactory;
use App\service\GitlabService;
use Monolog\Logger;
use Throwable;

class GitlabController
{
    /**
     * @var Logger L'instance du logger pour cette classe.
     */
    private readonly Logger $logger;

    /**
     * Constructeur de la classe GitlabController.
     *
     * @param GitlabService $gitlabService Service gitlab.
     * @param AppConfig $appConfig Configuration de l'application.
     * @param LoggerFactory $loggerFactory Usine pour créer le logger.
     */
    public function __construct(
        private readonly GitlabService $gitlabService,
        private readonly AppConfig     $appConfig,
        LoggerFactory                  $loggerFactory
    )
    {
        $this->logger = $loggerFactory->get(self::class);
    }

    public function handleRequest(string $action): string
    {
        try {
            switch ($action) {
                case ACTION_GITLAB_SCAN:
                    $response = $this->gitlabService->scan();
                    break;
                case ACTION_GITLAB_TREE:
                    $projectId = $this->appConfig->getParamConfig()->getGitlabBusinessContractProjectId();
                    $response = $this->gitlabService->getTree($projectId, $_REQUEST['path'] ?? '');
                    break;
                case ACTION_GITLAB_FILE:
                    $projectId = $this->appConfig->getParamConfig()->getGitlabBusinessContractProjectId();
                    $response = $this->gitlabService->getFile($projectId, $_REQUEST['file'] ?? '', 'master');
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