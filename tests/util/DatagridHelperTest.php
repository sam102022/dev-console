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
}
