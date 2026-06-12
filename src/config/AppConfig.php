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
    private string $pathCacheFile;
    private string $pathCacheUser;
    private string $pathImages;
    private string $pathData;

    /**
     * Constructeur de la classe AppConfig.
     *
     * @param string $env L'environnement actuel de l'application (ex: 'prod', 'dev').
     * @param string $pathTemplates Le chemin vers le répertoire des templates.
     * @param string $defaultLocale La locale par défaut de l'application.
     * @throws TechnicalException
     */
    public function __construct(
        public string  $env,
        public string  $pathTemplates,
        private string $defaultLocale = 'fr'
    )
    {
        $params = EnvLoader::load($env, dirname(__DIR__, 2));
        $this->paramConfig = ParamConfig::parse($params);

        $pathResolver = new PathResolver();
        $this->pathCacheFile = $pathResolver->resolve(PATH_CACHE_FILE);
        $this->pathCacheUser = $pathResolver->resolve(PATH_CACHE_USER);
        $this->pathImages = $pathResolver->resolve(PATH_IMAGES);
        $this->pathData = $pathResolver->resolve(PATH_DATA);
        $pathResolver->resolve(PATH_LOGS);
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

    public function getPathCacheFile(): string
    {
        return $this->pathCacheFile;
    }

    public function getPathCacheUser(): string
    {
        return $this->pathCacheUser;
    }

    public function getPathImages(): string
    {
        return $this->pathImages;
    }

    public function getPathData(): string
    {
        return $this->pathData;
    }
}