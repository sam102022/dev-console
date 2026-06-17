<?php
declare(strict_types=1);

use App\model\enum\ExcludeOperatorEnum;
use App\model\enum\RatingEnum;
use App\model\enum\YesNoEnum;
if (!defined("MESSAGE_ERROR_OCCURRED_ADDING")) {
    define("MESSAGE_ERROR_OCCURRED_ADDING", "Une erreur est survenue pendant l'ajout %s");
}
if (!defined("MESSAGE_CONFIRM_DELETE")) {
    define("MESSAGE_CONFIRM_DELETE", "Etes-vous sûr de vouloir supprimer %s");
}
if (!defined("MESSAGE_ERROR_OCCURRED_REMOVING")) {
    define("MESSAGE_ERROR_OCCURRED_REMOVING", "Une erreur est survenue pendant la suppression %s");
}
if (!defined("MESSAGE_ERROR_OCCURRED_RETRIEVING")) {
    define("MESSAGE_ERROR_OCCURRED_RETRIEVING", "Une erreur est survenue pendant la récupération %s");
}
if (!defined("MESSAGE_ERROR_OCCURRED_UPDATING")) {
    define("MESSAGE_ERROR_OCCURRED_UPDATING", "Une erreur est survenue pendant la mise à jour %s");
}
if (!defined("MESSAGE_ERROR_OCCURRED_BUILDING")) {
    define("MESSAGE_ERROR_OCCURRED_BUILDING", "Une erreur est survenue pendant la construction %s");
}

return [
    // Communs
    'add' => 'Ajouter', //
    'all' => 'Tous', //
    'free_space' => 'Espace disque libre', //
    'modify' => 'Modifier', //
    'download' => 'Télécharger', //
    'loading' => 'Chargement...', //
    'reset_filters' => 'Réinitialisation les filtres', //
    'category' => [ //
        'actions' => [
            'update_a_category' => 'Modifier une category', //
        ], //
        'messages' => [
            'confirm_delete_category' => sprintf(MESSAGE_CONFIRM_DELETE, "la catégorie '{categoryName}'"),
            'error_occurred_adding_category' => sprintf(MESSAGE_ERROR_OCCURRED_ADDING, "de la catégorie '{categoryName}'"),
            'error_occurred_deleting_category' => sprintf(MESSAGE_ERROR_OCCURRED_REMOVING, "de la catégorie '{categoryId}'"),
            'error_occurred_retrieving_categories' => sprintf(MESSAGE_ERROR_OCCURRED_RETRIEVING, "des catégories"),
            'error_occurred_updating_category' => sprintf(MESSAGE_ERROR_OCCURRED_UPDATING, "de la catégorie '{categoryName}'"),
            'error_occurred_not_found_category' => sprintf(MESSAGE_ERROR_OCCURRED_RETRIEVING, "la catégorie '{categoryId}'"), //
            'success_adding_category' => "La catégorie '{categoryName}' a été enregistrée", //
            'success_updating_category' => "La catégorie '{categoryName}' (id:'{categoryId}') de type '{categoryType}' a été mise à jour", //
            'success_deleting_category' => "La catégorie '{categoryId}' a été supprimée", //
            'please_enter_a_name' => "Veuillez saisir un nom",
            'please_select_valid_type' => "Veuillez sélectionner un type valide",
        ], //
        'labels' => [
            'categories' => 'Catégories', //
            'categorie_s' => 'Catégorie(s)', //
            'category' => 'Catégorie', //
            'add_a_category' => 'Ajouter une catégorie',
            'edit_the_category' => 'Editer la catégorie',
            'delete_the_category' => 'Supprimer la catégorie',
            'search_placeHolder' => "Rechercher", //
        ], //
        'columns' => [
            'category_id' => 'Id', //
            'category_action' => 'Action', //
            'category_active' => 'Actif', //
            'category_name' => 'Nom', //
            'category_source' => 'Source', //
            'category_type' => 'Type', //
        ] //
    ], //
    'actions' => [ //
        'cancel' => 'Annuler', //
        'close' => 'Fermer', //
        'delete' => 'Supprimer', //
        'download' => 'Télécharger', //
        'copy_filename' => 'Copier le nom du fichier', //
        'copy_url' => "Copier l'url", //
        'copy_urls' => "Copier les URLs", //
        "copy_cmd_dos" => 'Copier les commandes de renommage DOS', //
        'search' => 'Rechercher', //
        'search_files' => 'Rechercher les fichiers', //
        'reset_filters' => 'Réinitialiser les filtres', //
        'refresh_filters' => 'Rafraîchir les données', //
        'update' => 'Mise à jour', //
        'update_database' => 'Mettre à jour la base de données', //
        'delete_the_stream' => 'Supprimer le flux', //
    ], //
    'messages' => [
        'database_updated' => 'La base de données a été mise à jour', //
        'error_occurred_updating_database' => sprintf(MESSAGE_ERROR_OCCURRED_UPDATING, "de la base de données"), //
        'test_connection' => "Tentative de connexion au serveur IPTV. Veuillez patienter...", //
        'operation_in_progress' => 'Une opération prioritaire est en cours', //
        'error_occurred_loading_page' => 'Erreur pendant le chargement de la page'
    ], //
    'labels' => [
        'action' => 'Action', //
        'close' => 'Fermer', //
        'equals' => 'Egale', //
        'first' => 'Premier', //
        'last' => 'Dernier', //
        'ko' => 'Ko', //
        'name' => 'Nom', //
        'number_per_page' => 'Nombre par page', //
        'no' => 'Non', //
        'no_data' => "Aucune données", //
        'no_photo' => "Pas de photo", //
        'password' => 'Mot de passe', //
        'ok' => 'Ok', //
        'play' => 'Lecture', //
        'status' => 'Statut', //
        'type' => 'Type', //
        'username' => 'Utilisateur', //
        'unknown_action' => "Action inconnue",
        'yes' => 'Oui', //
    ], //
    'titles' => [
        'mister' => 'Mr',
        'miss' => 'Mlle',
        'madame' => 'Mme',
        'doctor' => 'Dr'
    ],
    'roles' => [
        'super_admin' => 'Super Administrateur', //
        'admin' => 'Administrateur', //
        'user' => 'Utilisateur' //
    ], //
    'statuses' => [
        'active' => 'Actif', //
        'inactive' => 'Inactif' //
    ]
];
