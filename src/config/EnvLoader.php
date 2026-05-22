<?php
declare(strict_types=1);

namespace App\config;

use App\exception\TechnicalException;

/**
 * Classe EnvLoader
 *
 * Charge les variables d'environnement à partir des fichiers .env.
 * Permet de configurer l'application dynamiquement en fonction de l'environnement (prod, dev, test).
 */
final class EnvLoader
{
    /**
     * Charge les variables d'environnement à partir du fichier .env correspondant à l'environnement spécifié.
     *
     * @param string $env L'environnement de l'application (ex: 'prod', 'dev', 'test').
     * @return array Un tableau associatif des variables d'environnement (clé => valeur).
     * @throws TechnicalException Si le fichier .env correspondant n'est pas trouvé.
     */
    public static function load(string $env, string $pathDefault): array
    {
        $filename = '.env';

        if ($env !== 'prod') {
            $filename .= '-' . $env;
        }

        $pathFile = $pathDefault . DIRECTORY_SEPARATOR . $filename;

        if (!file_exists($pathFile)) {
            throw TechnicalException::createWithMessage(
                "Fichier $pathFile non trouvé ($env)"
            );
        }

        $params = [];

        foreach (file($pathFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $params[$key] = $value;
        }

        return $params;
    }
}
