<?php
declare(strict_types=1);

namespace App\tests\exception;

use App\exception\FunctionalException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FunctionalExceptionTest extends TestCase
{
    final public function testConstructorSetsMessageCodeAndPrevious(): void
    {
        // GIVEN
        $previous = new RuntimeException('Previous error');

        // WHEN
        $exception = new FunctionalException('My error', 123, $previous);

        // THEN
        TestCase::assertSame('My error', $exception->getMessage());
        TestCase::assertSame(123, $exception->getCode());
        TestCase::assertSame($previous, $exception->getPrevious());
    }

    final public function testCreateWithMessage(): void
    {
        // WHEN
        $exception = FunctionalException::createWithMessage('Functional error');

        // THEN
        TestCase::assertSame('Functional error', $exception->getMessage());
        TestCase::assertSame(0, $exception->getCode());
        TestCase::assertNull($exception->getPrevious());
    }

    final public function testCreateWithCode(): void
    {
        // WHEN
        $exception = FunctionalException::createWithCode('Functional error', 42);

        // THEN
        TestCase::assertSame('Functional error', $exception->getMessage());
        TestCase::assertSame(42, $exception->getCode());
        TestCase::assertNull($exception->getPrevious());
    }

    final public function testToStringReturnsExpectedFormat(): void
    {
        // GIVEN
        $exception = new FunctionalException('Error message', 99);

        // WHEN
        $string = (string) $exception;

        // THEN
        TestCase::assertSame(
            FunctionalException::class . ": [99]: Error message\n",
            $string
        );
    }

    final public function testJsonSerializeContainsMessageAndCode(): void
    {
        // GIVEN
        $exception = new FunctionalException('JSON error', 7);

        // WHEN
        $data = $exception->jsonSerialize();

        // THEN
        TestCase::assertArrayHasKey('message', $data);
        TestCase::assertArrayHasKey('code', $data);

        TestCase::assertSame('JSON error', $data['message']);
        TestCase::assertSame(7, $data['code']);
    }

    final public function testJsonEncodeWorksAsExpected(): void
    {
        // GIVEN
        $exception = new FunctionalException('Encoded error', 500);

        // WHEN
        $json = json_encode($exception, JSON_THROW_ON_ERROR);
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        // THEN
        TestCase::assertSame('Encoded error', $decoded['message']);
        TestCase::assertSame(500, $decoded['code']);
    }
}
