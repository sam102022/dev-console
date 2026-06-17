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
}
