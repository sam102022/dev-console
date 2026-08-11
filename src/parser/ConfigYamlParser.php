<?php
declare(strict_types=1);

namespace App\parser;

use Exception;

class ConfigYamlParser
{
    /**
     * Analyse le contenu d'un fichier deploy.yml pour extraire le nom du service.
     *
     * @param string|null $deployYamlContent Le contenu du fichier deploy.yml.
     * @return string|null Le nom du service extrait, ou null s'il n'a pas pu être trouvé.
     */
    public static function parseServiceName(?string $deployYamlContent): ?string
    {
        if ($deployYamlContent && preg_match('/metadata:\s*name:\s*([^\s]+)/s', $deployYamlContent, $matches)
            && $matches[1] !== '$CI_PROJECT_NAME') {
            return $matches[1];
        }
        return null;
    }

    /**
     * Analyse le contenu d'un fichier deploy.yml pour extraire le path du health
     *
     * @param string|null $deployYamlContent Le contenu du fichier deploy.yml.
     * @return string|null Le path du health extrait, ou null s'il n'a pas pu être trouvé.
     */
    public static function parsePathLivenessProbe(?string $deployYamlContent): ?string
    {
        if ($deployYamlContent && preg_match('/livenessProbe:(?:.|\n)*?path:\s*([^\s]+)/s', $deployYamlContent, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Analyse le contenu d'un fichier deploy.yml pour extraire le nom du service.
     * Provoque l'erreur :
     * Multiple documents are not supported at line 217 (near "---").
     *
     * @param string|null $yamlContent Le contenu d'un fichier deploy.yml.
     * @return string|null Le nom du service extrait, ou null s'il n'a pas pu être trouvé.
     */
    public static function parseServiceNameObject(?string $yamlContent): ?string
    {
        if (empty($yamlContent)) {
            return null;
        }

        $data = YamlParser::parse($yamlContent);

        return self::extractValue($data, [
            ['metadata', 'name'],   // format Kubernetes standard
            ['metadata.name'],      // format flat YAML
        ]);
    }

    /**
     * Analyse le contenu d'un fichier application.yml pour extraire le nom de la souscription Pub/Sub.
     *
     * @param string|null $yamlContent Le contenu du fichier YAML.
     * @return string|null Le nom de la souscription, ou null s'il n'a pas pu être trouvé.
     */
    public static function parseSubscriptionName(?string $yamlContent): ?string
    {
        if (empty($yamlContent)) {
            return null;
        }

        $data = YamlParser::parse($yamlContent);
        $subscriptionName = null;

        foreach ($data['mdm']['core']['subscriber']['subscription'] ?? [] as $subscriberConfig) {
            foreach ($subscriberConfig['name'] ?? [] as $name) {
                $subscriptionName = $name;
            }
        }

        return $subscriptionName;
    }

    /**
     * Analyse le contenu d'un fichier deploy.yaml pour extraire les hosts.
     *
     * @param string $content Le contenu du fichier deploy.yaml.
     * @return array|null
     */
    public static function parseHosts(string $content): ?array
    {
        if (empty($content)) {
            return null;
        }

        $data = YamlParser::parse($content);
        $hosts = [];

        $tlsBlocks = self::getValue($data, ['spec', 'tls']) ?? [];

        foreach ((array) $tlsBlocks as $tls) {
            foreach (($tls['hosts'] ?? []) as $host) {
                $hosts[] = $host;
            }
        }
        return $hosts;
    }

    /**
     * Extrait la valeur d'une variable spécifique dans un fichier values.yaml.
     *
     * @param string $yamlContent Le contenu du fichier YAML.
     * @param string $variableName Le nom de la variable à rechercher.
     * @return string|null La valeur de la variable, ou null si elle n'est pas trouvée.
     */
    public static function parseVariableInValuesFile(string $yamlContent, string $variableName): ?string
    {
        // On cherche le nom de la variable (qui est peut-être imbriquée) suivi de deux points
        // Ex: CLICK_AND_COLLECT_REPORTS_SUBSCRIPTION_NAME: "my-subscription-name"
        if (preg_match('/' . preg_quote($variableName, '/') . ':\s*"?([^"\s]+)"?/', $yamlContent, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    // ------------------------------------------------------------------
    // CORE HELPERS (clé du design propre)
    // ------------------------------------------------------------------

    /**
     * Extrait la première valeur trouvée selon plusieurs chemins possibles.
     */
    private static function extractValue(array $data, array $paths): ?string
    {
        foreach ($paths as $path) {

            $value = self::getValue($data, is_array($path) ? $path : explode('.', $path));

            if ($value === null) {
                continue;
            }

            if (is_array($value)) {

                // flatten récursif simple Kubernetes-friendly
                $flat = self::flatten($value);

                if (!empty($flat)) {
                    return end($flat);
                }

                continue;
            }

            return (string) $value;
        }

        return null;
    }

    /**
     * Navigation safe dans un array multi-niveaux.
     */
    private static function getValue(array $data, array $path): mixed
    {
        $current = $data;

        foreach ($path as $key) {

            if (!is_array($current)) {
                return null;
            }

            // CAS 1 : clé directe
            if (array_key_exists($key, $current)) {
                $current = $current[$key];
                continue;
            }

            // CAS 2 : tableau de blocs (Kubernetes style)
            if (array_is_list($current)) {
                $values = [];

                foreach ($current as $item) {
                    if (is_array($item) && array_key_exists($key, $item)) {
                        $values[] = $item[$key];
                    }
                }

                $current = $values;
                continue;
            }

            return null;
        }

        return $current;
    }

    private static function flatten(array $values): array
    {
        $result = [];

        array_walk_recursive($values, static function ($item) use (&$result) {
            if (is_scalar($item)) {
                $result[] = $item;
            }
        });

        return $result;
    }
}