<?php
declare(strict_types=1);

namespace App;

use App\client\GitLabClient;
use App\client\NewRelicClient;
use App\client\PostmanClient;
use App\config\AppConfig;
use App\context\LocaleContext;
use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\service\Translator;
use App\service\IconService;
use App\util\UtilsLog;
use App\view\TwigFactory;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use InvalidArgumentException;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Twig\Environment;

/**
 * Classe AbstractContainer
 *
 * Un conteneur d'injection de dépendances (DIC) basé sur le ContainerBuilder de Symfony.
 * Il gère la création et la résolution des services avec l'autowiring natif de Symfony.
 */
abstract class AbstractContainer
{
    protected ContainerBuilder $containerBuilder;

    /**
     * Constructeur de la classe AbstractContainer.
     */
    public function __construct(
        private readonly string $pathLogs,
        private readonly string $env,
        private readonly string $pathTemplates,
        private readonly Level $levelLogger,
        private readonly LocaleContext $localeContext,
    ) {
        $this->containerBuilder = new ContainerBuilder();
        $this->registerCore();
        $this->registerAllClasses();
        $this->containerBuilder->compile();
    }

    /**
     * Enregistre les services principaux de l'application dans le conteneur.
     */
    protected function registerCore(): void
    {
        $pathTranslations = dirname(__DIR__) . '/translations';

        // Enregistre l'instance pré-construite de LocaleContext
        $this->set(LocaleContext::class, fn() => $this->localeContext);

        /**
         * Monolog Handler (gestionnaire de logs rotatifs)
         */
        $this->set(RotatingFileHandler::class, function () {
            return $this->createLogHandler();
        });

        /**
         * LoggerFactory (pour créer des instances de Logger)
         */
        $this->set(LoggerFactory::class, function ($c) {
            return new LoggerFactory(
                $c->get(RotatingFileHandler::class)
            );
        });

        /**
         * Translator (service de traduction)
         */
        $this->set(Translator::class, function ($c) use ($pathTranslations) {
            return new Translator(
                $c->get(LocaleContext::class)->getLang(),
                $pathTranslations
            );
        });

        /**
         * Twig Environment (moteur de template)
         */
        $this->set(Environment::class, function ($c) {
            return TwigFactory::create(
                $c->get(Translator::class),
                $this->pathTemplates,
                false,
                true
            );
        });

        /**
         * AppConfig (configuration de l'application)
         */
        $this->set(AppConfig::class, function ($c) {
            try {
                return new AppConfig($this->env, $this->pathTemplates, $c->get(LocaleContext::class)->getLang());
            } catch (InvalidArgumentException $e) {
                $logger = $c->get(LoggerFactory::class)->get(__CLASS__);
                $logger->error(
                    UtilsLog::prefixLog(__CLASS__, __FUNCTION__, __LINE__)
                    . "Erreur lors de l'initialisation de AppConfig: " . $e->getMessage()
                );
                throw new TechnicalException("Erreur lors de l'initialisation de l'application", 500, $e);
            }
        });

        $this->set(Client::class, fn($c) => $c->get(ClientInterface::class));
        $this->set(ClientInterface::class, fn($c) => $this->createGuzzleClient($c->get(LoggerFactory::class)));

        // Clients
        $this->set(GitLabClient::class, fn($c) => new GitLabClient(
            new Client([
                'base_uri' => $c->get(AppConfig::class)->getParamConfig()->getParamGitLab()->getGitlabUrl()
            ]),
            $c->get(AppConfig::class),
            $c->get(LoggerFactory::class)
        ));

        $this->set(PostmanClient::class, fn($c) => new PostmanClient(
            new Client([
                'base_uri' => $c->get(AppConfig::class)->getParamConfig()->getParamPostman()->getPostmanApiUrl(),
                'headers' => [
                    'X-Api-Key' => $c->get(AppConfig::class)->getParamConfig()->getParamPostman()->getPostmanApiKey(),
                    'Content-Type' => 'application/json'
                ]
            ])
        ));

        $this->set(NewRelicClient::class, fn($c) => new NewRelicClient(
            new Client(),
            $c->get(AppConfig::class)->getParamConfig()->getParamNewRelic(),
            $c->get(LoggerFactory::class)
        ));

        /**
         * IconService (service d'icônes Singleton)
         */
        $this->set(IconService::class, function () {
            return IconService::getInstance();
        });
    }

    /**
     * Scanne récursivement le dossier src/ pour enregistrer toutes les classes avec autowiring.
     */
    private function registerAllClasses(): void
    {
        $dir = dirname(__DIR__) . '/src';
        if (!is_dir($dir)) {
            return;
        }

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($it as $file) {
            if ($file->isDir()) {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());

            // Exclure les fichiers de configuration et d'icônes qui ne sont pas des classes
            if (str_ends_with($path, '/config/config.php') || str_ends_with($path, '/icons/icons.php')) {
                continue;
            }

            if (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
                $relPath = substr($path, strlen($dir) + 1); // ex: service/GitlabService.php
                $className = 'App\\' . str_replace('/', '\\', substr($relPath, 0, -4));

                // Exclure les exceptions, les modèles de données, LanguageResolver, LocaleContext, IconService, RepositoryService et Kernel de l'autowiring
                if (
                    str_starts_with($className, 'App\\exception\\') ||
                    str_starts_with($className, 'App\\model\\') ||
                    $className === 'App\\context\\LocaleContext' ||
                    $className === 'App\\LanguageResolver' ||
                    $className === 'App\\service\\IconService' ||
                    $className === 'App\\service\\RepositoryService' ||
                    $className === 'App\\Kernel'
                ) {
                    continue;
                }

                if (class_exists($className)) {
                    $reflection = new ReflectionClass($className);
                    if (!$reflection->isAbstract() && !$reflection->isInterface() && $className !== self::class) {
                        // Enregistre seulement si non défini manuellement par registerCore()
                        if (!$this->containerBuilder->has($className)) {
                            $this->containerBuilder->register($className, $className)
                                ->setAutowired(true)
                                ->setPublic(true);
                        }
                    }
                }
            }
        }
    }

    /**
     * Enregistre un service avec une usine de création (factory closure).
     */
    public function set(string $id, callable $factory): void
    {
        ServiceFactory::register($id, $factory);
        $this->containerBuilder->register($id, $id)
            ->setFactory([ServiceFactory::class, 'create'])
            ->setArguments([$id, $this])
            ->setAutowired(true)
            ->setPublic(true);
    }

    /**
     * Récupère une instance de service depuis le conteneur Symfony.
     */
    public function get(string $id): mixed
    {
        return $this->containerBuilder->get($id);
    }

    private function createLogHandler(): StreamHandler
    {
        $handler = new RotatingFileHandler(
            $this->pathLogs,
            7, // Garde les logs sur 7 jours
            $this->levelLogger
        );

        $handler->setFormatter(
            new LineFormatter(
                "[%datetime%] %level_name% - %channel%::%message%\n",
                'Y-m-d\TH:i:sP'
            )
        );

        return $handler;
    }

    /**
     * Client Http générique
     */
    private function createGuzzleClient(LoggerFactory $loggerFactory): Client
    {
        // Handler stack par défaut
        $stack = HandlerStack::create();

        $logger = $loggerFactory->get(__CLASS__);

        // Retry middleware
        $stack->push(Middleware::retry(
            static function (int $retries, RequestInterface $request, ?ResponseInterface $response = null, ?TransferException $exception = null) use ($logger): bool {
                $maxRetries = 3;
                // Limite max de retries
                if ($retries >= $maxRetries) {
                    return false;
                }
                $msgRetry = "Tentative (" . ($retries + 1) . "/" . $maxRetries . "). ";

                // Retry connection exceptions ou Erreur réseau
                if (
                    $exception instanceof ConnectException || $exception instanceof RequestException || $exception instanceof TransferException
                ) {
                    $logger->error(
                        UtilsLog::prefixLog(__CLASS__, __FUNCTION__, __LINE__)
                        . $msgRetry . "Impossible de se connecter à " . $request->getUri()->__toString()
                    );
                    return true;
                }

                if ($response && in_array($response->getStatusCode(), [249, 408, 429, 500, 502, 503, 504], true)) {
                    $logger->error(
                        UtilsLog::prefixLog(__CLASS__, __FUNCTION__, __LINE__)
                        . $msgRetry . "Une erreur est survenue sur le serveur."
                    );
                    return true;
                }

                return false;
            },
            static function (int $retries) {
                // Delay exponentiel en ms
                return (int) pow(2, $retries) * 1000;
            }
        ));

        return new Client([
            'timeout' => 0,
            'connect_timeout' => 30,
            'verify' => false, // DÉSACTIVATION DE LA VÉRIFICATION SSL
            'headers' => [
                'User-Agent' => 'Mozilla/4.0 (compatible; MSIE 5.00; Windows 98)',
            ],
            'handler' => $stack,
        ]);
    }
}
