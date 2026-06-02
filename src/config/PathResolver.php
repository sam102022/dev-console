<?php
declare(strict_types=1);

namespace App\config;

use App\exception\TechnicalException;
use App\service\FileService;
use RuntimeException;

/**
 * Classe PathResolver
 *
 * Résout les chemins relatifs à l'application en leurs chemins absolus et réels sur le système de fichiers.
 * Assure que les chemins configurés existent et sont accessibles.
 */
final class PathResolver
{
    /**
     * @var string Le chemin absolu vers le répertoire racine du projet.
     */
    private string $rootDir;

    /**
     * Constructeur de la classe PathResolver.
     *
     * Initialise le chemin racine du projet.
     */
    public function __construct()
    {
        $this->rootDir = dirname(__DIR__) . '/../';
    }

    /**
     * Résout un chemin donné relatif à la racine du projet en un chemin absolu.
     *
     * @param string $path Le chemin relatif à résoudre (ex: 'var/cache').
     * @return string Le chemin absolu et réel.
     * @throws RuntimeException|TechnicalException Si le chemin n'existe pas ou n'est pas lisible.
     */
    public function resolve(string $path): string
    {
        $realPath = realpath($this->rootDir . $path);
        if ($realPath === false) {
            FileService::createDirectory($this->rootDir . $path);
            $realPath = realpath($this->rootDir . $path);
            if ($realPath === false) {
                throw new RuntimeException(sprintf('Le chemin configuré n\'existe pas : %s', $this->rootDir . $path));
            }
        }
        return $realPath;
    }
}
