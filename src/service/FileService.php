<?php
declare(strict_types=1);

namespace App\service;

use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\util\UtilsLog;
use JsonException;
use Monolog\Logger;

class FileService
{
    private Logger $logger;
    private string $path;

    public function __construct(string $path, LoggerFactory $loggerFactory)
    {
        $this->path = $path;
        $this->logger = $loggerFactory->get(__CLASS__);
    }

    /**
     * Initialise les chemins de répertoires nécessaires à l'application.
     * @throws TechnicalException Exception possible
     */
    public static function initPaths(): void
    {
        $pathsToCreate = [PATH_IMAGES, PATH_LOGS];
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

    private function getPath(string $filename): string
    {
        return $this->path . "/$filename";
    }

    public function isFileExists(string $filename): bool
    {
        $pathFile = $this->getPath($filename);
        return file_exists($pathFile);
    }

    /**
     * @throws TechnicalException
     */
    public function read(string $filename): array
    {
        $pathFile = $this->getPath($filename);
        if (!is_readable($pathFile)) {
            return [];
        }

        $data = file_get_contents($pathFile);
        if ($data === false) {
            $this->logger->error(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__) . "Erreur lors de la lecture du fichier $pathFile");
            throw new TechnicalException("Erreur lors de la lecture du fichier $pathFile");
        }

        if (empty(trim($data))) {
            return [];
        }

        try {
            return json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->logger->error(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
                . "Erreur lors du décodage JSON du fichier $filename : " . $e->getMessage());
            throw new TechnicalException("Erreur lors du décodage JSON du fichier $filename", 400, $e);
        }
    }

    /**
     * @throws TechnicalException
     */
    public function save(array $responseJson, string $filename): void
    {
        $this->logger->debug(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
            . "Sauvegarde du fichier $filename");

        $pathFile = $this->getPath($filename);

        try {
            $jsonData = json_encode($responseJson, JSON_THROW_ON_ERROR); //  | JSON_PRETTY_PRINT
            if (file_put_contents($pathFile, $jsonData) === false) {
                $this->logger->error(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
                    . "Cannot write to file ($pathFile)");
                throw new TechnicalException("Cannot write to file ($pathFile)");
            }

            $this->logger->info(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
                . "Success, wrote to file ($pathFile)");

        } catch (JsonException $e) {
            $this->logger->error(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
                . "Error encoding JSON for file $filename: " . $e->getMessage());
            throw new TechnicalException("Error encoding JSON for file $filename", 400, $e);
        }
    }

    public function delete(string $filename): void
    {
        $pathFile = $this->getPath($filename);
        if (file_exists($pathFile)) {
            unlink($pathFile);
            $this->logger->info(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
                . "Fichier supprimé: $filename");
        }
    }
}