<?php
declare(strict_types=1);

namespace App\tests\model;

use App\model\EnumEnvironment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ValueError;

class EnumEnvironmentTest extends TestCase
{
    final public function testEnumValues(): void
    {
        $this->assertEquals('dev', EnumEnvironment::DEV->value);
        $this->assertEquals('rec', EnumEnvironment::REC->value);
        $this->assertEquals('pp', EnumEnvironment::PP->value);
        $this->assertEquals('prod', EnumEnvironment::PROD->value);
    }

    public static function fromProvider(): array
    {
        return [
            'dev' => ['dev', EnumEnvironment::DEV],
            'rec' => ['rec', EnumEnvironment::REC],
            'pp' => ['pp', EnumEnvironment::PP],
            'prod' => ['prod', EnumEnvironment::PROD],
        ];
    }

    #[DataProvider('fromProvider')]
    final public function testFrom(string $value, EnumEnvironment $expected): void
    {
        $this->assertSame($expected, EnumEnvironment::from($value));
    }

    final public function testFromInvalid(): void
    {
        $this->expectException(ValueError::class);
        EnumEnvironment::from('invalid');
    }

    public static function tryFromProvider(): array
    {
        return [
            'dev' => ['dev', EnumEnvironment::DEV],
            'rec' => ['rec', EnumEnvironment::REC],
            'pp' => ['pp', EnumEnvironment::PP],
            'prod' => ['prod', EnumEnvironment::PROD],
            'invalid' => ['invalid', null],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('tryFromProvider')]
    final public function testTryFrom(string $value, ?EnumEnvironment $expected): void
    {
        $this->assertSame($expected, EnumEnvironment::tryFrom($value));
    }
}
