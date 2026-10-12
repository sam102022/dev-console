<?php
declare(strict_types=1);

namespace App\parser;

class ChartParser
{
    /**
     * Analyse le contenu d'un fichier Chart.yaml pour extraire la version de la dépendance mdm-workload.
     *
     * @param string $content Le contenu du fichier Chart.yaml.
     * @return string|null
     */
    public function parseChartYaml(string $content): ?string
    {
        if (empty($content)) {
            return null;
        }

        $chart = YamlParser::parse($content);
        $dependencies = $chart['dependencies'] ?? [];

        foreach ($dependencies as $dependency) {
            if (isset($dependency['name']) && $dependency['name'] === 'mdm-workload') {
                return $dependency['version'] ?? null;
            }
        }

        return null;
    }

    /**
     * Analyse le contenu d'un fichier values-<env>.yaml pour extraire les extraHosts de mdm-workload.
     *
     * @param string $content Le contenu du fichier values-<env>.yaml.
     * @return array{data: list<string>, management-api: list<string>}
     */
    public function parseValuesYaml(string $content): array
    {
        $default = [
            'data' => [],
            'management-api' => [],
        ];

        if (trim($content) === '') {
            return $default;
        }

        try {
            $parsed = YamlParser::parse($content);
        } catch (\Throwable) {
            return $default;
        }

        if (!is_array($parsed)) {
            return $default;
        }

        $istio = $parsed['mdm-workload']['istioVirtualServices'] ?? [];
        if (!is_array($istio)) {
            return $default;
        }

        $extractHosts = static function (mixed $section): array {
            if (!is_array($section)) {
                return [];
            }
            $extraHosts = $section['extraHosts'] ?? [];
            if (!is_array($extraHosts)) {
                return [];
            }
            return array_values(array_filter(
                array_map('strval', $extraHosts),
                static fn(string $h): bool => trim($h) !== ''
            ));
        };

        return [
            'data' => $extractHosts($istio['data'] ?? null),
            'management-api' => $extractHosts($istio['management-api'] ?? null),
        ];
    }

    /**
     * Analyse le contenu d'un fichier values.yaml ou values-<env>.yaml pour extraire le probes.basePath.
     *
     * @param string|null $content Le contenu YAML du fichier de values.
     * @return string|null Le basePath normalisé (ex: "/v2"), ou null s'il n'est pas défini.
     */
    public function parseProbesBasePath(?string $content): ?string
    {
        if ($content === null || trim($content) === '') {
            return null;
        }

        try {
            $parsed = YamlParser::parse($content);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($parsed)) {
            return null;
        }

        $basePath = $parsed['mdm-workload']['config']['deployment']['container']['probes']['basePath'] ?? null;
        if (!is_string($basePath)) {
            return null;
        }

        $trimmed = trim(trim($basePath), '/');
        if ($trimmed === '') {
            return null;
        }

        return '/' . $trimmed;
    }
}
