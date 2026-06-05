<?php
declare(strict_types=1);

namespace App\config;

use App\exception\TechnicalException;
use App\model\ParamConfig;

/**
 * Classe AppConfig
 *
 * Gère et fournit l'accès à la configuration de l'application.
 * Elle centralise l'accès aux paramètres d'environnement, aux connexions de base de données et aux chemins de fichiers.
 */
class AppConfig
{
    private ParamConfig $paramConfig;
    private string $pathImages;

    /**
     * Constructeur de la classe AppConfig.
     *
     * @param string $env L'environnement actuel de l'application (ex: 'prod', 'dev').
     * @param string $pathTemplates Le chemin vers le répertoire des templates.
     * @param string $defaultLocale La locale par défaut de l'application.
     * @throws TechnicalException
     */
    public function __construct(
        public string $env,
        public string $pathTemplates,
        private string $defaultLocale = 'fr'
    ) {
        $params = EnvLoader::load($env, dirname(__DIR__, 2));
        $this->paramConfig = ParamConfig::parse($params);

        $pathResolver = new PathResolver();
        $this->pathImages = $pathResolver->resolve(PATH_IMAGES);
        $pathResolver->resolve(PATH_LOGS);
        $pathResolver->resolve(PATH_DATA);
    }

    /**
     * Retourne l'objet des paramètres de configuration analysés.
     */
    public function getParamConfig(): ParamConfig
    {
        return $this->paramConfig;
    }

    /**
     * Retourne la locale par défaut de l'application.
     */
    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    /**
     * Retourne l'environnement actuel.
     */
    public function getEnv(): string
    {
        return $this->env;
    }

    /**
     * Retourne le chemin vers le répertoire des templates.
     */
    public function getPathTemplates(): string
    {
        return $this->pathTemplates;
    }

    /**
     * Retourne l'URL de base du serveur.
     */
    public function getBaseUrl(): string
    {
        return $this->paramConfig->getUrlServer();
    }

    /**
     * Retourne l'hôte local.
     */
    public function getHost(): string
    {
        return $this->paramConfig->getIpLocal();
    }

    /**
     * Retourne le port local.
     */
    public function getPort(): int
    {
        return $this->paramConfig->getPortLocal();
    }
}