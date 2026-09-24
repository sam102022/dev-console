<?php
declare(strict_types=1);

namespace App\tests\templates;

use App\controller\TagAdminController;
use App\controller\UserAdminController;
use App\tests\AbstractTestCase;

class TagAdminTemplateTest extends AbstractTestCase
{
    public function testBaseTemplateRendersAdminDropdownWhenAdmin(): void
    {
        $html = self::$twig->render('base.html.twig', [
            'current_route' => TagAdminController::ROUTE_TAGS,
            'session' => [
                'user_id' => 1,
                'user_role' => 'ROLE_ADMIN',
                'user_email' => 'admin@mdm.com'
            ]
        ]);

        $this->assertStringContainsString('Administration', $html);
        $this->assertStringContainsString('?page=' . UserAdminController::ROUTE_USERS, $html);
        $this->assertStringContainsString('?page=' . TagAdminController::ROUTE_TAGS, $html);
        $this->assertStringContainsString('Gestion des tags', $html);
    }

    public function testTagsRowsRendersManageButtonAndTags(): void
    {
        $results = [
            [
                'name' => 'api-orders',
                'domain' => 'pdv',
                'tags' => ['paiement', 'checkout']
            ]
        ];

        $html = self::$twig->render('common/_tags_rows.html.twig', [
            'results' => $results,
            'offset' => 0
        ]);

        $this->assertStringContainsString('api-orders', $html);
        $this->assertStringContainsString('paiement', $html);
        $this->assertStringContainsString('checkout', $html);
        $this->assertStringContainsString('btn-manage-tags', $html);
        $this->assertStringContainsString('Gérer les tags', $html);
    }

    public function testTagsPageRendersCardAndDatalist(): void
    {
        $html = self::$twig->render('tags.html.twig', [
            'current_route' => TagAdminController::ROUTE_TAGS,
            'allTags' => ['paiement', 'checkout', 'batch'],
            'session' => [
                'user_id' => 1,
                'user_role' => 'ROLE_ADMIN',
                'user_email' => 'admin@mdm.com'
            ]
        ]);

        $this->assertStringContainsString('Gestion des Tags', $html);
        $this->assertStringContainsString('3 tag(s) existant(s)', $html);
        $this->assertStringContainsString('id="existing-tags-datalist"', $html);
        $this->assertStringContainsString('<option value="paiement"></option>', $html);
        $this->assertStringContainsString('<option value="checkout"></option>', $html);
        $this->assertStringContainsString('<option value="batch"></option>', $html);
        $this->assertStringContainsString('tagsDatagrid()', $html);
    }
}
