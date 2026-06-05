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
    /** @var ParamRepository Les paramètres de base de données. */
    private ParamRepository $paramRepository;

    /** @var string L'URL du serveur. */
    private string $urlServer;

    /** @var string L'adresse IP locale. */
    private string $ipLocal;

    /** @var int Le port local. */
    private int $portLocal;

    /** @var string La langue par défaut. */
    private string $language = 'fr';

    /** @var ParamGitLab Les paramètres GitLab. */
    private ParamGitLab $paramGitLab;

    /** @var string L'API Key de Postman. */
    private string $postmanApiKey;

    /** @var string L'URL de l'API Postman. */
    private string $postmanApiUrl;

    /** @var string Le token e107. */
    private string $tokenE107;

    /** @var ParamNewRelic Les paramètres New Relic. */
    private ParamNewRelic $paramNewRelic;


    public function getParamRepository(): ParamRepository
    {
        return $this->paramRepository;
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

    public function getParamGitLab(): ParamGitLab
    {
        return $this->paramGitLab;
    }

    public function getPostmanApiUrl(): string
    {
        return $this->postmanApiUrl;
    }

    public function getPostmanApiKey(): string
    {
        return $this->postmanApiKey;
    }

    public function getTokenE107(): string
    {
        return $this->tokenE107;
    }

    public function getParamNewRelic(): ParamNewRelic
    {
        return $this->paramNewRelic;
    }

    public function setParamRepository(ParamRepository $paramRepository): void
    {
        $this->paramRepository = $paramRepository;
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

    public function setParamGitLab(ParamGitLab $paramGitLab): void
    {
        $this->paramGitLab = $paramGitLab;
    }

    public function setPostmanApiKey(string $postmanApiKey): void
    {
        $this->postmanApiKey = $postmanApiKey;
    }

    public function setPostmanApiUrl(string $postmanApiUrl): void
    {
        $this->postmanApiUrl = $postmanApiUrl;
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
        if (!isset($params['base_url'], $params['host'], $params['port'],
            $params['postman_api_key'], $params['postman_api_url'])) {
            throw new InvalidArgumentException("Certains paramètres requis sont manquants.");
        }
        $paramRepository = ParamRepository::parse($params);
        $paramGitLab = ParamGitLab::parse($params);
        $paramNewRelic = ParamNewRelic::parse($params);

        $urlServer = $params['base_url'] ?? '';
        $ipLocal = $params['host'] ?? 'localhost';
        $portLocal = (int)($params['port'] ?? 80);

        $postmanApiKey = $params['postman_api_key'] ?? '';
        $postmanApiUrl = $params['postman_api_url'] ?? '';
        $tokenE107 = $params['token_e107'] ?? '';

        $paramConfig = new self();
        $paramConfig->setParamRepository($paramRepository);
        $paramConfig->setParamGitLab($paramGitLab);
        $paramConfig->setIpLocal($ipLocal);
        $paramConfig->setPortLocal($portLocal);
        $paramConfig->setUrlServer($urlServer);
        $paramConfig->setPostmanApiKey($postmanApiKey);
        $paramConfig->setPostmanApiUrl($postmanApiUrl);
        $paramConfig->setTokenE107($tokenE107);
        $paramConfig->setParamNewRelic($paramNewRelic);

        return $paramConfig;
    }
}