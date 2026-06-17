<?php
declare(strict_types=1);

use App\model\enum\ContactTitle;
use App\model\enum\ExcludeOperatorEnum;
use App\model\enum\RatingEnum;
use App\model\enum\YesNoEnum;
if (!defined("MESSAGE_ERROR_OCCURRED_ADDING")) {
    define("MESSAGE_ERROR_OCCURRED_ADDING", "An error occurred while adding %s");
}
if (!defined("MESSAGE_CONFIRM_DELETE")) {
    define("MESSAGE_CONFIRM_DELETE", "Are you sure you want to delete %s");
}
if (!defined("MESSAGE_ERROR_OCCURRED_REMOVING")) {
    define("MESSAGE_ERROR_OCCURRED_REMOVING", "An error occurred while deleting %s");
}
if (!defined("MESSAGE_ERROR_OCCURRED_RETRIEVING")) {
    define("MESSAGE_ERROR_OCCURRED_RETRIEVING", "An error occurred while retrieving %s");
}
if (!defined("MESSAGE_ERROR_OCCURRED_UPDATING")) {
    define("MESSAGE_ERROR_OCCURRED_UPDATING", "An error occurred while updating %s");
}
if (!defined("MESSAGE_ERROR_OCCURRED_BUILDING")) {
    define("MESSAGE_ERROR_OCCURRED_BUILDING", "An error occurred while building %s");
}

return [
    // Common
    'add' => 'Add',
    'all' => 'All',
    'free_space' => 'Free disk space',
    'modify' => 'Edit',
    'download' => 'Download',
    'loading' => 'Loading...',
    'reset_filters' => 'Reset filters',
    'category' => [
        'actions' => [
            'update_a_category' => 'Edit a category',
        ],
        'messages' => [
            'confirm_delete_category' => sprintf(MESSAGE_CONFIRM_DELETE, "the category '{categoryName}'"),
            'error_occurred_adding_category' => sprintf(MESSAGE_ERROR_OCCURRED_ADDING, "the category '{categoryName}'"),
            'error_occurred_deleting_category' => sprintf(MESSAGE_ERROR_OCCURRED_REMOVING, "the category '{categoryId}'"),
            'error_occurred_retrieving_categories' => sprintf(MESSAGE_ERROR_OCCURRED_RETRIEVING, "the categories"),
            'error_occurred_updating_category' => sprintf(MESSAGE_ERROR_OCCURRED_UPDATING, "the category '{categoryName}'"),
            'error_occurred_not_found_category' => sprintf(MESSAGE_ERROR_OCCURRED_RETRIEVING, "the category '{categoryId}'"),
            'success_adding_category' => "The category '{categoryName}' has been saved",
            'success_updating_category' => "The category '{categoryName}' (id:'{categoryId}') of type '{categoryType}' has been updated",
            'success_deleting_category' => "The category '{categoryId}' has been deleted",
            'please_enter_a_name' => "Please enter a name",
            'please_select_valid_type' => "Please select valid type",
        ],
        'labels' => [
            'categories' => 'Categories',
            'categorie_s' => 'Categorie(s)', //
            'category' => 'Category',
            'add_a_category' => 'Add a category',
            'edit_the_category' => 'Edit the category',
            'delete_the_category' => 'Delete the category',
            'search_placeHolder' => "Search", //
        ],
        'columns' => [
            'category_id' => 'ID',
            'category_action' => 'Action',
            'category_active' => 'Active',
            'category_name' => 'Name',
            'category_source' => 'Source',
            'category_type' => 'Type',
        ]
    ],

    'actions' => [ //
        'cancel' => 'Cancel', //
        'close' => 'Close', //
        'delete' => 'Delete', //
        'download' => 'Download', //
        'copy_filename' => 'Copy filename', //
        'copy_url' => "Copy URL", //
        'copy_urls' => "Copy URLs", //
        'copy_cmd_dos' => 'Copy the DOS rename commands', //
        'search' => 'Search', //
        'search_files' => 'Search files', //
        'reset_filters' => 'Reset filters', //
        'refresh_filters' => 'Refresh data', //
        'update' => 'Update', //
        'update_database' => 'Update database', //
        'delete_the_stream' => 'Delete stream', //
    ], //
    'messages' => [
        'database_updated' => 'The database has been updated', //
        'error_occurred_updating_database' => sprintf(MESSAGE_ERROR_OCCURRED_UPDATING, "the database"), //
        'test_connection' => "Attempting to connect to the IPTV server. Please wait...", //
        'operation_in_progress' => 'A priority task is in progress', //
        'error_occurred_loading_page' => 'An error occurred while loading the page'
    ], //
    'labels' => [
        'action' => 'Action', //
        'close' => 'Close', //
        'equals' => 'Equals', //
        'first' => 'First', //
        'last' => 'Last', //
        'ko' => 'Ko', //
        'name' => 'Name', //
        'number_per_page' => 'Number per page', //
        'no' => 'No', //
        'no_data' => "No data", //
        'no_photo' => "No photo", //
        'password' => 'Password', //
        'ok' => 'Ok', //
        'play' => 'Play', //
        'status' => 'Status', //
        'type' => 'Type', //
        'username' => 'Username', //
        'unknown_action' => "Unknown action", //
        'yes' => 'Yes', //
    ], //
    'titles' => [
        'mister' => 'Mister',
        'miss' => 'Miss',
        'madame' => 'Mme',
        'doctor' => 'Doc'
    ],
    'roles' => [
        'super_admin' => 'Super Administrator', //
        'admin' => 'Administrator', //
        'user' => 'User' //
    ], //
    'statuses' => [
        'active' => 'Active', //
        'inactive' => 'Inactive', //
    ],

];
