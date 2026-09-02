<?php
declare(strict_types=1);

namespace App;

require_once 'config/config.php';

use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use App\context\LocaleContext;
use App\router\IndexRouter;
use App\router\ConsoleRouter;

/**
 * Classe Kernel
 *
 * Le cœur de l'application, utilisant désormais le Micro-Kernel standard de Symfony.
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Langue par défaut de l'application.
     */
    public const LANGUAGE_DEFAULT = 'fr';

    /**
     * Locale par défaut de l'application.
     */
    public const LOCALE_DEFAULT = 'fr_FR';

    public function __construct()
    {
        $env = $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'prod';
        $debug = ($env !== 'prod');
        parent::__construct($env, $debug);
    }

    /**
     * Enregistre les bundles requis pour l'application.
     */
    public function registerBundles(): iterable
    {
        return [
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
        ];
    }

    /**
     * Configure le conteneur de services et les extensions de framework.
     */
    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'S0ME_SEC&ET',
            'http_method_override' => false,
            'php_errors' => [
                'log' => true,
            ],
        ]);

        $container->import(__DIR__ . '/config/services.yaml');
    }

    /**
     * Personnalise le dossier du cache pour correspondre à notre structure existante.
     */
    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/var/cache/' . $this->getEnvironment();
    }

    /**
     * Personnalise le dossier des logs.
     */
    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/var/logs';
    }

    /**
     * Gère les commandes exécutées en console.
     *
     * @param array $argv Les arguments passés à la commande.
     */
    public function handleConsole(array $argv): void
    {
        $this->boot();
        $container = $this->getContainer();

        // Configure dynamiquement la locale/langue courante
        $localeContext = $container->get(LocaleContext::class);
        $localeContext->setLang($this->getLang());
        $localeContext->setLocale($this->getLocale());

        $router = $container->get(ConsoleRouter::class);
        $router->dispatch($argv);
    }

    /**
     * Gère les requêtes web.
     */
    public function handleIndex(): void
    {
        $this->boot();
        $container = $this->getContainer();

        // Configure dynamiquement la locale/langue courante
        $localeContext = $container->get(LocaleContext::class);
        $localeContext->setLang($this->getLang());
        $localeContext->setLocale($this->getLocale());

        $router = $container->get(IndexRouter::class);
        $router->dispatch();
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
