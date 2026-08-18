<?php
declare(strict_types=1);

namespace App\tests\parser;

use App\parser\JsonParser;
use App\parser\XmlParser;
use App\parser\YamlParser;
use App\tests\AbstractTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Classe GenericParsersTest
 *
 * Tests unitaires paramétrés pour JsonParser, XmlParser, et YamlParser.
 */
class GenericParsersTest extends AbstractTestCase
{
    /**
     * Teste le décodage et le parsing de données JSON.
     */
    #[DataProvider('jsonDataProvider')]
    public function testJsonParser(string $json, mixed $expected): void
    {
        $result = JsonParser::parse($json);
        $this->assertEquals($expected, $result);
    }

    /**
     * Fournisseur de données pour les tests JSON.
     */
    public static function jsonDataProvider(): array
    {
        return [
            'valid_array' => ['{"foo": "bar"}', ['foo' => 'bar']],
            'valid_nested' => ['{"foo": {"bar": 123}}', ['foo' => ['bar' => 123]]],
            'empty_object' => ['{}', []],
            'invalid_json' => ['{invalid}', false],
        ];
    }

    /**
     * Teste le parsing de fichiers XML.
     */
    #[DataProvider('xmlDataProvider')]
    public function testXmlParser(string $xml, bool $isValid, ?string $rootName): void
    {
        $result = XmlParser::parse($xml);
        
        if ($isValid) {
            $this->assertInstanceOf(\SimpleXMLElement::class, $result);
            $this->assertEquals($rootName, $result->getName());
        } else {
            $this->assertFalse($result);
        }
    }

    /**
     * Fournisseur de données pour les tests XML.
     */
    public static function xmlDataProvider(): array
    {
        return [
            'valid_xml' => ['<root><child>value</child></root>', true, 'root'],
            'valid_xml_different_root' => ['<project name="test"></project>', true, 'project'],
            'invalid_xml' => ['<root><unclosed>', false, null],
            'empty_xml' => ['', false, null],
        ];
    }

    /**
     * Teste le parsing de fichiers YAML.
     */
    #[DataProvider('yamlDataProvider')]
    public function testYamlParser(string $yaml, ?array $expected): void
    {
        $result = YamlParser::parse($yaml);
        $this->assertEquals($expected, $result);
    }

    /**
     * Fournisseur de données pour les tests YAML.
     */
    public static function yamlDataProvider(): array
    {
        return [
            'valid_yaml' => ["foo: bar\nnum: 123", ['foo' => 'bar', 'num' => 123]],
            'empty_string' => ['', null],
            'only_spaces' => ['   ', null],
        ];
    }
}
