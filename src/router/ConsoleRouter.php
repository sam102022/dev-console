<?php
declare(strict_types=1);

namespace App\router;

use App\command\ScanCommand;
use App\ContainerConsole;
use App\factory\LoggerFactory;
use App\service\FileService;
use App\util\UtilsLog;
use Exception;
use Monolog\Logger;
use stdClass;

const NAMESPACE_COMMAND = 'App\\command';

/**
 * Classe ConsoleRouter
 *
 * Point d'entrée et routeur pour les commandes exécutées en ligne de commande (CLI).
 */
class ConsoleRouter
{
    private Logger $logger;

    public function __construct(
        private readonly FileService $fileService,
        private readonly ContainerConsole $containerConsole,
        LoggerFactory $loggerFactory
    ) {
        $this->logger = $loggerFactory->get(__CLASS__);
    }

    /**
     * Distribue la requête de la console à l'action appropriée.
     *
     * @param array $argv Les arguments de la ligne de commande.
     */
    public function dispatch(array $argv): void
    {
        // php bin/console.php scheduled_command

        if ($this->fileService->isLocked()) {
            //$this->logger->warning(UtilsLog::prefixLog(__CLASS__, __FUNCTION__, __LINE__) . "Le fichier lock est présent, les commandes ne seront pas executées");
            return;
        }

        if (!empty($argv) && count($argv) > 1 && $argv[1]) {

            $action = $argv[1];
            //$this->logger->info(UtilsLog::prefixLog(__CLASS__, __FUNCTION__, __LINE__) . "action:$action");

            $output = [];

            if ($action === ScanCommand::NAME) {
                $options = $this->buildOptions(array_slice($argv, 2, count($argv) - 2));
                $msg = "Demande d'exécution immédiate pour la commande : " . $action . ', options:' . json_encode($options);
                $this->logger->info(UtilsLog::prefixLog(__CLASS__, __FUNCTION__, __LINE__) . $msg);

                try {
                    $command = new stdClass();
                    $command->command = $action;
                    $command->name = "Scan des projets Gitlab";
                    $command->arguments = json_encode($options);
                    $this->executeCommand($command, $output);
                } catch (Exception $e) {
                    $this->logger->error(UtilsLog::prefixLog(__CLASS__, __FUNCTION__, __LINE__) . "Erreur : " . $e->getMessage());
                }
                return;
            }
        } else {
            echo "Usage : php bin/console.php <action> <option>" . PHP_EOL;
            echo "	Ex : php bin/console.php ScanCommand" . PHP_EOL;
        }

        http_response_code(404);
    }


    /**
     * Transforme un tableau d'arguments de ligne de commande en un tableau associatif d'options.
     *
     * @param array $input Tableau d'arguments (ex: ["-t", "streamsInfos", "-s", "1"]).
     */
    private function buildOptions(array $input): array
    {
        $output = [];
        $tmp = [];

        for ($i = 0, $iMax = count($input); $i < $iMax; $i += 2) {
            $tmp[str_replace('-', '', $input[$i])] = $input[$i + 1];
        }

        $output[] = $tmp;

        return $output[0];
    }

    /**
     * Exécute une commande ad-hoc (non planifiée).
     *
     * @param stdClass $scheduledCommand
     * @param array $output
     * @throws Exception
     */
    private function executeCommand(
        stdClass $scheduledCommand,
        array $output,
    ): void {
        $cmd = $scheduledCommand->command . ' ' . $scheduledCommand->arguments;

        try {
            $this->logger->debug(UtilsLog::prefixLog(__CLASS__, __FUNCTION__, __LINE__) . 'Execute : ' . $cmd);

            $msg = 'Execute arguments : ' . mb_convert_encoding($scheduledCommand->arguments, 'ISO-8859-1', 'UTF-8');
            $this->logger->debug(UtilsLog::prefixLog(__CLASS__, __FUNCTION__, __LINE__) . $msg);

            $arguments = json_decode($scheduledCommand->arguments, true, 512, JSON_THROW_ON_ERROR);

            $commandName = NAMESPACE_COMMAND . '\\' . $scheduledCommand->command;
            $command = $this->containerConsole->get($commandName);

            $command->execute($arguments, $output);

            $result = (int) $output['result'];

            if (isset($output['errors'])) {
                $this->logger->error(UtilsLog::prefixLog(__CLASS__, __FUNCTION__, __LINE__) . json_encode($output['errors']));
            }
        } catch (Exception $e) {
            $this->logger->error(UtilsLog::prefixLog(__CLASS__, __FUNCTION__, __LINE__) . $e->getMessage());
            $result = -1;
        }

        $msg = 'Commande "' . $scheduledCommand->name . '" terminée ' . (($result === 1) ? 'sans' : 'avec') . ' erreurs';
        if ($result === 1) {
            $this->logger->info(UtilsLog::prefixLog(__CLASS__, __FUNCTION__, __LINE__) . $msg);
        } else {
            $this->logger->error(UtilsLog::prefixLog(__CLASS__, __FUNCTION__, __LINE__) . $msg);
        }
    }
}
