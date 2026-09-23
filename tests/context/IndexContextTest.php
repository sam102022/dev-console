<?php
declare(strict_types=1);

namespace App\tests\context;

use App\context\IndexContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IndexContextTest extends TestCase
{
    private IndexContext $context;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->context = new IndexContext();
    }

    /**
     * Provider for getUserId testing (parameterized)
     */
    public static function userIdProvider(): array
    {
        return [
            'userId set to 42' => [
                'sessionData' => ['userId' => 42],
                'expected' => 42
            ],
            'userId set to string 99' => [
                'sessionData' => ['userId' => '99'],
                'expected' => 99
            ],
            'userId not set' => [
                'sessionData' => [],
                'expected' => 1
            ],
            'userId set to 0' => [
                'sessionData' => ['userId' => 0],
                'expected' => 0
            ]
        ];
    }

    #[DataProvider('userIdProvider')]
    public function testGetUserId(array $sessionData, int $expected): void
    {
        $_SESSION = $sessionData;
        $this->assertEquals($expected, $this->context->getUserId());
    }

    /**
     * Provider for getTheme testing (parameterized)
     */
    public static function themeProvider(): array
    {
        return [
            'theme set to dark' => [
                'sessionData' => ['theme' => 'dark'],
                'expected' => 'dark'
            ],
            'theme set to light' => [
                'sessionData' => ['theme' => 'light'],
                'expected' => 'light'
            ],
            'theme not set' => [
                'sessionData' => [],
                'expected' => 'default'
            ]
        ];
    }

    #[DataProvider('themeProvider')]
    public function testGetTheme(array $sessionData, string $expected): void
    {
        $_SESSION = $sessionData;
        $this->assertEquals($expected, $this->context->getTheme());
    }

    public function testInitMessages(): void
    {
        $messages = $this->context->initMessages();

        $this->assertIsArray($messages);
        $this->assertArrayHasKey(MESSAGES_SCAN_RESULTS, $messages);
        $this->assertArrayHasKey(MESSAGES_RUNDECK_RESULTS, $messages);
        $this->assertArrayHasKey(MESSAGES_POSTMAN, $messages);

        $this->assertEmpty($messages[MESSAGES_SCAN_RESULTS]);
        $this->assertEmpty($messages[MESSAGES_RUNDECK_RESULTS]);
        $this->assertEmpty($messages[MESSAGES_POSTMAN]);
    }
}
