<?php
declare(strict_types=1);

namespace App\parser;

use Symfony\Component\Yaml\Yaml;

class ChartParser
{
    public function parse(string $content): ?string
    {
        if (empty($content)) {
            return null;
        }

        $chart = Yaml::parse($content);
        $dependencies = $chart['dependencies'] ?? [];

        foreach ($dependencies as $dependency) {
            if (isset($dependency['name']) && $dependency['name'] === 'mdm-workload') {
                return $dependency['version'] ?? null;
            }
        }

        return null;
    }
}
