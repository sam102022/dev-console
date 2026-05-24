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
     * @throws GuzzleException
     */
    public function index(array $messages): void
    {
        try {
            $response = $this->gitlabService->scan();
            $this->viewModelFactory->setResults($response);
        } catch (Exception $e) {
            $this->logger->error(UtilsLog::prefixLog(self::class, __FUNCTION__, __LINE__) . $e->getMessage());
            $messages[MESSAGES_SCAN_RESULTS] = [
                LEVEL_LOG_ERROR => [
                    'Une erreur est survenue lors du scan des projets GitLab.'
                ]
            ];
        }

        $this->render($messages);
    }

    /**
     * @throws GuzzleException
     */
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
            $viewModel['current_route'] = 'index';
            echo $this->twig->render(
                'index.html.twig',
                $viewModel
            );
        } catch (LoaderError | RuntimeError | SyntaxError | TechnicalException $e) {
            $this->logger->error(UtilsLog::prefixLog(self::class, __FUNCTION__, __LINE__) . $e->getMessage());
        }
    }

}