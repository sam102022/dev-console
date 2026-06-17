<?php
declare(strict_types=1);

namespace App\tests\parser;

use App\parser\MavenParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MavenParserTest extends TestCase
{
    private MavenParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MavenParser();
    }

    public static function parseProvider(): array
    {
        return [
            'valid pom with spring boot and java' => [
                'xml' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<project xmlns="http://maven.apache.org/POM/4.0.0">
    <modelVersion>4.0.0</modelVersion>
    <parent>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-parent</artifactId>
        <version>3.1.5</version>
    </parent>
    <properties>
        <java.version>17</java.version>
    </properties>
</project>
XML,
                'expected' => [
                    'springBoot' => '3.1.5',
                    'java' => '17'
                ]
            ],
            'pom with java only' => [
                'xml' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<project xmlns="http://maven.apache.org/POM/4.0.0">
    <modelVersion>4.0.0</modelVersion>
    <parent>
        <groupId>org.example</groupId>
        <artifactId>my-parent</artifactId>
        <version>1.0.0</version>
    </parent>
    <properties>
        <java.version>11</java.version>
    </properties>
</project>
XML,
                'expected' => [
                    'springBoot' => null,
                    'java' => '11'
                ]
            ],
            'pom with spring boot only' => [
                'xml' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<project xmlns="http://maven.apache.org/POM/4.0.0">
    <modelVersion>4.0.0</modelVersion>
    <parent>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-parent</artifactId>
        <version>2.7.0</version>
    </parent>
</project>
XML,
                'expected' => [
                    'springBoot' => '2.7.0',
                    'java' => null
                ]
            ],
            'pom without parent and properties' => [
                'xml' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<project xmlns="http://maven.apache.org/POM/4.0.0">
    <modelVersion>4.0.0</modelVersion>
    <groupId>org.example</groupId>
    <artifactId>my-app</artifactId>
    <version>1.0.0</version>
</project>
XML,
                'expected' => [
                    'springBoot' => null,
                    'java' => null
                ]
            ],
            'invalid xml' => [
                'xml' => 'not xml',
                'expected' => [
                    'springBoot' => null,
                    'java' => null
                ]
            ],
            'empty xml' => [
                'xml' => '',
                'expected' => [
                    'springBoot' => null,
                    'java' => null
                ]
            ],
        ];
    }

    #[DataProvider('parseProvider')]
    public function testParse(string $xml, array $expected): void
    {
        // Suppress warnings for simplexml_load_string if invalid xml is tested
        $actual = @$this->parser->parsePomXml($xml);
        $this->assertEquals($expected, $actual);
    }
}
