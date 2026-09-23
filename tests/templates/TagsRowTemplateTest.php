<?php
declare(strict_types=1);

namespace App\tests\templates;

use App\tests\AbstractTestCase;

class TagsRowTemplateTest extends AbstractTestCase
{
    public function testIndexRowsRendersTagsAndAdminControls(): void
    {
        $results = [
            [
                'name' => 'api-orders',
                'domain' => 'pdv',
                'sf' => 'buyers',
                'cloudGCP' => true,
                'springBoot' => '3.2.0',
                'java' => '21',
                'mdmWorkloadVersion' => '1.0.0',
                'webUrl' => 'https://gitlab.com/api-orders',
                'tags' => ['paiement', 'checkout']
            ]
        ];

        // 1. As ROLE_ADMIN
        $htmlAdmin = self::$twig->render('common/_index_rows.html.twig', [
            'results' => $results,
            'offset' => 0,
            'session' => ['user_role' => 'ROLE_ADMIN']
        ]);

        $this->assertStringContainsString('paiement', $htmlAdmin);
        $this->assertStringContainsString('checkout', $htmlAdmin);
        $this->assertStringContainsString('delete-tag-btn', $htmlAdmin);
        $this->assertStringContainsString('add-tag-btn', $htmlAdmin);
        $this->assertStringContainsString('tag-badge', $htmlAdmin);

        // 2. As ROLE_USER (non admin)
        $htmlUser = self::$twig->render('common/_index_rows.html.twig', [
            'results' => $results,
            'offset' => 0,
            'session' => ['user_role' => 'ROLE_USER']
        ]);

        $this->assertStringContainsString('paiement', $htmlUser);
        $this->assertStringContainsString('checkout', $htmlUser);
        $this->assertStringNotContainsString('delete-tag-btn', $htmlUser);
        $this->assertStringNotContainsString('add-tag-btn', $htmlUser);
    }

    public function testMonitoringRowsRendersTags(): void
    {
        $results = [
            [
                'name' => 'api-orders',
                'domain' => 'pdv',
                'sf' => 'buyers',
                'techno' => 'java',
                'archived' => false,
                'cloudGCP' => true,
                'webUrl' => 'https://gitlab.com/api-orders',
                'tags' => ['supervision', 'core'],
                'urlActuatorInfo' => [],
                'urlHealthCheck' => [],
                'urlLogs' => [],
                'urlFronts' => [],
                'urlPubsubs' => [],
                'urlsRundeck' => [],
                'urlsDeploymentGcp' => []
            ]
        ];

        $html = self::$twig->render('common/_monitoring_rows.html.twig', [
            'results' => $results,
            'offset' => 0,
            'session' => ['user_role' => 'ROLE_ADMIN']
        ]);

        $this->assertStringContainsString('supervision', $html);
        $this->assertStringContainsString('core', $html);
        $this->assertStringContainsString('delete-tag-btn', $html);
        $this->assertStringContainsString('add-tag-btn', $html);
    }

    public function testRundeckRowsRendersTags(): void
    {
        $results = [
            [
                'name' => 'Batch Orders',
                'domain' => 'pdv',
                'sf' => 'buyers',
                'techno' => 'java',
                'webUrl' => 'https://gitlab.com/batch-orders',
                'tags' => ['batch', 'nightly'],
                'urlsRundeck' => []
            ]
        ];

        $html = self::$twig->render('common/_rundeck_rows.html.twig', [
            'results' => $results,
            'offset' => 0,
            'session' => ['user_role' => 'ROLE_ADMIN']
        ]);

        $this->assertStringContainsString('batch', $html);
        $this->assertStringContainsString('nightly', $html);
        $this->assertStringContainsString('delete-tag-btn', $html);
        $this->assertStringContainsString('add-tag-btn', $html);
    }
}
