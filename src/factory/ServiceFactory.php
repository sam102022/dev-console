<?php
declare(strict_types=1);

namespace App\factory;

use App\context\LocaleContext;
use App\config\AppConfig;
use App\service\Translator;
use App\view\TwigFactory;
use App\client\GitLabClient;
use App\client\PostmanClient;
use App\client\NewRelicClient;
use App\service\IconService;
use App\service\RepositoryService;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use App\util\UtilsLog;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Twig\Environment;

class ServiceFactory
{
    public static function createRotatingFileHandler(string $kernelEnv): RotatingFileHandler
    {
        $logFile = $kernelEnv === 'test' ? TEST_LOG_FILE : LOG_FILE_DEFAULT;
        $level = $kernelEnv === 'test' ? Level::Debug : Level::Debug;

        $handler = new RotatingFileHandler(
            dirname(__DIR__, 2) . '/' . $logFile,
            7,
            $level
        );

        $handler->setFormatter(
            new LineFormatter(
                "[%datetime%] %level_name% - %channel%::%message%\n",
                'Y-m-d\TH:i:sP'
            )
        );

        return $handler;
    }

    public static function createLoggerFactory(RotatingFileHandler $handler): LoggerFactory
    {
        return new LoggerFactory($handler);
    }

    public static function createTranslator(LocaleContext $localeContext): Translator
    {
        return new Translator(
            $localeContext->getLang(),
            dirname(__DIR__, 2) . '/translations'
        );
    }

    public static function createTwig(Translator $translator): Environment
    {
        return TwigFactory::create(
            $translator,
            dirname(__DIR__, 2) . '/templates',
            false,
            true
        );
    }

    public static function createAppConfig(string $kernelEnv, LocaleContext $localeContext): AppConfig
    {
        return new AppConfig(
            $kernelEnv,
            dirname(__DIR__, 2) . '/templates',
            $localeContext->getLang()
        );
    }

    public static function createGuzzleClient(LoggerFactory $loggerFactory): Client
    {
        $stack = HandlerStack::create();
        $logger = $loggerFactory->get(self::class);

        $stack->push(Middleware::retry(
            static function (int $retries, RequestInterface $request, ?ResponseInterface $response = null, ?TransferException $exception = null) use ($logger): bool {
                $maxRetries = 3;
                if ($retries >= $maxRetries) {
                    return false;
                }
                $msgRetry = "Tentative (" . ($retries + 1) . "/" . $maxRetries . "). ";

                if ($exception instanceof ConnectException || $exception instanceof RequestException || $exception instanceof TransferException) {
                    $logger->error(UtilsLog::prefixLog(self::class, __FUNCTION__, __LINE__) . $msgRetry . "Impossible de se connecter à " . $request->getUri()->__toString());
                    return true;
                }

                if ($response && in_array($response->getStatusCode(), [249, 408, 429, 500, 502, 503, 504], true)) {
                    $logger->error(UtilsLog::prefixLog(self::class, __FUNCTION__, __LINE__) . $msgRetry . "Une erreur est survenue sur le serveur.");
                    return true;
                }

                return false;
            },
            static function (int $retries) {
                return (int) pow(2, $retries) * 1000;
            }
        ));

        return new Client([
            'timeout' => 0,
            'connect_timeout' => 30,
            'verify' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/4.0 (compatible; MSIE 5.00; Windows 98)',
            ],
            'handler' => $stack,
        ]);
    }

    public static function createGitLabClient(AppConfig $appConfig, LoggerFactory $loggerFactory): GitLabClient
    {
        return new GitLabClient(
            new Client([
                'base_uri' => $appConfig->getParamConfig()->getParamGitLab()->getGitlabUrl()
            ]),
            $appConfig,
            $loggerFactory
        );
    }

    public static function createPostmanClient(AppConfig $appConfig, RepositoryService $repositoryService): PostmanClient
    {
        $apiKey = $appConfig->getParamConfig()->getParamPostman()->getPostmanApiKey();

        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
            $user = $repositoryService->findUserById((int)$_SESSION['user_id']);
            if ($user && !empty($user['postman_api_key'])) {
                $apiKey = $user['postman_api_key'];
            }
        }

        return new PostmanClient(
            new Client([
                'base_uri' => $appConfig->getParamConfig()->getParamPostman()->getPostmanApiUrl(),
                'headers' => [
                    'X-Api-Key' => $apiKey,
                    'Content-Type' => 'application/json'
                ]
            ])
        );
    }

    public static function createNewRelicClient(AppConfig $appConfig, LoggerFactory $loggerFactory): NewRelicClient
    {
        return new NewRelicClient(
            new Client(),
            $appConfig->getParamConfig()->getParamNewRelic(),
            $loggerFactory
        );
    }

    public static function createIconService(): IconService
    {
        return IconService::getInstance();
    }
}
