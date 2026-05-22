<?php
declare(strict_types=1);

namespace App\view;

use App\service\Translator;
use App\service\TwigTranslationExtension;
use Twig\Cache\CacheInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Classe TwigFactory
 *
 * Usine (Factory) pour créer et configurer l'environnement Twig.
 * Elle centralise la configuration du moteur de templates, y compris
 * l'ajout d'extensions personnalisées.
 */
final class TwigFactory
{
    /**
     * Crée une instance de l'environnement Twig.
     *
     * @param Translator $translator Traducteur.
     * @param string $templateDir Le chemin vers le répertoire des templates.
     * @param string|false|CacheInterface $cacheDir Le chemin vers le répertoire de cache (optionnel).
     * @param bool $debug Active ou désactive le mode de débogage.
     */
    public static function create(
        Translator $translator,
        string $templateDir,
        string|false|CacheInterface $cacheDir,
        bool $debug
    ): Environment {
        $loader = new FilesystemLoader($templateDir);
        $twig = new Environment($loader, [
            'cache' => $cacheDir,
            'debug' => $debug,
        ]);

        $twig->addExtension(new TwigTranslationExtension($translator));

        return $twig;
    }
}
