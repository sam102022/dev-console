<?php
declare(strict_types=1);

namespace App\parser;

use SimpleXMLElement;

class XmlParser
{
    /**
     * Récupère le contenu d'un fichier.
     *
     * @param string $xml Le contenu d'un fichier xml.
     * @return false|SimpleXMLElement
     */
    public static function parse(string $xml): false|SimpleXMLElement
    {
        return simplexml_load_string($xml);
    }
}