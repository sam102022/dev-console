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
                'sf' => 'buyers',
                'tags' => ['paiement', 'checkout']
            ]
        ];

        $html = self::$twig->render('common/_tags_rows.html.twig', [
            'results' => $results,
            'offset' => 0
        ]);

        $this->assertStringContainsString('api-orders', $html);
        $this->assertStringContainsString('class="col-domain"', $html);
        $this->assertStringContainsString('pdv', $html);
        $this->assertStringContainsString('class="col-sf"', $html);
        $this->assertStringContainsString('buyers', $html);
        $this->assertStringContainsString('paiement', $html);
        $this->assertStringContainsString('checkout', $html);
        $this->assertStringContainsString('btn-manage-tags', $html);
        $this->assertStringContainsString('Gérer les tags', $html);
        $this->assertStringContainsString('manageProjectTags(this)', $html);
        $this->assertStringContainsString('data-tags="&#x5B;&quot;paiement&quot;,&quot;checkout&quot;&#x5D;"', $html);
    }

    public function testTagsPageRendersCardAndDatalist(): void
    {
        $html = self::$twig->render('tags.html.twig', [
            'current_route' => TagAdminController::ROUTE_TAGS,
            'allTags' => ['paiement', 'checkout', 'batch'],
            'domains' => ['pdv' => 'Point de vente'],
            'sfs' => ['buyers' => 'buyers'],
            'session' => [
                'user_id' => 1,
                'user_role' => 'ROLE_ADMIN',
                'user_email' => 'admin@mdm.com'
            ]
        ]);

        $this->assertStringContainsString('Gestion des Tags', $html);
        $this->assertStringContainsString('3 tag(s) existant(s)', $html);
        $this->assertStringContainsString('id="filter_domain"', $html);
        $this->assertStringContainsString('id="filter_sf"', $html);
        $this->assertStringContainsString('sortBy(\'domain\')', $html);
        $this->assertStringContainsString('sortBy(\'sf\')', $html);
        $this->assertStringContainsString('<option value="pdv">Point de vente</option>', $html);
        $this->assertStringContainsString('<option value="buyers">buyers</option>', $html);
        $this->assertStringContainsString('id="existing-tags-datalist"', $html);
        $this->assertStringContainsString('<option value="paiement"></option>', $html);
        $this->assertStringContainsString('<option value="checkout"></option>', $html);
        $this->assertStringContainsString('<option value="batch"></option>', $html);
        $this->assertStringContainsString('tagsDatagrid()', $html);
        $this->assertStringContainsString('id="pagination-info"', $html);
        $this->assertStringContainsString('aria-label="Page navigation"', $html);
        $this->assertStringContainsString('getPagesToShow()', $html);
        $this->assertStringNotContainsString('totalRowsText', $html);
        $this->assertStringNotContainsString('paginationHtml', $html);
    }

    public function testTagsRowsEscapesSpecialCharactersSafely(): void
    {
        $results = [
            [
                'name' => '<script>alert(1)</script>',
                'domain' => 'pdv&co',
                'tags' => ['tag<one>', 'tag"two"']
            ]
        ];

        $html = self::$twig->render('common/_tags_rows.html.twig', [
            'results' => $results,
            'offset' => 0
        ]);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;one&gt;', $html);
    }
}
