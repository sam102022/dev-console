<?php
declare(strict_types=1);

namespace App\parser;

use Symfony\Component\Yaml\Yaml;

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

        $chart = $this->parse($content);
        $dependencies = $chart['dependencies'] ?? [];

        foreach ($dependencies as $dependency) {
            if (isset($dependency['name']) && $dependency['name'] === 'mdm-workload') {
                return $dependency['version'] ?? null;
            }
        }

        return null;
    }

    /**
     * Récupère le contenu d'un fichier .yaml
     *
     * @param string $content Le contenu d'un fichier yaml.
     * @return array|null
     */
    private function parse(string $content): ?array
    {
        if (empty($content)) {
            return null;
        }
        return Yaml::parse($content);
    }
}
