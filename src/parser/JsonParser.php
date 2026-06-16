<?php
declare(strict_types=1);

namespace App\parser;

class JsonParser
{
    /**
     * Récupère le contenu d'un fichier.
     *
     * @param string $content Le contenu d'un fichier json.
     * @return false|array
     */
    public static function parse(string $content): false|array
    {
        return json_decode($content, true);
    }
}