<?php
declare(strict_types=1);

namespace App\service;

use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\util\UtilsLog;
use Monolog\Logger;

class RepositoryService
{
    private Logger $logger;
    private ?\PDO $pdo = null;
    private ?\Symfony\Component\Cache\Adapter\FilesystemAdapter $cache = null;
    private string $path;
    private bool $useSqlite = false;

    private const array STATIC_FILES = [
        'new_relic_urls.json'
    ];

    public function __construct(string $path, LoggerFactory $loggerFactory)
    {
        $this->path = $path;
        $this->logger = $loggerFactory->get(__CLASS__);

        $availableDrivers = class_exists(\PDO::class) ? \PDO::getAvailableDrivers() : [];
        if (in_array('sqlite', $availableDrivers, true)) {
            $this->useSqlite = true;
            if (str_starts_with($path, 'vfs://')) {
                $dbFile = ':memory:';
            } else {
                $dbFile = $path . '/database.sqlite';
            }

            try {
                $this->pdo = new \PDO('sqlite:' . $dbFile);
                $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
                $this->createTables();
            } catch (\PDOException $e) {
                $this->logger->warning(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__) . "Impossible d'initialiser SQLite, repli sur le cache fichiers : " . $e->getMessage());
                $this->useSqlite = false;
            }
        } else {
            $this->logger->warning(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__) . "Extension PDO SQLite non disponible, repli sur le cache fichiers.");
            $this->useSqlite = false;
        }

        if (!$this->useSqlite) {
            $this->cache = new \Symfony\Component\Cache\Adapter\FilesystemAdapter('dev_console_data', 0, $path);
        }
    }

    private function createTables(): void
    {
        if (!$this->useSqlite) {
            return;
        }

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS projects (
                name TEXT PRIMARY KEY,
                serviceName TEXT,
                domain TEXT,
                domainName TEXT,
                sf TEXT,
                cloudGCP INTEGER,
                springBoot TEXT,
                java TEXT,
                techno TEXT,
                subscriptionName TEXT,
                mdmWorkloadVersion TEXT,
                pathLivenessProbe TEXT,
                webUrl TEXT,
                archived INTEGER,
                urlHealthCheck TEXT,
                urlActuatorInfo TEXT,
                urlLogs TEXT,
                urlFronts TEXT,
                urlPubsubs TEXT,
                urlsRundeck TEXT,
                urlsDeploymentGcp TEXT
            );
            CREATE INDEX IF NOT EXISTS idx_projects_archived ON projects(archived);
            CREATE INDEX IF NOT EXISTS idx_projects_domain ON projects(domain);

            CREATE TABLE IF NOT EXISTS gitlab_projects (
                id INTEGER PRIMARY KEY,
                name TEXT,
                description TEXT,
                nameWithNamespace TEXT,
                path TEXT,
                pathWithNamespace TEXT,
                createdAt TEXT,
                defaultBranch TEXT,
                webUrl TEXT,
                archived INTEGER
            );

            CREATE TABLE IF NOT EXISTS rundeck_projects (
                name TEXT PRIMARY KEY,
                domain TEXT,
                sf TEXT,
                category TEXT,
                token TEXT,
                path TEXT,
                projectName TEXT
            );

            CREATE TABLE IF NOT EXISTS cache_store (
                key TEXT PRIMARY KEY,
                value TEXT
            );
        ");
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

        if ($this->useSqlite) {
            if ($filename === 'javaProjects.json') {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM projects");
                return $stmt->fetchColumn() > 0;
            }

            if ($filename === 'gitlabProjects.json') {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM gitlab_projects");
                return $stmt->fetchColumn() > 0;
            }

            if ($filename === 'rundeckObjects.json') {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM rundeck_projects");
                return $stmt->fetchColumn() > 0;
            }

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM cache_store WHERE key = :key");
            $stmt->execute([':key' => $this->sanitizeKey($filename)]);
            return $stmt->fetchColumn() > 0;
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
            } catch (\JsonException $e) {
                $this->logger->error(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
                    . "Erreur lors du décodage JSON du fichier $filename : " . $e->getMessage());
                throw new TechnicalException("Erreur lors du décodage JSON du fichier $filename", 400, $e);
            }
        }

        if ($this->useSqlite) {
            if ($filename === 'javaProjects.json') {
                return $this->getProjects();
            }

            if ($filename === 'gitlabProjects.json') {
                return $this->getGitlabProjects();
            }

            if ($filename === 'rundeckObjects.json') {
                return $this->getRundeckProjects();
            }

            $stmt = $this->pdo->prepare("SELECT value FROM cache_store WHERE key = :key");
            $stmt->execute([':key' => $this->sanitizeKey($filename)]);
            $val = $stmt->fetchColumn();
            if ($val === false) {
                return [];
            }
            return json_decode($val, true) ?? [];
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
            } catch (\JsonException $e) {
                $this->logger->error(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__) . "Error encoding JSON for file $filename: " . $e->getMessage());
                throw new TechnicalException("Error encoding JSON for file $filename", 400, $e);
            }
            return;
        }

        if ($this->useSqlite) {
            if ($filename === 'javaProjects.json') {
                $this->saveProjects($responseJson);
                return;
            }

            if ($filename === 'gitlabProjects.json') {
                $this->saveGitlabProjects($responseJson);
                return;
            }

            if ($filename === 'rundeckObjects.json') {
                $this->saveRundeckProjects($responseJson);
                return;
            }

            $stmt = $this->pdo->prepare("INSERT INTO cache_store (key, value) VALUES (:key, :value) ON CONFLICT(key) DO UPDATE SET value = excluded.value");
            $stmt->execute([
                ':key' => $this->sanitizeKey($filename),
                ':value' => json_encode($responseJson)
            ]);
            return;
        }

        $key = $this->sanitizeKey($filename);
        $this->logger->debug(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__) . "Sauvegarde du cache pour la clef $key");

        $item = $this->cache->getItem($key);
        $item->set($responseJson);
        $this->cache->save($item);
        $this->logger->info(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__) . "Success, saved to cache ($key)");
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

        if ($this->useSqlite) {
            if ($filename === 'javaProjects.json') {
                $this->pdo->exec("DELETE FROM projects");
                return;
            }

            if ($filename === 'gitlabProjects.json') {
                $this->pdo->exec("DELETE FROM gitlab_projects");
                return;
            }

            if ($filename === 'rundeckObjects.json') {
                $this->pdo->exec("DELETE FROM rundeck_projects");
                return;
            }

            $stmt = $this->pdo->prepare("DELETE FROM cache_store WHERE key = :key");
            $stmt->execute([':key' => $this->sanitizeKey($filename)]);
            return;
        }

        $key = $this->sanitizeKey($filename);
        $this->cache->deleteItem($key);
        $this->logger->info(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__) . "Cache supprimé: $key");
    }

    // ----------------------------------------------------------------------
    // SQL Structured Operations
    // ----------------------------------------------------------------------

    public function saveProjects(array $projects): void
    {
        if (!$this->useSqlite) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec("DELETE FROM projects");
            $stmt = $this->pdo->prepare("
                INSERT INTO projects (
                    name, serviceName, domain, domainName, sf, cloudGCP, springBoot, java, techno,
                    subscriptionName, mdmWorkloadVersion, pathLivenessProbe, webUrl, archived,
                    urlHealthCheck, urlActuatorInfo, urlLogs, urlFronts, urlPubsubs, urlsRundeck, urlsDeploymentGcp
                ) VALUES (
                    :name, :serviceName, :domain, :domainName, :sf, :cloudGCP, :springBoot, :java, :techno,
                    :subscriptionName, :mdmWorkloadVersion, :pathLivenessProbe, :webUrl, :archived,
                    :urlHealthCheck, :urlActuatorInfo, :urlLogs, :urlFronts, :urlPubsubs, :urlsRundeck, :urlsDeploymentGcp
                )
            ");

            foreach ($projects as $proj) {
                $stmt->execute([
                    ':name' => $proj['name'],
                    ':serviceName' => $proj['serviceName'] ?? null,
                    ':domain' => $proj['domain'] ?? '',
                    ':domainName' => $proj['domainName'] ?? '',
                    ':sf' => $proj['sf'] ?? '',
                    ':cloudGCP' => $proj['cloudGCP'] ? 1 : 0,
                    ':springBoot' => $proj['springBoot'] ?? null,
                    ':java' => $proj['java'] ?? null,
                    ':techno' => $proj['techno'] ?? null,
                    ':subscriptionName' => $proj['subscriptionName'] ?? null,
                    ':mdmWorkloadVersion' => $proj['mdmWorkloadVersion'] ?? null,
                    ':pathLivenessProbe' => $proj['pathLivenessProbe'] ?? null,
                    ':webUrl' => $proj['webUrl'] ?? '',
                    ':archived' => $proj['archived'] ? 1 : 0,
                    ':urlHealthCheck' => json_encode($proj['urlHealthCheck'] ?? []),
                    ':urlActuatorInfo' => json_encode($proj['urlActuatorInfo'] ?? []),
                    ':urlLogs' => json_encode($proj['urlLogs'] ?? []),
                    ':urlFronts' => json_encode($proj['urlFronts'] ?? []),
                    ':urlPubsubs' => json_encode($proj['urlPubsubs'] ?? []),
                    ':urlsRundeck' => json_encode($proj['urlsRundeck'] ?? []),
                    ':urlsDeploymentGcp' => json_encode($proj['urlsDeploymentGcp'] ?? [])
                ]);
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function saveProject(array $proj): void
    {
        if (!$this->useSqlite) {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO projects (
                name, serviceName, domain, domainName, sf, cloudGCP, springBoot, java, techno,
                subscriptionName, mdmWorkloadVersion, pathLivenessProbe, webUrl, archived,
                urlHealthCheck, urlActuatorInfo, urlLogs, urlFronts, urlPubsubs, urlsRundeck, urlsDeploymentGcp
            ) VALUES (
                :name, :serviceName, :domain, :domainName, :sf, :cloudGCP, :springBoot, :java, :techno,
                :subscriptionName, :mdmWorkloadVersion, :pathLivenessProbe, :webUrl, :archived,
                :urlHealthCheck, :urlActuatorInfo, :urlLogs, :urlFronts, :urlPubsubs, :urlsRundeck, :urlsDeploymentGcp
            ) ON CONFLICT(name) DO UPDATE SET
                serviceName = excluded.serviceName,
                domain = excluded.domain,
                domainName = excluded.domainName,
                sf = excluded.sf,
                cloudGCP = excluded.cloudGCP,
                springBoot = excluded.springBoot,
                java = excluded.java,
                techno = excluded.techno,
                subscriptionName = excluded.subscriptionName,
                mdmWorkloadVersion = excluded.mdmWorkloadVersion,
                pathLivenessProbe = excluded.pathLivenessProbe,
                webUrl = excluded.webUrl,
                archived = excluded.archived,
                urlHealthCheck = excluded.urlHealthCheck,
                urlActuatorInfo = excluded.urlActuatorInfo,
                urlLogs = excluded.urlLogs,
                urlFronts = excluded.urlFronts,
                urlPubsubs = excluded.urlPubsubs,
                urlsRundeck = excluded.urlsRundeck,
                urlsDeploymentGcp = excluded.urlsDeploymentGcp
        ");

        $stmt->execute([
            ':name' => $proj['name'],
            ':serviceName' => $proj['serviceName'] ?? null,
            ':domain' => $proj['domain'] ?? '',
            ':domainName' => $proj['domainName'] ?? '',
            ':sf' => $proj['sf'] ?? '',
            ':cloudGCP' => $proj['cloudGCP'] ? 1 : 0,
            ':springBoot' => $proj['springBoot'] ?? null,
            ':java' => $proj['java'] ?? null,
            ':techno' => $proj['techno'] ?? null,
            ':subscriptionName' => $proj['subscriptionName'] ?? null,
            ':mdmWorkloadVersion' => $proj['mdmWorkloadVersion'] ?? null,
            ':pathLivenessProbe' => $proj['pathLivenessProbe'] ?? null,
            ':webUrl' => $proj['webUrl'] ?? '',
            ':archived' => $proj['archived'] ? 1 : 0,
            ':urlHealthCheck' => json_encode($proj['urlHealthCheck'] ?? []),
            ':urlActuatorInfo' => json_encode($proj['urlActuatorInfo'] ?? []),
            ':urlLogs' => json_encode($proj['urlLogs'] ?? []),
            ':urlFronts' => json_encode($proj['urlFronts'] ?? []),
            ':urlPubsubs' => json_encode($proj['urlPubsubs'] ?? []),
            ':urlsRundeck' => json_encode($proj['urlsRundeck'] ?? []),
            ':urlsDeploymentGcp' => json_encode($proj['urlsDeploymentGcp'] ?? [])
        ]);
    }

    public function getProjects(): array
    {
        if (!$this->useSqlite) {
            return [];
        }

        $stmt = $this->pdo->query("SELECT * FROM projects");
        $rows = $stmt->fetchAll();
        $projects = [];
        foreach ($rows as $row) {
            $row['cloudGCP'] = (bool) $row['cloudGCP'];
            $row['archived'] = (bool) $row['archived'];
            $row['urlHealthCheck'] = json_decode($row['urlHealthCheck'] ?? '[]', true);
            $row['urlActuatorInfo'] = json_decode($row['urlActuatorInfo'] ?? '[]', true);
            $row['urlLogs'] = json_decode($row['urlLogs'] ?? '[]', true);
            $row['urlFronts'] = json_decode($row['urlFronts'] ?? '[]', true);
            $row['urlPubsubs'] = json_decode($row['urlPubsubs'] ?? '[]', true);
            $row['urlsRundeck'] = json_decode($row['urlsRundeck'] ?? '[]', true);
            $row['urlsDeploymentGcp'] = json_decode($row['urlsDeploymentGcp'] ?? '[]', true);
            $projects[] = $row;
        }
        return $projects;
    }

    public function findProjectByName(string $name): ?array
    {
        if ($this->useSqlite) {
            $stmt = $this->pdo->prepare("SELECT * FROM projects WHERE name = :name");
            $stmt->execute([':name' => $name]);
            $row = $stmt->fetch();
            if (!$row) {
                return null;
            }
            $row['cloudGCP'] = (bool) $row['cloudGCP'];
            $row['archived'] = (bool) $row['archived'];
            $row['urlHealthCheck'] = json_decode($row['urlHealthCheck'] ?? '[]', true);
            $row['urlActuatorInfo'] = json_decode($row['urlActuatorInfo'] ?? '[]', true);
            $row['urlLogs'] = json_decode($row['urlLogs'] ?? '[]', true);
            $row['urlFronts'] = json_decode($row['urlFronts'] ?? '[]', true);
            $row['urlPubsubs'] = json_decode($row['urlPubsubs'] ?? '[]', true);
            $row['urlsRundeck'] = json_decode($row['urlsRundeck'] ?? '[]', true);
            $row['urlsDeploymentGcp'] = json_decode($row['urlsDeploymentGcp'] ?? '[]', true);
            return $row;
        }

        // Fallback search in filesystem cache array
        $projects = $this->read('javaProjects.json');
        return array_find($projects, fn($p) => ($p['name'] ?? null) === $name);
    }

    public function saveGitlabProjects(array $projects): void
    {
        if (!$this->useSqlite) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec("DELETE FROM gitlab_projects");
            $stmt = $this->pdo->prepare("
                INSERT INTO gitlab_projects (
                    id, name, description, nameWithNamespace, path, pathWithNamespace, createdAt, defaultBranch, webUrl, archived
                ) VALUES (
                    :id, :name, :description, :nameWithNamespace, :path, :pathWithNamespace, :createdAt, :defaultBranch, :webUrl, :archived
                )
            ");
            foreach ($projects as $proj) {
                $stmt->execute([
                    ':id' => $proj['id'],
                    ':name' => $proj['name'],
                    ':description' => $proj['description'] ?? null,
                    ':nameWithNamespace' => $proj['nameWithNamespace'],
                    ':path' => $proj['path'],
                    ':pathWithNamespace' => $proj['pathWithNamespace'],
                    ':createdAt' => $proj['createdAt'],
                    ':defaultBranch' => $proj['defaultBranch'],
                    ':webUrl' => $proj['webUrl'],
                    ':archived' => $proj['archived'] ? 1 : 0
                ]);
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getGitlabProjects(): array
    {
        if (!$this->useSqlite) {
            return [];
        }

        $stmt = $this->pdo->query("SELECT * FROM gitlab_projects");
        $rows = $stmt->fetchAll();
        $projects = [];
        foreach ($rows as $row) {
            $row['archived'] = (bool) $row['archived'];
            $projects[] = $row;
        }
        return $projects;
    }

    public function saveRundeckProjects(array $projects): void
    {
        if (!$this->useSqlite) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec("DELETE FROM rundeck_projects");
            $stmt = $this->pdo->prepare("
                INSERT INTO rundeck_projects (
                    name, domain, sf, category, token, path, projectName
                ) VALUES (
                    :name, :domain, :sf, :category, :token, :path, :projectName
                )
            ");
            foreach ($projects as $proj) {
                $stmt->execute([
                    ':name' => $proj['name'],
                    ':domain' => $proj['domain'] ?? '',
                    ':sf' => $proj['sf'] ?? '',
                    ':category' => $proj['category'] ?? null,
                    ':token' => json_encode($proj['token'] ?? []),
                    ':path' => $proj['path'] ?? null,
                    ':projectName' => $proj['projectName'] ?? null
                ]);
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getRundeckProjects(): array
    {
        if (!$this->useSqlite) {
            return [];
        }

        $stmt = $this->pdo->query("SELECT * FROM rundeck_projects");
        $rows = $stmt->fetchAll();
        $projects = [];
        foreach ($rows as $row) {
            $row['token'] = json_decode($row['token'] ?? '[]', true);
            $projects[] = $row;
        }
        return $projects;
    }
}
