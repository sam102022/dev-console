<?php
declare(strict_types=1);

namespace App\model;

use InvalidArgumentException;

/**
 * Classe ParamConfig
 *
 * Représente un objet de transfert de données (DTO) pour les paramètres de configuration
 * bruts chargés depuis les fichiers d'environnement.
 */
class ParamConfig extends AbstractModel
{
    /** @var string L'hôte de la base de données. */
    private string $databaseHost;

    /** @var int Le port de la base de données. */
    private int $databasePort;

    /** @var string Le nom de la base de données. */
    private string $databaseName;

    /** @var string Le nom d'utilisateur pour la base de données. */
    private string $databaseUser;

    /** @var string Le mot de passe pour la base de données. */
    private string $databasePassword;

    /** @var string L'URL du serveur. */
    private string $urlServer;

    /** @var string L'adresse IP locale. */
    private string $ipLocal;

    /** @var int Le port local. */
    private int $portLocal;

    /** @var string La langue par défaut. */
    private string $language = 'fr';

    /** @var string L'URL du serveur gitlab. */
    private string $gitlabUrl;

    /** @var string L'URL du serveur gitlab. */
    private string $gitlabToken;

    /** @var int L'ID du projet de contrat commercial GitLab. */
    private int $gitlabBusinessContractProjectId;

    /** @var string Le chemin par défaut du groupe GitLab. */
    private string $gitlabPathGroupDefault;

    /** @var string L'API Key de Postman. */
    private string $postmanApiKey;

    /** @var string L'URL de l'API Postman. */
    private string $postmanApiUrl;

    /** @var array Les projets migrés sur GKE séparés par une virgule. */
    private array $projectsInGke;

    /** @var array Les projets à exclure séparés par une virgule. */
    private array $excludeProjects;

    /** @var string Le token e107. */
    private string $tokenE107;

    /** @var ParamNewRelic Les paramètres New Relic. */
    private ParamNewRelic $paramNewRelic;


    public function getDatabaseHost(): string
    {
        return $this->databaseHost;
    }

    public function getDatabasePort(): int
    {
        return $this->databasePort;
    }

    public function getDatabaseName(): string
    {
        return $this->databaseName;
    }

    public function getDatabaseUser(): string
    {
        return $this->databaseUser;
    }

    public function getDatabasePassword(): string
    {
        return $this->databasePassword;
    }

    public function getUrlServer(): string
    {
        return $this->urlServer;
    }

    public function getIpLocal(): string
    {
        return $this->ipLocal;
    }

    public function getPortLocal(): int
    {
        return $this->portLocal;
    }

    /**
     * @return string
     */
    public function getGitlabUrl(): string
    {
        return $this->gitlabUrl;
    }

    /**
     * @return string
     */
    public function getGitlabToken(): string
    {
        return $this->gitlabToken;
    }

    public function getGitlabBusinessContractProjectId(): int
    {
        return $this->gitlabBusinessContractProjectId;
    }

    public function getGitlabPathGroupDefault(): string
    {
        return $this->gitlabPathGroupDefault;
    }

    public function getPostmanApiUrl(): string
    {
        return $this->postmanApiUrl;
    }

    public function getPostmanApiKey(): string
    {
        return $this->postmanApiKey;
    }

    public function getProjectsInGke(): array
    {
        return $this->projectsInGke;
    }

    public function getExcludeProjects(): array
    {
        return $this->excludeProjects;
    }

    public function getTokenE107(): string
    {
        return $this->tokenE107;
    }

    public function getParamNewRelic(): ParamNewRelic
    {
        return $this->paramNewRelic;
    }

    public function setDatabaseHost(string $databaseHost): void
    {
        $this->databaseHost = $databaseHost;
    }

    public function setDatabasePort(int $databasePort): void
    {
        $this->databasePort = $databasePort;
    }

    public function setDatabaseName(string $databaseName): void
    {
        $this->databaseName = $databaseName;
    }

    public function setDatabaseUser(string $databaseUser): void
    {
        $this->databaseUser = $databaseUser;
    }

    public function setDatabasePassword(string $databasePassword): void
    {
        $this->databasePassword = $databasePassword;
    }

    public function setUrlServer(string $urlServer): void
    {
        $this->urlServer = $urlServer;
    }

    public function setIpLocal(string $ipLocal): void
    {
        $this->ipLocal = $ipLocal;
    }

    public function setPortLocal(int $portLocal): void
    {
        $this->portLocal = $portLocal;
    }

    /**
     * @param string $gitlabUrl
     */
    public function setGitlabUrl(string $gitlabUrl): void
    {
        $this->gitlabUrl = $gitlabUrl;
    }

    /**
     * @param string $gitlabToken
     */
    public function setGitlabToken(string $gitlabToken): void
    {
        $this->gitlabToken = $gitlabToken;
    }

    public function setGitlabBusinessContractProjectId(int $gitlabBusinessContractProjectId): void
    {
        $this->gitlabBusinessContractProjectId = $gitlabBusinessContractProjectId;
    }

    public function setGitlabPathGroupDefault(string $gitlabPathGroupDefault): void
    {
        $this->gitlabPathGroupDefault = $gitlabPathGroupDefault;
    }

    public function setPostmanApiKey(string $postmanApiKey): void
    {
        $this->postmanApiKey = $postmanApiKey;
    }

    public function setPostmanApiUrl(string $postmanApiUrl): void
    {
        $this->postmanApiUrl = $postmanApiUrl;
    }

    public function setProjectsInGke(array $projectsInGke): void
    {
        $this->projectsInGke = $projectsInGke;
    }

    public function setExcludeProjects(array $excludeProjects): void
    {
        $this->excludeProjects = $excludeProjects;
    }

    public function setTokenE107(string $tokenE107): void
    {
        $this->tokenE107 = $tokenE107;
    }

    public function setParamNewRelic(ParamNewRelic $paramNewRelic): void
    {
        $this->paramNewRelic = $paramNewRelic;
    }

    /**
     * Méthode de fabrique statique pour analyser un tableau de paramètres et créer un objet ParamConfig.
     *
     * @param array $params Le tableau de paramètres bruts.
     * @return ParamConfig L'objet de configuration des paramètres.
     */
    public static function parse(array $params): self
    {
        if (empty($params)) {
            throw new InvalidArgumentException("Le tableau de paramètres est vide.");
        }
        if (!isset($params['base_url'], $params['host'], $params['port'], $params['database_host'], $params['database_port'], $params['database_user'], $params['database_password'], $params['database_name'],
            $params['gitlab_url'], $params['gitlab_token'], $params['gitlab_business_contract_project_id'], $params['gitlab_path_group_default'],
            $params['postman_api_key'], $params['postman_api_url'],
            $params['newrelic-api-user'], $params['newrelic-account-id-rec'], $params['newrelic-account-id-prod'], $params['newrelic-api-key-rec'], $params['newrelic-api-key-prod'])) {
            throw new InvalidArgumentException("Certains paramètres requis sont manquants.");
        }
        $urlServer = $params['base_url'] ?? '';
        $ipLocal = $params['host'] ?? 'localhost';
        $portLocal = (int)($params['port'] ?? 80);
        $databaseHost = $params['database_host'] ?? 'localhost';
        $databasePort = (int)($params['database_port'] ?? 3306);
        $databaseUser = $params['database_user'] ?? '';
        $databasePassword = $params['database_password'] ?? '';
        $databaseName = $params['database_name'] ?? '';
        $gitlabUrl = $params['gitlab_url'] ?? '';
        $gitlabToken = $params['gitlab_token'] ?? '';
        $gitlabBusinessContractProjectId = (int)($params['gitlab_business_contract_project_id'] ?? 0);
        $gitlabPathGroupDefault = $params['gitlab_path_group_default'] ?? '';
        $postmanApiKey = $params['postman_api_key'] ?? '';
        $postmanApiUrl = $params['postman_api_url'] ?? '';
        $tokenE107 = $params['token_e107'] ?? '';

        $projectsInGke = [];
        if (!empty($params['projects_in_gke'])) {
            $projectsInGke = array_map('trim', explode(',', $params['projects_in_gke']));
        }

        $excludeProjects = [];
        if (!empty($params['exclude_projects'])) {
            $excludeProjects = array_map('trim', explode(',', $params['exclude_projects']));
        }

        $paramNewRelic = new ParamNewRelic();
        $paramNewRelic->setApiUser($params['newrelic-api-user']);
        $paramNewRelic->setApiKeyRec($params['newrelic-api-key-rec']);
        $paramNewRelic->setApiKeyProd($params['newrelic-api-key-prod']);
        $paramNewRelic->setAccountIdDev((int)$params['newrelic-account-id-dev']);
        $paramNewRelic->setAccountIdRec((int)$params['newrelic-account-id-rec']);
        $paramNewRelic->setAccountIdPreprod((int)$params['newrelic-account-id-pp']);
        $paramNewRelic->setAccountIdProd((int)$params['newrelic-account-id-prod']);

        $paramConfig = new self();
        $paramConfig->setDatabaseHost($databaseHost);
        $paramConfig->setDatabaseName($databaseName);
        $paramConfig->setDatabasePassword($databasePassword);
        $paramConfig->setDatabasePort($databasePort);
        $paramConfig->setDatabaseUser($databaseUser);
        $paramConfig->setIpLocal($ipLocal);
        $paramConfig->setPortLocal($portLocal);
        $paramConfig->setUrlServer($urlServer);
        $paramConfig->setGitlabUrl($gitlabUrl);
        $paramConfig->setGitlabToken($gitlabToken);
        $paramConfig->setGitlabBusinessContractProjectId($gitlabBusinessContractProjectId);
        $paramConfig->setGitlabPathGroupDefault($gitlabPathGroupDefault);
        $paramConfig->setPostmanApiKey($postmanApiKey);
        $paramConfig->setPostmanApiUrl($postmanApiUrl);
        $paramConfig->setProjectsInGke($projectsInGke);
        $paramConfig->setExcludeProjects($excludeProjects);
        $paramConfig->setTokenE107($tokenE107);
        $paramConfig->setParamNewRelic($paramNewRelic);

        return $paramConfig;
    }
}