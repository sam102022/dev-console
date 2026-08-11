<?php
declare(strict_types=1);

const ENVIRONMENT_PROD = 'prod';
const ENVIRONMENT_TEST = 'test';
// Chemins
const PATH_DATA = 'data';
const PATH_CACHE = 'var/cache';
const PATH_CACHE_FILE = PATH_CACHE . '/file';
const PATH_CACHE_USER = PATH_CACHE . '/user';
const PATH_IMAGES = 'public/images';

const THEME_DEFAULT = 'dark';

const LEVEL_LOG_INFO = 'info';
const LEVEL_LOG_WARN = 'warn';
const LEVEL_LOG_ERROR = 'error';
const PATH_LOGS = 'var/logs';
const LOG_FILE_DEFAULT = PATH_LOGS . '/log.log';
const LOG_CLI_FILE_DEFAULT = PATH_LOGS . '/logCli.log';
const TEST_LOG_FILE = PATH_LOGS . '/logTest.log';

const MESSAGES_SCAN_RESULTS = 'scanResults';
const MESSAGES_RUNDECK_RESULTS = 'rundeckResults';
const MESSAGES_POSTMAN = 'postman';

// Actions
const ACTION_PURGE_CACHE = 'purge_cache';

const ACTION_GITLAB_SCAN = 'scan';
const ACTION_GITLAB_TREE = 'tree';
const ACTION_GITLAB_FILE = 'file';
const ACTION_NEW_RELIC_URL = 'get_new_relic_url';

const ACTION_POSTMAN_WORKSPACES = 'getWorkspaces';
const ACTION_POSTMAN_CREATE_WORKSPACE = 'createWorkspace';
const ACTION_POSTMAN_CREATE_ENVIRONMENT = 'createEnvironment';
const ACTION_POSTMAN_IMPORT_OPENAPI = 'importOpenApi';
const ACTION_POSTMAN_GET_WORKSPACE_DETAILS = 'getWorkspaceDetails';

const ACTION_MONITORING_GET_DATA = 'getMonitoringData';
const ACTION_SAVE_COLUMNS_PREFS = 'saveColumnsPrefs';

const THEMES_COLORS = [ //
    'dark' => [ //
        'bgBody' => 'bg-dark', //
        'bgContainer' => 'bg-dark', //
        'bgSecondary' => 'bg-secondary', //
        'bgToolbar' => 'bg-secondary', //
        'colorInverse' => 'text-dark', //
        'colorLink' => 'text-white', //
        'colorText' => 'text-white' //
    ], //
    'light' => [ //
        'bgBody' => 'bg-white', //
        'bgContainer' => 'bg-light', //
        'bgSecondary' => 'bg-light', //
        'bgToolbar' => 'bg-light', //
        'colorInverse' => 'text-dark', //
        'colorLink' => 'text-primary', //
        'colorText' => 'text-dark' //
    ] //
];

// Start the session
session_start(['name' => 'dev-console']);