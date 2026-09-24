<?php
declare(strict_types=1);

namespace App\tests\util;

use App\util\DatagridHelper;
use PHPUnit\Framework\TestCase;

class DatagridHelperTest extends TestCase
{
    public function testProcessPaginationAndFiltering(): void
    {
        $items = [
            (object)['domain' => 'finance', 'name' => 'proj1', 'java' => '17'],
            (object)['domain' => 'hr', 'name' => 'proj2', 'java' => '11'],
            (object)['domain' => 'finance', 'name' => 'proj3', 'java' => '21'],
        ];

        $filters = ['domain' => 'finance'];
        $result = DatagridHelper::process($items, $filters, 'name', 'desc', 1, 1);

        $this->assertEquals(2, $result['totalRows']);
        $this->assertCount(1, $result['items']);
        $this->assertEquals('proj3', $result['items'][0]->name);
    }

    public function testProcessArchivedFilter(): void
    {
        $items = [
            (object)['name' => 'proj1', 'archived' => true],
            (object)['name' => 'proj2', 'archived' => false],
            (object)['name' => 'proj3', 'archived' => 1],
            (object)['name' => 'proj4', 'archived' => 0],
        ];

        // Filter archived = 'oui' (should match true and 1)
        $resultOui = DatagridHelper::process($items, ['archived' => 'oui'], 'name', 'asc', 1, 10);
        $this->assertEquals(2, $resultOui['totalRows']);
        $this->assertEquals('proj1', $resultOui['items'][0]->name);
        $this->assertEquals('proj3', $resultOui['items'][1]->name);

        // Filter archived = 'non' (should match false and 0)
        $resultNon = DatagridHelper::process($items, ['archived' => 'non'], 'name', 'asc', 1, 10);
        $this->assertEquals(2, $resultNon['totalRows']);
        $this->assertEquals('proj2', $resultNon['items'][0]->name);
        $this->assertEquals('proj4', $resultNon['items'][1]->name);
    }

    public function testProcessGcpFilter(): void
    {
        $items = [
            (object)['name' => 'proj1', 'cloudGCP' => true],
            (object)['name' => 'proj2', 'cloudGCP' => false],
        ];

        // Filter cloudGCP = 'oui'
        $resultOui = DatagridHelper::process($items, ['cloudGCP' => 'oui'], 'name', 'asc', 1, 10);
        $this->assertEquals(1, $resultOui['totalRows']);
        $this->assertEquals('proj1', $resultOui['items'][0]->name);

        // Filter cloudGCP = 'non'
        $resultNon = DatagridHelper::process($items, ['cloudGCP' => 'non'], 'name', 'asc', 1, 10);
        $this->assertEquals(1, $resultNon['totalRows']);
        $this->assertEquals('proj2', $resultNon['items'][0]->name);
    }

    public function testProcessStatusFilter(): void
    {
        $items = [
            (object)['name' => 'proj1', 'java' => '17', 'springBoot' => '3.1'], // OK
            (object)['name' => 'proj2', 'java' => '11', 'springBoot' => '3.1'], // Java obsolète (<17)
            (object)['name' => 'proj3', 'java' => '17', 'springBoot' => '2.7'], // Spring Boot ancien (<3)
        ];

        // Filter status = 'OK'
        $resultOk = DatagridHelper::process($items, ['status' => 'OK'], 'name', 'asc', 1, 10);
        $this->assertEquals(1, $resultOk['totalRows']);
        $this->assertEquals('proj1', $resultOk['items'][0]->name);

        // Filter status = 'Java obsolète'
        $resultJava = DatagridHelper::process($items, ['status' => 'Java obsolète'], 'name', 'asc', 1, 10);
        $this->assertEquals(1, $resultJava['totalRows']);
        $this->assertEquals('proj2', $resultJava['items'][0]->name);

        // Filter status = 'Spring Boot ancien'
        $resultSb = DatagridHelper::process($items, ['status' => 'Spring Boot ancien'], 'name', 'asc', 1, 10);
        $this->assertEquals(1, $resultSb['totalRows']);
        $this->assertEquals('proj3', $resultSb['items'][0]->name);
    }

    public function testProcessFreeTextCaseInsensitiveFilter(): void
    {
        $items = [
            (object)['name' => 'Project Alpha', 'java' => 17, 'domain' => 'Finance'],
            (object)['name' => 'project beta', 'java' => 11, 'domain' => 'HR'],
            (object)['name' => 'PROJECT GAMMA', 'java' => 21, 'domain' => 'finance'],
        ];

        // Filter name = 'project' (should match all case-insensitively)
        $resultName = DatagridHelper::process($items, ['name' => 'project'], 'name', 'asc', 1, 10);
        $this->assertEquals(3, $resultName['totalRows']);

        // Filter name = 'ALPHA' (should match 'Project Alpha')
        $resultAlpha = DatagridHelper::process($items, ['name' => 'ALPHA'], 'name', 'asc', 1, 10);
        $this->assertEquals(1, $resultAlpha['totalRows']);
        $this->assertEquals('Project Alpha', $resultAlpha['items'][0]->name);

        // Filter domain = 'FINANCE' (should match both 'Finance' and 'finance')
        $resultDomain = DatagridHelper::process($items, ['domain' => 'FINANCE'], 'name', 'asc', 1, 10);
        $this->assertEquals(2, $resultDomain['totalRows']);

        // Filter java = '17' (should match numeric 17)
        $resultJava = DatagridHelper::process($items, ['java' => '17'], 'name', 'asc', 1, 10);
        $this->assertEquals(1, $resultJava['totalRows']);
        $this->assertEquals('Project Alpha', $resultJava['items'][0]->name);
    }

    public function testProcessFilterByNameAndTags(): void
    {
        $items = [
            [
                'name' => 'api-orders',
                'tags' => ['paiement', 'checkout']
            ],
            [
                'name' => 'flow-billing',
                'tags' => ['facturation', 'paiement']
            ],
            [
                'name' => 'batch-customers',
                'tags' => ['client']
            ],
            [
                'name' => 'integ-partners',
                'tags' => []
            ]
        ];

        // 1. Filtrer par nom direct
        $resultOrders = DatagridHelper::process($items, ['name' => 'orders'], 'name', 'asc', 1, 10);
        $this->assertEquals(1, $resultOrders['totalRows']);
        $this->assertEquals('api-orders', $resultOrders['items'][0]['name']);

        // 2. Filtrer par tag ("paiement" doit remonter api-orders et flow-billing)
        $resultTag = DatagridHelper::process($items, ['name' => 'paiement'], 'name', 'asc', 1, 10);
        $this->assertEquals(2, $resultTag['totalRows']);
        $names = array_column($resultTag['items'], 'name');
        $this->assertContains('api-orders', $names);
        $this->assertContains('flow-billing', $names);

        // 3. Filtrer par tag avec casse différente ("CHECKOUT")
        $resultCase = DatagridHelper::process($items, ['name' => 'CHECKOUT'], 'name', 'asc', 1, 10);
        $this->assertEquals(1, $resultCase['totalRows']);
        $this->assertEquals('api-orders', $resultCase['items'][0]['name']);

        // 4. Filtrer avec un terme inexistant
        $resultNone = DatagridHelper::process($items, ['name' => 'inconnu'], 'name', 'asc', 1, 10);
        $this->assertEquals(0, $resultNone['totalRows']);
        $this->assertEmpty($resultNone['items']);
    }

    public function testProcessExplicitTagFilter(): void
    {
        $items = [
            [
                'name' => 'api-orders',
                'tags' => ['paiement', 'checkout']
            ],
            [
                'name' => 'flow-billing',
                'tags' => ['facturation', 'paiement']
            ],
            [
                'name' => 'batch-customers',
                'tags' => ['client']
            ],
            [
                'name' => 'integ-partners',
                'tags' => []
            ]
        ];

        // 1. Filtrer par tag direct "checkout"
        $resultCheckout = DatagridHelper::process($items, ['tag' => 'checkout'], 'name', 'asc', 1, 10);
        $this->assertEquals(1, $resultCheckout['totalRows']);
        $this->assertEquals('api-orders', $resultCheckout['items'][0]['name']);

        // 2. Filtrer par tag "paiement" (doit retourner api-orders et flow-billing)
        $resultPaiement = DatagridHelper::process($items, ['tag' => 'paiement'], 'name', 'asc', 1, 10);
        $this->assertEquals(2, $resultPaiement['totalRows']);

        // 3. Filtrer par tag partiel / casse différente ("PAIE")
        $resultPaie = DatagridHelper::process($items, ['tag' => 'PAIE'], 'name', 'asc', 1, 10);
        $this->assertEquals(2, $resultPaie['totalRows']);

        // 4. Filtrer par tag inexistant
        $resultNone = DatagridHelper::process($items, ['tag' => 'inexistant'], 'name', 'asc', 1, 10);
        $this->assertEquals(0, $resultNone['totalRows']);
        $this->assertEmpty($resultNone['items']);
    }
}
