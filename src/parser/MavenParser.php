<?php
declare(strict_types=1);

namespace App\parser;

class MavenParser
{
    public function parse(string $xml): array
    {
        $data = [
            'springBoot' => null,
            'java' => null
        ];

        $xmlObj = simplexml_load_string($xml);

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
}