<?php
declare(strict_types=1);

namespace App\parser;

use Symfony\Component\Yaml\Yaml;

class YamlParser
{
    /**
     * Récupère le contenu d'un fichier .yaml
     *
     * @param string $content Le contenu d'un fichier yaml.
     * @return array|null
     */
    public static function parse(string $content): ?array
    {
        if (empty($content)) {
            return null;
        }
        return Yaml::parse($content);
    }
}
