<?php
declare(strict_types=1);

namespace App\tests\templates;

use App\tests\AbstractTestCase;

class QuickFilterTemplateTest extends AbstractTestCase
{
    private array $sampleResults = [
        [
            'name' => 'api-orders',
            'domain' => 'pdv',
            'sf' => 'stores-stock',
            'cloudGCP' => true,
            'springBoot' => '3.2.0',
            'java' => '21',
            'mdmWorkloadVersion' => '1.0.0',
            'techno' => 'java',
            'archived' => false,
            'webUrl' => 'https://gitlab.com/api-orders',
            'tags' => ['paiement'],
            'urlActuatorInfo' => ['dev' => ''],
            'urlHealthCheck' => ['dev' => ''],
            'urlLogs' => ['dev' => ''],
            'urlsRundeck' => ['dev' => '']
        ]
    ];

    private array $emptyResults = [
        [
            'name' => 'empty-project',
            'domain' => '',
            'sf' => '',
            'cloudGCP' => false,
            'techno' => '',
            'archived' => false,
            'webUrl' => '',
            'tags' => []
        ]
    ];

    public function testIndexRowsRendersQuickFilterLinks(): void
    {
        $html = self::$twig->render('common/_index_rows.html.twig', [
            'results' => $this->sampleResults,
            'offset' => 0
        ]);

        $this->assertStringContainsString('class="quick-filter-link text-primary"', $html);
        $this->assertStringContainsString('data-filter-field="domain"', $html);
        $this->assertStringContainsString('data-filter-value="pdv"', $html);
        $this->assertStringContainsString('data-filter-field="sf"', $html);
        $this->assertStringContainsString('data-filter-value="stores-stock"', $html);
        $this->assertStringContainsString('data-filter-domain="pdv"', $html);

        $emptyHtml = self::$twig->render('common/_index_rows.html.twig', [
            'results' => $this->emptyResults,
            'offset' => 0
        ]);
        $this->assertStringNotContainsString('data-filter-field="domain"', $emptyHtml);
        $this->assertStringNotContainsString('data-filter-field="sf"', $emptyHtml);
    }

    public function testMonitoringRowsRendersQuickFilterLinks(): void
    {
        $html = self::$twig->render('common/_monitoring_rows.html.twig', [
            'results' => $this->sampleResults,
            'offset' => 0
        ]);

        $this->assertStringContainsString('class="quick-filter-link text-primary"', $html);
        $this->assertStringContainsString('data-filter-field="domain"', $html);
        $this->assertStringContainsString('data-filter-value="pdv"', $html);
        $this->assertStringContainsString('data-filter-field="sf"', $html);
        $this->assertStringContainsString('data-filter-value="stores-stock"', $html);
        $this->assertStringContainsString('data-filter-domain="pdv"', $html);

        $emptyHtml = self::$twig->render('common/_monitoring_rows.html.twig', [
            'results' => $this->emptyResults,
            'offset' => 0
        ]);
        $this->assertStringNotContainsString('data-filter-field="domain"', $emptyHtml);
        $this->assertStringNotContainsString('data-filter-field="sf"', $emptyHtml);
    }

    public function testRundeckRowsRendersQuickFilterLinks(): void
    {
        $html = self::$twig->render('common/_rundeck_rows.html.twig', [
            'results' => $this->sampleResults,
            'offset' => 0
        ]);

        $this->assertStringContainsString('class="quick-filter-link text-primary"', $html);
        $this->assertStringContainsString('data-filter-field="domain"', $html);
        $this->assertStringContainsString('data-filter-value="pdv"', $html);
        $this->assertStringContainsString('data-filter-field="sf"', $html);
        $this->assertStringContainsString('data-filter-value="stores-stock"', $html);
        $this->assertStringContainsString('data-filter-domain="pdv"', $html);

        $emptyHtml = self::$twig->render('common/_rundeck_rows.html.twig', [
            'results' => $this->emptyResults,
            'offset' => 0
        ]);
        $this->assertStringNotContainsString('data-filter-field="domain"', $emptyHtml);
        $this->assertStringNotContainsString('data-filter-field="sf"', $emptyHtml);
    }

    public function testTagsRowsRendersQuickFilterLinks(): void
    {
        $html = self::$twig->render('common/_tags_rows.html.twig', [
            'results' => $this->sampleResults,
            'offset' => 0
        ]);

        $this->assertStringContainsString('class="quick-filter-link text-primary"', $html);
        $this->assertStringContainsString('data-filter-field="domain"', $html);
        $this->assertStringContainsString('data-filter-value="pdv"', $html);
        $this->assertStringContainsString('data-filter-field="sf"', $html);
        $this->assertStringContainsString('data-filter-value="stores-stock"', $html);
        $this->assertStringContainsString('data-filter-domain="pdv"', $html);

        $emptyHtml = self::$twig->render('common/_tags_rows.html.twig', [
            'results' => $this->emptyResults,
            'offset' => 0
        ]);
        $this->assertStringNotContainsString('data-filter-field="domain"', $emptyHtml);
        $this->assertStringNotContainsString('data-filter-field="sf"', $emptyHtml);
    }
}
