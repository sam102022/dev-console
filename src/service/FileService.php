<?php
declare(strict_types=1);

namespace App\service;

use App\config\AppConfig;
use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use Monolog\Logger;

class FileService
{
    private Logger $logger;

    public function __construct(
        private readonly AppConfig $appConfig,
        LoggerFactory $loggerFactory)
    {
        $this->logger = $loggerFactory->get(__CLASS__);
    }

    /**
     * Initialise les chemins de répertoires nécessaires à l'application.
     * @throws TechnicalException Exception possible
     */
    public static function initPaths(): void
    {
        $pathsToCreate = [PATH_CACHE, PATH_CACHE_FILE, PATH_CACHE_USER, PATH_IMAGES, PATH_LOGS];
        $pathFull = [];
        foreach ($pathsToCreate as $pathToCreateTmp) {
            $pathFull[] = dirname(__DIR__, 2) . '/' . $pathToCreateTmp;
        }
        self::checkPaths($pathFull);
    }

    /**
     * Vérifie et crée les répertoires si nécessaire.
     * @param array $pathsToCreate Tableau des chemins à vérifier et créer.
     * @throws TechnicalException Exception possible
     */
    public static function checkPaths(array $pathsToCreate): void
    {
        foreach ($pathsToCreate as $pathToCreateTmp) {
            if (!file_exists($pathToCreateTmp)) {
                self::createDirectory($pathToCreateTmp);
            }
        }
    }

    /**
     * Crée un répertoire s'il n'existe pas.
     *
     * @param string $path Le chemin du répertoire à créer.
     * @param int $permissions Les permissions du dossier (par défaut : 0755).
     * @throws TechnicalException Exception possible
     */
    public static function createDirectory(string $path, int $permissions = 0755): bool
    {
        if (!mkdir($path, $permissions, true) && !is_dir($path)) {
            throw TechnicalException::createWithMessage("Impossible de créer le répertoire : $path");
        }
        return true;
    }

    /**
     * Vérifie si un fichier de verrouillage existe.
     */
    public function isLocked(): bool
    {
        return file_exists($this->getCacheFileLock());
    }

    /**
     * Retourne le chemin complet du fichier de verrouillage.
     */
    private function getCacheFileLock(): string
    {
        return $this->appConfig->getPathCacheFile() . '/lock';
    }
}