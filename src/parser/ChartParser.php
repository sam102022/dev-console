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
}
