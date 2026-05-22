<?php
declare(strict_types=1);

namespace App\tests\service;

use App\config\AppConfig;
use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\service\FileService;
use App\service\Translator;
use App\TestContainer;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use Twig\Environment;

class AbstractServiceCase extends TestCase
{
    protected LoggerFactory|MockObject $loggerFactoryMocked;

    protected Logger|MockObject $loggerMocked;

    protected Environment|MockObject $twigMocked;

    protected Logger $log;
    protected Translator|MockObject $translatorMocked;

    protected static AppConfig $appConfig;
    protected static TestContainer $container;

    protected static Environment $twig;
    protected static LoggerFactory $loggerFactory;
    protected static Logger $logger;
    protected static Translator $translator;

    protected const ENVIRONMENT = ENVIRONMENT_TEST;

    /**
     * @throws ReflectionException
     * @throws TechnicalException
     */
    public static function setUpBeforeClass(): void
    {
        // Environnement de test
        self::$container = new TestContainer();

        // Permet de créer les répertoires de travail necessaires
        FileService::initPaths();

        self::$appConfig = self::$container->get(AppConfig::class);
        self::$loggerFactory = self::$container->get(LoggerFactory::class);
        self::$logger = self::$loggerFactory->get(__CLASS__);
        self::$translator = self::$container->get(Translator::class);
        self::$twig = self::$container->get(Environment::class);
    }

    protected function setUp(): void
    {
        $this->twigMocked = $this->createMock(Environment::class);

        $this->translatorMocked = $this->createMock(Translator::class);
        $this->translatorMocked
            ->method('translate')
            ->willReturnCallback(
                fn(string $key, array $params = []) => $key
            );

        // Logger mock
        $this->loggerMocked = $this->createMock(Logger::class);

        // LoggerFactory mock
        $this->loggerFactoryMocked = $this->createMock(LoggerFactory::class);
        $this->loggerFactoryMocked
            ->method('get')
            ->willReturn($this->loggerMocked);
    }

}

