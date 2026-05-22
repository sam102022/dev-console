<?php
declare(strict_types=1);

namespace App\util;

/**
 * Classe UtilsLog
 *
 * Fournit des méthodes utilitaires pour la gestion des logs.
 */
class UtilsLog
{
    public const string LINE = ' line ';

    /**
     * Construit un préfixe standard pour les messages de log.
     *
     * @param string $className Nom de la classe d'où provient le log.
     * @param string $functionName Nom de la fonction d'où provient le log.
     * @param int $lineNumber Numéro de la ligne d'où provient le log.
     */
    public static function prefixLog(string $className, string $functionName, int $lineNumber): string
    {
        return $functionName . self::LINE . $lineNumber . ' - ';
    }
}
