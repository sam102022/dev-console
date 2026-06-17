<?php
declare(strict_types=1);

namespace App\factory;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

/**
 * Classe LoggerFactory
 *
 * Usine (Factory) pour créer et configurer des instances de Logger (Monolog).
 * Elle centralise la configuration du gestionnaire de logs (handler) et du formatage,
 * et fournit des loggers pré-configurés pour différents canaux de l'application.
 */
class LoggerFactory
{
    /**
     * Constructeur de la classe LoggerFactory.
     *
     * @param RotatingFileHandler $handler Le gestionnaire de logs (handler) partagé par tous les loggers.
     */
    public function __construct(
        private readonly RotatingFileHandler $handler
    ) {}

    /**
     * Crée un logger pour un canal spécifique.
     *
     * @param string $channel Le nom du canal (généralement le nom de la classe qui utilise le logger).
     * @return Logger L'instance du logger configurée.
     */
    public function get(string $channel): Logger
    {
        $logger = new Logger($channel);
        $logger->pushHandler($this->handler);

        return $logger;
    }
    
    /**
     * Méthode de fabrique statique pour créer un gestionnaire de logs rotatifs.
     *
     * @param string $logFile Le chemin vers le fichier de log.
     * @param int|string $level Le niveau de log minimum.
     * @return RotatingFileHandler Le gestionnaire de logs configuré.
     */
    public static function createHandler(
        string $logFile,
        int|string $level
    ): RotatingFileHandler {
        $dateFormat = 'Y-m-d\TH:i:sP';
        $output = "[%datetime%] %level_name% - %channel%::%message%\n";

        $formatter = new LineFormatter($output, $dateFormat);

        $handler = new RotatingFileHandler($logFile, 7, $level);
        $handler->setFormatter($formatter);

        return $handler;
    }

    /**
     * Méthode de fabrique statique pour créer un logger complet avec son propre handler.
     *
     * @param string $channel Le nom du canal.
     * @param string $logFile Le chemin vers le fichier de log.
     * @param int|string $level Le niveau de log minimum.
     * @return Logger L'instance du logger configurée.
     */
    public static function create(
        string $channel,
        string $logFile,
        int|string $level
    ): Logger {
        $logger = new Logger($channel);
        $logger->pushHandler(
            self::createHandler($logFile, $level)
        );

        return $logger;
    }
}
