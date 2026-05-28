<?php
declare(strict_types=1);

namespace App;

use App\client\GitLabClient;
use App\client\PostmanClient;
use App\config\AppConfig;
use App\context\LocaleContext;
use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\parser\GradleParser;
use App\parser\MavenParser;
use App\repository\GitLabRepository;
use App\repository\ProjectRepository;
use App\service\GitlabService;
use App\service\Translator;
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
use ReflectionException;
use ReflectionNamedType;
use RuntimeException;
use Twig\Environment;

/**
 * Classe AbstractContainer
 *
 * Un conteneur d'injection de dépendances (DIC) abstrait avec autowiring.
 * Il gère la création et la résolution des services de l'application.
 * Les services peuvent être enregistrés manuellement ou résolus automatiquement
 * par réflexion (autowiring).
 */
abstract class AbstractContainer
{
    /**
     * @var array Stocke les définitions des services (usines de création).
     */
    private array $entries = [];

    /**
     * @var array Stocke les instances des services déjà créés (singletons).
     */
    private array $instances = [];

    /**
     * Constructeur de la classe AbstractContainer.
     *
     * @param string $pathLogs Chemin vers le fichier de log.
     * @param string $env L'environnement actuel (ex: 'prod', 'dev', 'test').
     * @param string $pathTemplates Chemin vers le répertoire des templates Twig.
     * @param Level $levelLogger Le niveau de log minimum pour Monolog.
     * @param LocaleContext $localeContext Le contexte de la locale.
     */
    public function __construct(
        private readonly string $pathLogs,
        private readonly string $env,
        private readonly string $pathTemplates,
        private readonly Level $levelLogger,
        private readonly LocaleContext $localeContext,
    ) {
        $this->registerCore();
    }

    /**
     * Enregistre les services principaux de l'application dans le conteneur.
     * Ces services sont essentiels au fonctionnement de base de l'application.
     */
    protected function registerCore(): void
    {
        $pathTranslations = dirname(__DIR__) . '/translations';

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
                throw new TechnicalException("Erreur lors de l'initialisation de l'application", 500, $e); // Rethrow pour que l'application puisse gérer cette erreur critique
            }
        });

        $this->set(Client::class, fn($c) => $c->get(ClientInterface::class));
        $this->set(ClientInterface::class, fn($c) => $this->createGuzzleClient($c->get(LoggerFactory::class)));

        // Clients
        $this->set(GitLabClient::class, fn($c) => new GitLabClient(
            new Client([
                'base_uri' => $c->get(AppConfig::class)->getParamConfig()->getGitlabUrl()
            ]),
            $c->get(AppConfig::class),
            $c->get(LoggerFactory::class)
        ));

        $this->set(PostmanClient::class, fn($c) => new PostmanClient(
            new Client([
                'base_uri' => $c->get(AppConfig::class)->getParamConfig()->getPostmanApiUrl(),
                'headers' => [
                    'X-Api-Key' => $c->get(AppConfig::class)->getParamConfig()->getPostmanApiKey(),
                    'Content-Type' => 'application/json'
                ]
            ])
        ));

        // Services
        $this->set(GitlabService::class, fn($c) => new GitlabService(
            $c->get(GitLabClient::class),
            $c->get(MavenParser::class),
            $c->get(GradleParser::class),
            $c->get(GitLabRepository::class),
            $c->get(ProjectRepository::class),
            $c->get(AppConfig::class),
            $c->get(LoggerFactory::class)
        ));
    }

    /**
     * Enregistre manuellement un service dans le conteneur.
     *
     * @param string $id L'identifiant du service (généralement le nom de la classe).
     * @param callable $factory Une fonction (closure) qui sait comment créer l'instance du service.
     */
    public function set(string $id, callable $factory): void
    {
        $this->entries[$id] = $factory;
    }

    /**
     * Récupère une instance de service depuis le conteneur.
     *
     * Si l'instance n'existe pas, elle est créée, mise en cache (singleton) et retournée.
     * La création se fait via une usine manuelle si elle existe, sinon par autowiring.
     *
     * @param string $id L'identifiant du service à récupérer.
     * @return mixed L'instance du service.
     * @throws ReflectionException Exception
     */
    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->entries[$id])) {
            return $this->instances[$id] = ($this->entries[$id])($this);
        }

        return $this->instances[$id] = $this->autowire($id);
    }

    /**
     * Crée automatiquement une instance de classe en résolvant ses dépendances.
     *
     * Utilise la réflexion pour inspecter le constructeur de la classe, puis demande
     * récursivement au conteneur de fournir chaque dépendance.
     *
     * @param string $class Le nom de la classe à instancier.
     * @return object L'instance de la classe créée.
     * @throws RuntimeException|ReflectionException Si une dépendance ne peut pas être résolue.
     */
    private function autowire(string $class): object
    {
        if (!class_exists($class)) {
            throw new RuntimeException("La classe $class n'existe pas.");
        }

        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            return new $class();
        }

        $dependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            // Les types scalaires (string, int, etc.) ne peuvent pas être autowirés.
            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                throw new RuntimeException(
                    "Impossible d'autowire le paramètre \${$parameter->getName()} de la classe $class"
                );
            }

            $dependencies[] = $this->get($type->getName());
        }

        return $reflection->newInstanceArgs($dependencies);
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

        $maxRetries = 3;
        $logger = $loggerFactory->get(__CLASS__);

        // Retry middleware
        $stack->push(Middleware::retry(
            static function (int $retries, RequestInterface $request, ?ResponseInterface $response = null, ?TransferException $exception = null) use ($maxRetries, $logger): bool {
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
                        . $msgRetry . "Impossible de se connecter à " . $request->getUri()
                    );
                    return true;
                }

                if ($response) {
                    /*$logger->debug(
                        UtilsLog::prefixLog(__CLASS__, __FUNCTION__, __LINE__)
                        . "Statut " . $response->getStatusCode() . " pour " . $request->getUri()
                    );*/
                    if (in_array($response->getStatusCode(), [249, 408, 429, 500, 502, 503, 504], true)) {
                        $logger->error(
                            UtilsLog::prefixLog(__CLASS__, __FUNCTION__, __LINE__)
                            . $msgRetry . "Une erreur est survenue sur le serveur."
                        );
                        return true;
                    }
                }

                return false;
            },
            static function (int $retries) {
                // Delay exponentiel en ms
                return (int) pow(2, $retries) * 1000;
            }
        ));

        return new Client([
            //'read_timeout' => 300,
            'timeout' => 0,
            'connect_timeout' => 30,
            'verify' => false, // DÉSACTIVATION DE LA VÉRIFICATION SSL
            //'stream' => true,
            'headers' => [
                'User-Agent' => 'Mozilla/4.0 (compatible; MSIE 5.00; Windows 98)',
            ],
            'handler' => $stack,
        ]);
    }
}