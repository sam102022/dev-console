<?php
declare(strict_types=1);

namespace App\parser;

class PackageJsonParser
{
    /**
     * Analyse le contenu d'un fichier package.json pour déterminer la technologie frontend (React ou Nuxt).
     *
     * @param string|null $content Le contenu du fichier package.json.
     * @return string|null La technologie ('react' ou 'nuxt'), ou null si elle n'est pas identifiée.
     */
    public static function parsePackage(?string $content): ?string
    {
        if (empty($content)) {
            return null;
        }

        $data = JsonParser::parse($content);
        if (!is_array($data)) {
            return null;
        }

        $dependencies = array_merge(
            $data['dependencies'] ?? [],
            $data['devDependencies'] ?? []
        );

        $scripts = $data['scripts'] ?? [];

        // Nuxt
        if (isset($dependencies['nuxt']) || self::hasNuxtScript($scripts) || self::hasNuxtScope($dependencies)) {
            return 'nuxt';
        }

        // React
        if (isset($dependencies['react']) || isset($dependencies['react-dom'])
            || (isset($scripts['start']) && str_starts_with($scripts['start'], 'node server.js'))
            || (isset($scripts['test:ci']) && str_starts_with($scripts['test:ci'], 'react-scripts'))
        ) {
            return 'react';
        }

        return null;
    }

    /**
     * Vérifie si le mot "nuxt" est présent dans les scripts NPM.
     *
     * @param array $scripts Liste des scripts du package.json.
     * @return bool True si un script contient 'nuxt', sinon false.
     */
    private static function hasNuxtScript(array $scripts): bool
    {
        return array_any($scripts, static fn($script) => str_contains($script, 'nuxt'));
    }

    /**
     * Vérifie si une dépendance du scope "@nuxt/" est présente.
     *
     * @param array $dependencies Liste des dépendances du package.json.
     * @return bool True si une dépendance commence par '@nuxt/', sinon false.
     */
    private static function hasNuxtScope(array $dependencies): bool
    {
        return array_any(array_keys($dependencies), static fn($packageName) => str_starts_with($packageName, '@nuxt/'));
    }
}
