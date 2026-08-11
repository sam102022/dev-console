<?php
declare(strict_types=1);

namespace App\tests\parser;

use App\parser\ConfigYamlParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConfigYamlParserTest extends TestCase
{
    #[DataProvider('provideServiceNameData')]
    public function testParseServiceName(?string $yamlContent, ?string $expectedServiceName): void
    {
        $this->assertSame($expectedServiceName, ConfigYamlParser::parseServiceName($yamlContent));
    }

    public static function provideServiceNameData(): array
    {
        return [
            'Nom de service simple' => [
                'yamlContent' => "
                    apiVersion: apps/v1
                    kind: Deployment
                    metadata:
                      name: my-service
                ",
                'expectedServiceName' => 'my-service',
            ],
            'Aucun contenu' => [
                'yamlContent' => null,
                'expectedServiceName' => null,
            ],
            'Contenu vide' => [
                'yamlContent' => '',
                'expectedServiceName' => null,
            ],
            'Nom de service avec des espaces' => [
                'yamlContent' => "
                    metadata:
                        name:    my-service-with-spaces  
                ",
                'expectedServiceName' => 'my-service-with-spaces',
            ],
            'Variable CI_PROJECT_NAME' => [
                'yamlContent' => "
                    metadata:
                      name: 'CI_PROJECT_NAME'
                ",
                'expectedServiceName' => '\'CI_PROJECT_NAME\'',
            ],
            'Format invalide' => [
                'yamlContent' => "
                    metadata:
                      nom: my-service
                ",
                'expectedServiceName' => null,
            ],
        ];
    }

    #[DataProvider('providePathLivenessProbeData')]
    public function testParsePathLivenessProbe(?string $yamlContent, ?string $expectedPath): void
    {
        $this->assertSame($expectedPath, ConfigYamlParser::parsePathLivenessProbe($yamlContent));
    }

    public static function providePathLivenessProbeData(): array
    {
        return [
            'Path simple' => [
                'yamlContent' => <<<YAML
          livenessProbe:
            failureThreshold: 3
            httpGet:
             path: /health
YAML,
                'expectedPath' => '/health',
            ],
            'Aucun contenu' => [
                'yamlContent' => null,
                'expectedPath' => null,
            ],
            'Contenu vide' => [
                'yamlContent' => '',
                'expectedPath' => null,
            ],
            'Path avec des espaces' => [
                'yamlContent' => <<<YAML
          livenessProbe:
            failureThreshold: 3
            httpGet:
              path:    /health-with-spaces  
YAML,
                'expectedPath' => '/health-with-spaces',
            ],
            'Format invalide' => [
                'yamlContent' => <<<YAML
          livenessProbe:
            failureThreshold: 3
            httpGet:
              chemin: /health
YAML,
                'expectedPath' => null,
            ],
        ];
    }

    #[DataProvider('provideHostsData')]
    public function testParseHosts(string $yamlContent, ?array $expectedHosts): void
    {
        $this->assertSame($expectedHosts, ConfigYamlParser::parseHosts($yamlContent));
    }

    public static function provideHostsData(): array
    {
        return [
            'Hosts simples' => [
                'yamlContent' => "spec:
  tls:
  - hosts:
    - host1.example.com
    - host2.example.com
",
                'expectedHosts' => ['host1.example.com', 'host2.example.com'],
            ],
            'Aucun contenu' => [
                'yamlContent' => '',
                'expectedHosts' => null,
            ],
            'Format invalide' => [
                'yamlContent' => "spec:
  tls:
  - host:
    - host1.example.com
",
                'expectedHosts' => [],
            ],
        ];
    }

    #[DataProvider('provideServiceNameObjectData')]
    final public function testParseServiceNameObject(?string $yamlContent, ?string $expectedServiceName): void
    {
        $this->assertSame($expectedServiceName, ConfigYamlParser::parseServiceNameObject($yamlContent));
    }

    public static function provideServiceNameObjectData(): array
    {
        return [
            'Nom de service simple' => [
                'yamlContent' => <<<YAML
apiVersion: apps/v1
kind: Deployment
metadata:
  name: my-service
YAML,
                'expectedServiceName' => 'my-service',
            ],
            'Aucun contenu' => [
                'yamlContent' => null,
                'expectedServiceName' => null,
            ],
            'Contenu vide' => [
                'yamlContent' => '',
                'expectedServiceName' => null,
            ],
            'Nom de service avec des espaces' => [
                'yamlContent' => <<<YAML
metadata:
    name:    my-service-with-spaces  
YAML,
                'expectedServiceName' => 'my-service-with-spaces',
            ],
            'Format plat' => [
                'yamlContent' => <<<YAML
metadata.name: my-flat-service
YAML,
                'expectedServiceName' => 'my-flat-service',
            ],
            'Format invalide' => [
                'yamlContent' => <<<YAML
metadata:
  nom: my-service
YAML,
                'expectedServiceName' => null,
            ],
        ];
    }

    #[DataProvider('provideSubscriptionNameData')]
    final public function testParseSubscriptionName(?string $yamlContent, ?string $expectedSubscriptionName): void
    {
        $this->assertSame($expectedSubscriptionName, ConfigYamlParser::parseSubscriptionName($yamlContent));
    }

    public static function provideSubscriptionNameData(): array
    {
        return [
            'Nom de souscription simple' => [
                'yamlContent' => <<<YAML
mdm:
  core:
    subscriber:
      subscription:
        - name:
            - my-subscription
YAML,
                'expectedSubscriptionName' => 'my-subscription',
            ],
            'Aucun contenu' => [
                'yamlContent' => null,
                'expectedSubscriptionName' => null,
            ],
            'Contenu vide' => [
                'yamlContent' => '',
                'expectedSubscriptionName' => null,
            ],
            'Format invalide' => [
                'yamlContent' => <<<YAML
mdm:
  core:
    subscriber:
      subscription:
        - nom:
            - my-subscription
YAML,
                'expectedSubscriptionName' => null,
            ],
        ];
    }

    #[DataProvider('provideVariableInValuesFileData')]
    final public function testParseVariableInValuesFile(string $yamlContent, string $variableName, ?string $expectedValue): void
    {
        $this->assertSame($expectedValue, ConfigYamlParser::parseVariableInValuesFile($yamlContent, $variableName));
    }

    public static function provideVariableInValuesFileData(): array
    {
        return [
            'Variable simple' => [
                'yamlContent' => <<<YAML
MY_VAR: "my-value"
YAML,
                'variableName' => 'MY_VAR',
                'expectedValue' => 'my-value',
            ],
            'Variable avec des espaces' => [
                'yamlContent' => <<<YAML
MY_VAR:   "my-value"  
YAML,
                'variableName' => 'MY_VAR',
                'expectedValue' => 'my-value',
            ],
            'Variable non trouvée' => [
                'yamlContent' => <<<YAML
ANOTHER_VAR: "another-value"
YAML,
                'variableName' => 'MY_VAR',
                'expectedValue' => null,
            ],
            'Aucun contenu' => [
                'yamlContent' => '',
                'variableName' => 'MY_VAR',
                'expectedValue' => null,
            ],
        ];
    }
}