<?php
declare(strict_types=1);

namespace App;

/**
 * Classe TestContainer
 *
 * Conteneur d'injection de dépendances pour l'environnement de test,
 * agissant désormais comme un pont vers le conteneur du Micro-Kernel de Symfony.
 */
final class TestContainer
{
    private $container;

    /**
     * Constructeur de la classe TestContainer.
     *
     * Initialise et démarre le Kernel de Symfony en environnement de test.
     */
    public function __construct()
    {
        $_SERVER['APP_ENV'] = 'test';
        $kernel = new Kernel();
        $kernel->boot();
        $this->container = $kernel->getContainer();
    }

    /**
     * Récupère une instance de service depuis le conteneur Symfony.
     */
    public function get(string $id): mixed
    {
        return $this->container->get($id);
    }
}
