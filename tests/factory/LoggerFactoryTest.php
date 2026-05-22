<?php
declare(strict_types=1);

namespace App\tests\factory;

use App\factory\LoggerFactory;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class LoggerFactoryTest extends TestCase
{
    public function testGet(): void
    {
        $handler = $this->createMock(RotatingFileHandler::class);
        $loggerFactory = new LoggerFactory($handler);
        $logger = $loggerFactory->get('test_channel');

        $this->assertEquals('test_channel', $logger->getName());
    }

    public function testCreate(): void
    {
        $logFile = sys_get_temp_dir() . '/test.log';
        $logger = LoggerFactory::create('test_channel', $logFile, Level::Debug->value);

        $this->assertEquals('test_channel', $logger->getName());
        $this->assertCount(1, $logger->getHandlers());
        $this->assertInstanceOf(RotatingFileHandler::class, $logger->getHandlers()[0]);
    }
}
