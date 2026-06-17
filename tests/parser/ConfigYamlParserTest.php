<?php
declare(strict_types=1);

namespace App\tests\parser;

use App\parser\ConfigYamlParser;
use App\util\MonitoringUtils;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConfigYamlParserTest extends TestCase
{
    #[DataProvider('parseServiceNameProvider')]
    final public function testParseServiceName(
        ?string $yamlContent,
        ?string $expectedServiceName
    ): void
    {
        $result = ConfigYamlParser::parseServiceNameObject($yamlContent);

        $this->assertSame($expectedServiceName, $result);
    }

    public static function parseServiceNameProvider(): iterable
    {
        yield 'null content' => [
            null,
            null,
        ];

        yield 'empty content' => [
            '',
            null,
        ];

        yield 'missing metadata' => [
            <<<YAML
spec:
  replicas: 2
YAML,
            null,
        ];

        yield 'missing name field' => [
            <<<YAML
metadata:
  labels:
    app: test
YAML,
            null,
        ];

        yield 'single service name 1' => [
            <<<YAML
metadata:
  - name:
      - my-service
YAML,
            'my-service',
        ];

        yield 'single service name without quotes' => [
            <<<YAML
metadata:
  name: my-service
YAML,
            'my-service',
        ];

        yield 'single service name inline' => [
            <<<YAML
metadata.name: my-service
YAML,
            'my-service',
        ];

        yield 'multiple names last wins' => [
            <<<YAML
metadata:
  - name:
      - service-1
      - service-2
      - service-3
YAML,
            'service-3',
        ];
    }

    #[DataProvider('parseHostsProvider')]
    final public function testParseHosts(
        string $content,
        ?array $expectedHosts
    ): void
    {
        // GIVEN

        // WHEN
        $result = ConfigYamlParser::parseHosts($content);

        // THEN
        $this->assertSame($expectedHosts, $result);
    }

    public static function parseHostsProvider(): iterable
    {
        yield 'should return null when content is empty' => [
            '',
            null,
        ];

        yield 'should return empty array when spec is missing' => [
            <<<YAML
foo: bar
YAML,
            [],
        ];

        yield 'should return empty array when tls section is missing' => [
            <<<YAML
spec:
  ingressClassName: nginx
YAML,
            [],
        ];

        yield 'should return empty array when hosts are missing' => [
            <<<YAML
spec:
  tls:
    - secretName: wildcard-app-sf
YAML,
            [],
        ];

        yield 'should return a single host' => [
            <<<YAML
spec:
  tls:
    - hosts:
        - api-store-stock-regulation.stores-stock.app-dev.xm
      secretName: wildcard-app-sf
YAML,
            [
                'api-store-stock-regulation.stores-stock.app-dev.xm',
            ],
        ];

        yield 'should return all hosts from multiple tls entries' => [
            <<<YAML
spec:
  tls:
    - hosts:
        - api-store-stock-regulation.stores-stock.app-dev.xm
        - management-api-store-stock-regulation.stores-stock.app-dev.xm
      secretName: wildcard-app-sf

    - hosts:
        - api-product.app-dev.xm
        - management-api-product.app-dev.xm
      secretName: wildcard-product
YAML,
            [
                'api-store-stock-regulation.stores-stock.app-dev.xm',
                'management-api-store-stock-regulation.stores-stock.app-dev.xm',
                'api-product.app-dev.xm',
                'management-api-product.app-dev.xm',
            ],
        ];
    }

    #[DataProvider('parseSubscriptionNameProvider')]
    final public function testParseSubscriptionName(
        ?string $yamlContent,
        ?string $expectedSubscriptionName
    ): void
    {
        // GIVEN

        // WHEN
        $result = ConfigYamlParser::parseSubscriptionName($yamlContent);

        // THEN
        $this->assertSame($expectedSubscriptionName, $result);
    }

    public static function parseSubscriptionNameProvider(): iterable
    {
        yield 'should return null when content is null' => [
            null,
            null,
        ];

        yield 'should return null when content is empty' => [
            '',
            null,
        ];

        yield 'should return null when mdm section is missing' => [
            <<<YAML
foo:
  bar: test
YAML,
            null,
        ];

        yield 'should return null when subscription section is missing' => [
            <<<YAML
mdm:
  core:
    subscriber:
      enabled: true
YAML,
            null,
        ];

        yield 'should return subscription name' => [
            <<<YAML
mdm:
  core:
    subscriber:
      subscription:
        - name:
            - stock-regulation-subscription
YAML,
            'stock-regulation-subscription',
        ];

        yield 'should return last subscription name when several names exist' => [
            <<<YAML
mdm:
  core:
    subscriber:
      subscription:
        - name:
            - subscription-1
            - subscription-2
            - subscription-3
YAML,
            'subscription-3',
        ];
    }

    public static function parseVariableInValuesFileProvider(): array
    {
        return [
            'valid variable name' => [
                'yamlContent' => "CLICK_AND_COLLECT_REPORTS_SUBSCRIPTION_NAME: \"my-subscription-name\"",
                'variableName' => 'CLICK_AND_COLLECT_REPORTS_SUBSCRIPTION_NAME',
                'expectedValue' => 'my-subscription-name'
            ],
            'valid variable name without quotes' => [
                'yamlContent' => "CLICK_AND_COLLECT_REPORTS_SUBSCRIPTION_NAME: my-subscription-name",
                'variableName' => 'CLICK_AND_COLLECT_REPORTS_SUBSCRIPTION_NAME',
                'expectedValue' => 'my-subscription-name'
            ],
            'valid variable name with spaces' => [
                'yamlContent' => "CLICK_AND_COLLECT_REPORTS_SUBSCRIPTION_NAME:   \"my-subscription-name\"  ",
                'variableName' => 'CLICK_AND_COLLECT_REPORTS_SUBSCRIPTION_NAME',
                'expectedValue' => 'my-subscription-name'
            ],
            'no variable name' => [
                'yamlContent' => "OTHER_VARIABLE: my-subscription-name",
                'variableName' => 'CLICK_AND_COLLECT_REPORTS_SUBSCRIPTION_NAME',
                'expectedValue' => null
            ]
        ];
    }

    #[DataProvider('parseVariableInValuesFileProvider')]
    final public function testParseVariableInValuesFile(string $yamlContent, string $variableName, ?string $expectedValue): void
    {
        $this->assertEquals($expectedValue, ConfigYamlParser::parseVariableInValuesFile($yamlContent, $variableName));
    }
}