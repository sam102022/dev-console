<?php
declare(strict_types=1);

namespace App\tests\exception;

use App\exception\TechnicalException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TechnicalExceptionTest extends TestCase
{
    public function testConstructorSetsMessageCodeAndPrevious(): void
    {
        // GIVEN
        $previous = new RuntimeException('Previous error');

        // WHEN
        $exception = new TechnicalException('My error', 123, $previous);

        // THEN
        TestCase::assertSame('My error', $exception->getMessage());
        TestCase::assertSame(123, $exception->getCode());
        TestCase::assertSame($previous, $exception->getPrevious());
    }

    public function testCreateWithMessage(): void
    {
        // WHEN
        $exception = TechnicalException::createWithMessage('Technical error');

        // THEN
        TestCase::assertSame('Technical error', $exception->getMessage());
        TestCase::assertSame(0, $exception->getCode());
        TestCase::assertNull($exception->getPrevious());
    }

    public function testCreateWithCode(): void
    {
        // WHEN
        $exception = TechnicalException::createWithCode('Technical error', 42);

        // THEN
        TestCase::assertSame('Technical error', $exception->getMessage());
        TestCase::assertSame(42, $exception->getCode());
        TestCase::assertNull($exception->getPrevious());
    }

    public function testToStringReturnsExpectedFormat(): void
    {
        // GIVEN
        $exception = new TechnicalException('Error message', 99);

        // WHEN
        $string = (string) $exception;

        // THEN
        TestCase::assertSame(
            TechnicalException::class . ": [99]: Error message\n",
            $string
        );
    }

    public function testJsonSerializeContainsMessageAndCode(): void
    {
        // GIVEN
        $exception = new TechnicalException('JSON error', 7);

        // WHEN
        $data = $exception->jsonSerialize();

        // THEN
        TestCase::assertArrayHasKey('message', $data);
        TestCase::assertArrayHasKey('code', $data);

        TestCase::assertSame('JSON error', $data['message']);
        TestCase::assertSame(7, $data['code']);
    }

    public function testJsonEncodeWorksAsExpected(): void
    {
        // GIVEN
        $exception = new TechnicalException('Encoded error', 500);

        // WHEN
        $json = json_encode($exception, JSON_THROW_ON_ERROR);
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        // THEN
        TestCase::assertSame('Encoded error', $decoded['message']);
        TestCase::assertSame(500, $decoded['code']);
    }
}
