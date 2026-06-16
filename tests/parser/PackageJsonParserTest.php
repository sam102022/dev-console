<?php
declare(strict_types=1);

namespace App\tests\parser;

use App\parser\PackageJsonParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PackageJsonParserTest extends TestCase
{

    public static function parsePackageProvider(): array
    {
        return [
            'with nuxt dependency' => [
                'packageContent' => '{"dependencies": {"nuxt": "^2.15.0"}}',
                'expected' => 'nuxt'
            ],
            'with react dependency' => [
                'packageContent' => '{"dependencies": {"react": "^17.0.0"}}',
                'expected' => 'react'
            ],
            'with both, nuxt first' => [
                'packageContent' => '{"dependencies": {"nuxt": "2.0", "react": "17.0"}}',
                'expected' => 'nuxt'
            ],
            'with both, react first' => [
                'packageContent' => '{"dependencies": {"react": "17.0", "nuxt": "2.0"}}',
                'expected' => 'nuxt' // nuxt is checked first in the function
            ],
            'no relevant dependencies' => [
                'packageContent' => '{"dependencies": {"vue": "^3.0.0"}}',
                'expected' => null
            ],
            'empty content' => [
                'packageContent' => '',
                'expected' => null
            ],
            'null content' => [
                'packageContent' => null,
                'expected' => null
            ]
        ];
    }

    #[DataProvider('parsePackageProvider')]
    final public function testParsePackage(?string $packageContent, ?string $expected): void
    {
        $this->assertEquals($expected, PackageJsonParser::parsePackage($packageContent));
    }

}