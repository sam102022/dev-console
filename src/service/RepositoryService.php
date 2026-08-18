<?php
declare(strict_types=1);

namespace App\service;

use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\util\UtilsLog;
use JsonException;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class RepositoryService
{
    private Logger $logger;
    private FilesystemAdapter $cache;
    private string $path;

    private const array STATIC_FILES = [
        'rundeckObjects.json',
        'new_relic_urls.json'
    ];

    public function __construct(string $path, LoggerFactory $loggerFactory)
    {
        $this->path = $path;
        $this->cache = new FilesystemAdapter('dev_console_data', 0, $path);
        $this->logger = $loggerFactory->get(__CLASS__);
    }

    private function sanitizeKey(string $filename): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $filename);
    }

    private function isStaticFile(string $filename): bool
    {
        return in_array($filename, self::STATIC_FILES, true);
    }

    private function getStaticFilePath(string $filename): string
    {
        return $this->path . '/' . $filename;
    }

    public function isFileExists(string $filename): bool
    {
        if ($this->isStaticFile($filename)) {
            return file_exists($this->getStaticFilePath($filename));
        }
        return $this->cache->hasItem($this->sanitizeKey($filename));
    }

    /**
     * @throws TechnicalException
     */
    public function read(string $filename): array
    {
        if ($this->isStaticFile($filename)) {
            $pathFile = $this->getStaticFilePath($filename);
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

        $key = $this->sanitizeKey($filename);
        $item = $this->cache->getItem($key);
        
        if (!$item->isHit()) {
            return [];
        }

        $data = $item->get();
        return is_array($data) ? $data : [];
    }

    /**
     * @throws TechnicalException
     */
    public function save(array $responseJson, string $filename): void
    {
        if ($this->isStaticFile($filename)) {
            $pathFile = $this->getStaticFilePath($filename);
            try {
                $jsonData = json_encode($responseJson, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
                if (file_put_contents($pathFile, $jsonData) === false) {
                    $this->logger->error(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__) . "Cannot write to file ($pathFile)");
                    throw new TechnicalException("Cannot write to file ($pathFile)");
                }
                $this->logger->info(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__) . "Success, wrote to static file ($pathFile)");
            } catch (JsonException $e) {
                $this->logger->error(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__) . "Error encoding JSON for file $filename: " . $e->getMessage());
                throw new TechnicalException("Error encoding JSON for file $filename", 400, $e);
            }
            return;
        }

        $key = $this->sanitizeKey($filename);
        $this->logger->debug(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
            . "Sauvegarde du cache pour la clef $key");

        $item = $this->cache->getItem($key);
        $item->set($responseJson);
        $this->cache->save($item);

        $this->logger->info(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
            . "Success, saved to cache ($key)");
    }

    public function delete(string $filename): void
    {
        if ($this->isStaticFile($filename)) {
            $pathFile = $this->getStaticFilePath($filename);
            if (file_exists($pathFile)) {
                unlink($pathFile);
                $this->logger->info(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__) . "Fichier statique supprimé: $filename");
            }
            return;
        }

        $key = $this->sanitizeKey($filename);
        $this->cache->deleteItem($key);
        $this->logger->info(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
            . "Cache supprimé: $key");
    }
}