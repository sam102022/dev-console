<?php
declare(strict_types=1);

namespace App\command;

use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\service\GitlabService;
use App\util\UtilsLog;
use Exception;
use Monolog\Logger;

/**
 * Classe ScanCommand
 *
 * Représente une commande pour scanner les projets GitLab et les sauvegarder dans la base de données.
 * Cette commande est conçue pour être exécutée en arrière-plan ou via la console.
 */
class ScanCommand
{
    /**
     * Nom de la commande.
     */
    public const NAME = 'ScanCommand';

    /**
     * Code de résultat pour une exécution réussie.
     */
    public const RESULT_OK = 1;

    /**
     * Code de résultat pour une exécution échouée.
     */
    public const RESULT_KO = -1;

    /**
     * @var Logger L'instance du logger pour cette classe.
     */
    private readonly Logger $log;

    /**
     * Constructeur de la classe DownloadCommand.
     *
     * @param GitlabService $gitlabService Service pour les interactions avec Gitlab.
     * @param LoggerFactory $loggerFactory Usine pour créer des instances de Logger.
     */
    public function __construct(
        private readonly GitlabService $gitlabService,
        LoggerFactory                  $loggerFactory
    )
    {
        $this->log = $loggerFactory->get(self::class);
    }

    /**
     * Exécute la commande de scan des projets gitlab.
     *
     * @param array $input Paramètres d'entrée contenant les informations nécessaires au téléchargement
     *                     (offlineId, streamId, name, type, extension, cover, userId).
     * @param array $output Tableau de sortie pour les messages de résultat et les erreurs, passé par référence.
     * @throws Exception Si une erreur inattendue survient.
     */
    public function execute(array $input, array &$output): void
    {
        $this->log->debug(UtilsLog::prefixLog(self::class, __FUNCTION__, __LINE__) . 'input: ' . json_encode($input));

        // Vérification de la présence des paramètres obligatoires
        $params = [];
        $output['result'] = self::RESULT_KO;

        foreach ($params as $param) {
            if (!isset($input[$param])) {
                $msg = "Paramètre '$param' non trouvé";
                $this->log->error(UtilsLog::prefixLog(self::class, __FUNCTION__, __LINE__) . $msg);
                $output['errors'][LEVEL_LOG_ERROR][] = $msg;
                return;
            }
        }
        //$userId = (int) $input['userId'];

        $this->log->debug(UtilsLog::prefixLog(self::class, __FUNCTION__, __LINE__) .
            "Scan des projets gitlab...");

        try {
            $projects = $this->gitlabService->scan();

            if ($projects === null) {
                $msg = 'Aucun projets trouvés';
                $this->log->error(UtilsLog::prefixLog(self::class, __FUNCTION__, __LINE__) . $msg);
                return;
            }
            $output['result'] = self::RESULT_OK;
        } catch (TechnicalException $e) {
            $msg = 'Erreur lors du scan des projets gitlab';
            $output['errors'][LEVEL_LOG_ERROR][] = $msg;
            $this->log->error(UtilsLog::prefixLog(self::class, __FUNCTION__, __LINE__) . $msg . ' : ' . $e->getMessage());
        }
    }
}
