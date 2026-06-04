<?php
declare(strict_types=1);

namespace App\parser;

use SimpleXMLElement;

class MavenParser
{
    /**
     * Analyse le contenu d'un fichier pom.xml pour extraire les versions de Spring Boot et de Java.
     *
     * @param string $xml Le contenu du fichier pom.xml.
     * @return null[]
     */
    public function parsePomXml(string $xml): array
    {
        $data = [
            'springBoot' => null,
            'java' => null
        ];

        $xmlObj = $this->parse($xml);

        if (!$xmlObj) {
            return $data;
        }

        // Spring Boot
        if (isset($xmlObj->parent->groupId) &&
            (string)$xmlObj->parent->groupId === 'org.springframework.boot') {
            $data['springBoot'] = (string)$xmlObj->parent->version;
        }

        // Java
        if (isset($xmlObj->properties->{'java.version'})) {
            $data['java'] = (string)$xmlObj->properties->{'java.version'};
        }

        return $data;
    }

    /**
     * Récupère le contenu d'un fichier.
     *
     * @param string $xml Le contenu d'un fichier xml.
     * @return false|SimpleXMLElement
     */
    private function parse(string $xml): false|SimpleXMLElement
    {
        return simplexml_load_string($xml);
    }
}