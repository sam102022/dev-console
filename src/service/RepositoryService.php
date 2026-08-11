<?php
declare(strict_types=1);

namespace App\service;

use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\util\UtilsLog;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class RepositoryService
{
    private Logger $logger;
    private FilesystemAdapter $cache;

    public function __construct(string $path, LoggerFactory $loggerFactory)
    {
        $this->cache = new FilesystemAdapter('dev_console_data', 0, $path);
        $this->logger = $loggerFactory->get(__CLASS__);
    }

    private function sanitizeKey(string $filename): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $filename);
    }

    public function isFileExists(string $filename): bool
    {
        return $this->cache->hasItem($this->sanitizeKey($filename));
    }

    public function read(string $filename): array
    {
        $key = $this->sanitizeKey($filename);
        $item = $this->cache->getItem($key);
        
        if (!$item->isHit()) {
            return [];
        }

        $data = $item->get();
        return is_array($data) ? $data : [];
    }

    public function save(array $responseJson, string $filename): void
    {
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
        $key = $this->sanitizeKey($filename);
        $this->cache->deleteItem($key);
        $this->logger->info(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
            . "Cache supprimé: $key");
    }
}