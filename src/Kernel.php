<?php
declare(strict_types=1);

namespace App;

require_once 'config/config.php';

use App\context\LocaleContext;
use App\exception\TechnicalException;
use App\router\ConsoleRouter;
use App\router\IndexRouter;

/**
 * Classe Kernel
 *
 * Le cœur de l'application. Cette classe est responsable de l'amorçage (boot)
 * de l'application, de l'initialisation du conteneur d'injection de dépendances
 * et de la gestion des différentes routes (web, admin, console, etc.).
 */
final class Kernel
{
    /**
     * Langue par défaut de l'application.
     */
    public const LANGUAGE_DEFAULT = 'fr';

    /**
     * Locale par défaut de l'application.
     */
    public const LOCALE_DEFAULT = 'fr_FR';

    /**
     * Amorce le conteneur de dépendances pour les contextes web.
     */
    public function boot(): Container
    {
        return new Container(new LocaleContext($this->getLocale(), $this->getLang()));
    }

    /**
     * Amorce le conteneur de dépendances pour le contexte de la console.
     */
    public function bootConsole(): ContainerConsole
    {
        return new ContainerConsole(new LocaleContext($this->getLocale(), $this->getLang()));
    }

    /**
     * Gère les commandes exécutées en console.
     *
     * @param array $argv Les arguments passés à la commande.
     */
    public function handleConsole(array $argv): void
    {
        $router = $this->buildConsoleRouter();
        $router->dispatch($argv);
    }

    /**
     * Gère les requêtes web AJAX.
     * Initialise le routeur Ajax et déclenche la distribution de la requête.
     * @throws TechnicalException
     */
    public function handleIndex(): void
    {
        $router = $this->buildIndexRouter();
        $router->dispatch();
    }

    /**
     * Construit le routeur pour la console.
     */
    private function buildConsoleRouter(): ConsoleRouter
    {
        return $this->bootConsole()->get(ConsoleRouter::class);
    }

    /**
     * Construit le routeur pour la section public.
     */
    private function buildIndexRouter(): IndexRouter
    {
        return $this->boot()->get(IndexRouter::class);
    }

    /**
     * Détermine la langue à utiliser en se basant sur les paramètres GET,
     * la session et les en-têtes HTTP.
     */
    private function getLang(): string
    {
        $resolver = new LanguageResolver(['fr', 'en'], self::LANGUAGE_DEFAULT);
        $lang = $resolver->resolve(
            $_GET['lang'] ?? null,
            $_SESSION['lang'] ?? null,
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null
        );
        $_SESSION['lang'] = $lang;
        return $lang;
    }

    /**
     * Détermine la locale à utiliser.
     */
    private function getLocale(): string
    {
        /*$locale = match ($this->getLang()) {
            'en' => 'en_US',
            default => self::LOCALE_DEFAULT,
        };*/

        $locale = $_SESSION['locale'] ?? self::LOCALE_DEFAULT;
        if (isset($_GET['locale'])) {
            $locale = $_GET['locale'];
            $_SESSION['locale'] = $locale;
            $localeArr = explode('_', $locale);
            $_SESSION['lang'] = $localeArr[0];
        }
        return $locale;
    }
}
