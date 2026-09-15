<?php
declare(strict_types=1);

namespace App\tests\config;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    /**
     * Provider for defined constants and their expected types or values (parameterized)
     */
    public static function constantProvider(): array
    {
        return [
            'ENVIRONMENT_PROD' => ['ENVIRONMENT_PROD', 'prod'],
            'ENVIRONMENT_TEST' => ['ENVIRONMENT_TEST', 'test'],
            'PATH_DATA' => ['PATH_DATA', 'data'],
            'PATH_CACHE' => ['PATH_CACHE', 'var/cache'],
            'PATH_CACHE_FILE' => ['PATH_CACHE_FILE', 'var/cache/file'],
            'PATH_CACHE_USER' => ['PATH_CACHE_USER', 'var/cache/user'],
            'PATH_IMAGES' => ['PATH_IMAGES', 'public/images'],
            'THEME_DEFAULT' => ['THEME_DEFAULT', 'dark'],
            'LEVEL_LOG_INFO' => ['LEVEL_LOG_INFO', 'info'],
            'LEVEL_LOG_WARN' => ['LEVEL_LOG_WARN', 'warn'],
            'LEVEL_LOG_ERROR' => ['LEVEL_LOG_ERROR', 'error'],
            'PATH_LOGS' => ['PATH_LOGS', 'var/logs'],
            'LOG_FILE_DEFAULT' => ['LOG_FILE_DEFAULT', 'var/logs/log.log'],
            'LOG_CLI_FILE_DEFAULT' => ['LOG_CLI_FILE_DEFAULT', 'var/logs/logCli.log'],
            'TEST_LOG_FILE' => ['TEST_LOG_FILE', 'var/logs/logTest.log'],
            'MESSAGES_SCAN_RESULTS' => ['MESSAGES_SCAN_RESULTS', 'scanResults'],
            'MESSAGES_RUNDECK_RESULTS' => ['MESSAGES_RUNDECK_RESULTS', 'rundeckResults'],
            'MESSAGES_POSTMAN' => ['MESSAGES_POSTMAN', 'postman'],
            'ACTION_PURGE_CACHE' => ['ACTION_PURGE_CACHE', 'purge_cache'],
            'ACTION_GITLAB_SCAN' => ['ACTION_GITLAB_SCAN', 'scan'],
            'ACTION_GITLAB_TREE' => ['ACTION_GITLAB_TREE', 'tree'],
            'ACTION_GITLAB_FILE' => ['ACTION_GITLAB_FILE', 'file'],
            'ACTION_NEW_RELIC_URL' => ['ACTION_NEW_RELIC_URL', 'get_new_relic_url'],
            'ACTION_POSTMAN_WORKSPACES' => ['ACTION_POSTMAN_WORKSPACES', 'getWorkspaces'],
            'ACTION_POSTMAN_CREATE_WORKSPACE' => ['ACTION_POSTMAN_CREATE_WORKSPACE', 'createWorkspace'],
            'ACTION_POSTMAN_CREATE_ENVIRONMENT' => ['ACTION_POSTMAN_CREATE_ENVIRONMENT', 'createEnvironment'],
            'ACTION_POSTMAN_IMPORT_OPENAPI' => ['ACTION_POSTMAN_IMPORT_OPENAPI', 'importOpenApi'],
            'ACTION_POSTMAN_GET_WORKSPACE_DETAILS' => ['ACTION_POSTMAN_GET_WORKSPACE_DETAILS', 'getWorkspaceDetails'],
            'ACTION_MONITORING_GET_DATA' => ['ACTION_MONITORING_GET_DATA', 'getMonitoringData'],
            'ACTION_SAVE_COLUMNS_PREFS' => ['ACTION_SAVE_COLUMNS_PREFS', 'saveColumnsPrefs'],
            'ACTION_GET_DATAGRID_ROWS' => ['ACTION_GET_DATAGRID_ROWS', 'getDatagridRows']
        ];
    }

    #[DataProvider('constantProvider')]
    public function testConstantsAreDefined(string $constantName, mixed $expectedValue): void
    {
        $this->assertTrue(defined($constantName), "Constant $constantName is not defined.");
        $this->assertEquals($expectedValue, constant($constantName));
    }

    public function testThemeColorsConstant(): void
    {
        $this->assertTrue(defined('THEMES_COLORS'));
        $colors = THEMES_COLORS;

        $this->assertIsArray($colors);
        $this->assertArrayHasKey('dark', $colors);
        $this->assertArrayHasKey('light', $colors);

        $this->assertEquals('bg-dark', $colors['dark']['bgBody']);
        $this->assertEquals('bg-white', $colors['light']['bgBody']);
    }
}
