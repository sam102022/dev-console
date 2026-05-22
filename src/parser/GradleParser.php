<?php
declare(strict_types=1);

namespace App\parser;

class GradleParser
{
    public function parse(string $content): array
    {
        $data = [
            'springBoot' => null,
            'java' => null
        ];

        // Spring Boot
        if (preg_match("/org\\.springframework\\.boot['\"]?\\)?\\s*version\\s*['\"]([0-9.]+)['\"]/", $content, $m)) {
            $data['springBoot'] = $m[1];
        }

        // Java
        if (preg_match("/sourceCompatibility\\s*=\\s*['\"]([0-9]+)['\"]/", $content, $m)) {
            $data['java'] = $m[1];
        }

        return $data;
    }
}