<?php
declare(strict_types=1);

namespace App;

/**
 * Pont d'usine de création de services pour compatibilité entre les Closures d'AbstractContainer et Symfony DI.
 */
class ServiceFactory
{
    private static array $closures = [];

    /**
     * Enregistre une closure associée à un service.
     */
    public static function register(string $id, callable $closure): void
    {
        self::$closures[$id] = $closure;
    }

    /**
     * Crée le service en invoquant sa closure enregistrée.
     */
    public static function create(string $id, $container): mixed
    {
        if (!isset(self::$closures[$id])) {
            throw new \RuntimeException("Aucune usine de création enregistrée pour le service : " . $id);
        }
        return (self::$closures[$id])($container);
    }
}
