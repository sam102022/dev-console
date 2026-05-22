<?php
declare(strict_types=1);

namespace App\model;

use JsonSerializable;

/**
 * Classe AbstractModel
 *
 * Méthodes communes à tous les modèles.
 */
class AbstractModel implements JsonSerializable
{
    /**
     * Spécifie les données qui doivent être sérialisées en JSON.
     */
    public function jsonSerialize(): array
    {
        $props = (array) $this;
        $result = [];
        foreach ($props as $key => $value) {
            if (preg_match('/^\0.*\0(.+)$/', $key, $m)) {
                $name = $m[1];
            } else {
                $name = $key;
            }
            $result[$name] = $value;
        }
        return $result;
    }
}
