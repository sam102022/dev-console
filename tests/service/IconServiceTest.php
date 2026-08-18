<?php
declare(strict_types=1);

namespace App\tests\service;

use App\service\IconService;
use App\tests\AbstractTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Classe IconServiceTest
 *
 * Tests unitaires paramétrés pour le service IconService.
 */
class IconServiceTest extends AbstractTestCase
{
    /**
     * Teste que l'instance récupérée est bien un singleton de type IconService.
     */
    public function testGetInstance(): void
    {
        $instance1 = IconService::getInstance();
        $instance2 = IconService::getInstance();

        $this->assertInstanceOf(IconService::class, $instance1);
        $this->assertSame($instance1, $instance2);
    }

    /**
     * Teste la récupération du balisage d'icônes existantes ou non.
     */
    #[DataProvider('iconDataProvider')]
    public function testGetIconMarkup(string $key, string $expectedContains): void
    {
        $service = IconService::getInstance();
        $markup = $service->get($key);

        if ($expectedContains === '') {
            $this->assertEmpty($markup);
        } else {
            $this->assertStringContainsString($expectedContains, $markup);
        }
    }

    /**
     * Fournisseur de données pour tester la récupération des icônes.
     */
    public static function iconDataProvider(): array
    {
        return [
            'icon_calendar' => ['calendar', 'fa-calendar'],
            'icon_delete' => ['delete', 'fa-trash'],
            'icon_edit' => ['edit', 'fa-pen'],
            'icon_refresh' => ['refresh', 'fa-sync'],
            'icon_non_existent' => ['non_existent_key_9999', ''],
            'nested_key_empty' => ['nested.non.existent', ''],
        ];
    }

    /**
     * Teste que getAll() retourne la configuration complète des icônes.
     */
    public function testGetAll(): void
    {
        $service = IconService::getInstance();
        $icons = $service->getAll();

        $this->assertIsArray($icons);
        $this->assertArrayHasKey('calendar', $icons);
        $this->assertArrayHasKey('edit', $icons);
    }
}
