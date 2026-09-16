<?php
declare(strict_types=1);

namespace App\tests\service;

use App\service\UserPreferencesService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserPreferencesServiceTest extends TestCase
{
    private UserPreferencesService $service;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->service = new UserPreferencesService();
    }

    /**
     * Provider for get testing (parameterized)
     */
    public static function getProvider(): array
    {
        return [
            'key exists' => [
                'key' => 'theme',
                'default' => 'light',
                'sessionData' => ['theme' => 'dark'],
                'expected' => 'dark'
            ],
            'key does not exist returns default' => [
                'key' => 'language',
                'default' => 'en',
                'sessionData' => ['theme' => 'dark'],
                'expected' => 'en'
            ],
            'empty preferences array' => [
                'key' => 'columns',
                'default' => ['id'],
                'sessionData' => [],
                'expected' => ['id']
            ],
            'preferences key totally absent' => [
                'key' => 'something',
                'default' => null,
                'sessionData' => null,
                'expected' => null
            ]
        ];
    }

    #[DataProvider('getProvider')]
    public function testGet(string $key, mixed $default, ?array $sessionData, mixed $expected): void
    {
        if ($sessionData !== null) {
            $_SESSION['user_preferences'] = $sessionData;
        } else {
            unset($_SESSION['user_preferences']);
        }

        $this->assertEquals($expected, $this->service->get($key, $default));
    }

    /**
     * Provider for set testing (parameterized)
     */
    public static function setProvider(): array
    {
        return [
            'set string value' => [
                'key' => 'theme',
                'value' => 'dark'
            ],
            'set array value' => [
                'key' => 'columns',
                'value' => ['name', 'status']
            ],
            'set int value' => [
                'key' => 'rows_per_page',
                'value' => 50
            ],
            'set null value' => [
                'key' => 'active_tab',
                'value' => null
            ]
        ];
    }

    #[DataProvider('setProvider')]
    public function testSet(string $key, mixed $value): void
    {
        $this->service->set($key, $value);

        $this->assertArrayHasKey('user_preferences', $_SESSION);
        $this->assertArrayHasKey($key, $_SESSION['user_preferences']);
        $this->assertEquals($value, $_SESSION['user_preferences'][$key]);
    }
}
