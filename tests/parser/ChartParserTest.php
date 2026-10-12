<?php
declare(strict_types=1);

namespace App\tests\parser;

use App\parser\ChartParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ChartParserTest extends TestCase
{
    private ChartParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ChartParser();
    }

    public static function chartContentProvider(): array
    {
        return [
            'valid chart with mdm-workload' => [
                'content' => <<<YAML
apiVersion: v2
description: A Helm chart to deploy api-store-operator
name: \${COMPONENT_NAME}
version: 1.0.0
dependencies:
  - name: mdm-workload
    version: 1.5.0
    repository: '@mdm-workload'
YAML,
                'expectedVersion' => '1.5.0',
            ],
            'chart with different dependency' => [
                'content' => <<<YAML
apiVersion: v2
dependencies:
  - name: other-dependency
    version: 1.0.0
YAML,
                'expectedVersion' => null,
            ],
            'chart with no dependencies' => [
                'content' => "apiVersion: v2\nname: my-chart",
                'expectedVersion' => null,
            ],
            'empty content' => [
                'content' => '',
                'expectedVersion' => null,
            ],
        ];
    }

    #[DataProvider('chartContentProvider')]
    final public function testParse(string $content, ?string $expectedVersion): void
    {
        $this->assertEquals($expectedVersion, $this->parser->parseChartYaml($content));
    }

    public static function valuesYamlContentProvider(): array
    {
        return [
            'valid values with both data and management-api extraHosts' => [
                'content' => <<<YAML
mdm-workload:
  config:
    deployment:
      container:
        env:
  istioVirtualServices:
    data:
      extraHosts:
        - api-store-stock.dev.mdm-int.net
    management-api:
      extraHosts:
        - management-api-store-stock.dev.mdm-int.net
YAML,
                'expected' => [
                    'data' => ['api-store-stock.dev.mdm-int.net'],
                    'management-api' => ['management-api-store-stock.dev.mdm-int.net'],
                ],
            ],
            'values with only management-api extraHosts' => [
                'content' => <<<YAML
mdm-workload:
  istioVirtualServices:
    management-api:
      extraHosts:
        - management-api-only.dev.mdm-int.net
YAML,
                'expected' => [
                    'data' => [],
                    'management-api' => ['management-api-only.dev.mdm-int.net'],
                ],
            ],
            'values with only data extraHosts' => [
                'content' => <<<YAML
mdm-workload:
  istioVirtualServices:
    data:
      extraHosts:
        - api-data-only.dev.mdm-int.net
YAML,
                'expected' => [
                    'data' => ['api-data-only.dev.mdm-int.net'],
                    'management-api' => [],
                ],
            ],
            'values without istioVirtualServices' => [
                'content' => <<<YAML
mdm-workload:
  config:
    deployment: {}
YAML,
                'expected' => [
                    'data' => [],
                    'management-api' => [],
                ],
            ],
            'empty content' => [
                'content' => '',
                'expected' => [
                    'data' => [],
                    'management-api' => [],
                ],
            ],
            'malformed yaml content' => [
                'content' => "mdm-workload: [unclosed",
                'expected' => [
                    'data' => [],
                    'management-api' => [],
                ],
            ],
        ];
    }

    #[DataProvider('valuesYamlContentProvider')]
    final public function testParseValuesYaml(string $content, array $expected): void
    {
        $this->assertEquals($expected, $this->parser->parseValuesYaml($content));
    }

    public static function probesBasePathProvider(): array
    {
        return [
            'valid basePath with leading slash' => [
                'content' => <<<YAML
mdm-workload:
  config:
    deployment:
      container:
        env:
        probes:
          basePath: "/v2"
YAML,
                'expected' => '/v2',
            ],
            'valid basePath without leading slash' => [
                'content' => <<<YAML
mdm-workload:
  config:
    deployment:
      container:
        probes:
          basePath: "v2"
YAML,
                'expected' => '/v2',
            ],
            'valid basePath with trailing slash' => [
                'content' => <<<YAML
mdm-workload:
  config:
    deployment:
      container:
        probes:
          basePath: "/v2/"
YAML,
                'expected' => '/v2',
            ],
            'multi-segment basePath' => [
                'content' => <<<YAML
mdm-workload:
  config:
    deployment:
      container:
        probes:
          basePath: "/api/v3/"
YAML,
                'expected' => '/api/v3',
            ],
            'values without probes section' => [
                'content' => <<<YAML
mdm-workload:
  config:
    deployment:
      container:
        env:
YAML,
                'expected' => null,
            ],
            'values with empty basePath' => [
                'content' => <<<YAML
mdm-workload:
  config:
    deployment:
      container:
        probes:
          basePath: ""
YAML,
                'expected' => null,
            ],
            'values with root slash basePath' => [
                'content' => <<<YAML
mdm-workload:
  config:
    deployment:
      container:
        probes:
          basePath: "/"
YAML,
                'expected' => null,
            ],
            'empty content' => [
                'content' => '',
                'expected' => null,
            ],
            'null content' => [
                'content' => null,
                'expected' => null,
            ],
            'malformed yaml' => [
                'content' => "mdm-workload: [unclosed",
                'expected' => null,
            ],
        ];
    }

    #[DataProvider('probesBasePathProvider')]
    final public function testParseProbesBasePath(?string $content, ?string $expected): void
    {
        $this->assertEquals($expected, $this->parser->parseProbesBasePath($content));
    }
}
