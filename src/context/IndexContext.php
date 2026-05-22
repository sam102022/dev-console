<?php
declare(strict_types=1);

namespace App\context;

/**
 * Classe IndexContext
 *
 * Gère le contexte de la session utilisateur pour la partie principale.
 * Fournit un accès centralisé et sécurisé aux données de session telles que
 * l'identifiant de l'utilisateur, le thème, l'onglet actif et les conteneurs d'affichage.
 */
class IndexContext
{
    /**
     * Récupère l'identifiant de l'utilisateur connecté.
     *
     * @return int L'identifiant de l'utilisateur.
     */
    public function getUserId(): int
    {
        return (int) ($_SESSION['userId'] ?? 1);
    }

    /**
     * Récupère le thème d'affichage actuel.
     *
     * @return string Le nom du thème.
     */
    public function getTheme(): string
    {
        return $_SESSION['theme'] ?? 'default';
    }

    /**
     * Initialise la structure du tableau des messages qui sera stocké en session.
     *
     * @return array Un tableau structuré pour stocker les messages par catégorie.
     */
    public function initMessages(): array
    {
        return [
            MESSAGES_SCAN_RESULTS => [],
            MESSAGES_POSTMAN => []
        ];
    }
}
