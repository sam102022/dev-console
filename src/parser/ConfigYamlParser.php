<?php
declare(strict_types=1);

namespace App\parser;

class ConfigYamlParser
{
    /**
     * Analyse le contenu d'un fichier deploy.yml pour extraire le nom du service.
     *
     * @param string|null $yamlContent Le contenu d'un fichier deploy.yml.
     * @return string|null Le nom du service extrait, ou null s'il n'a pas pu être trouvé.
     */
    public static function parseServiceName(?string $yamlContent): ?string
    {
        if (empty($yamlContent)) {
            return null;
        }

        $data = YamlParser::parse($yamlContent);
        $serviceName = null;

        // CAS 1 : metadata.name (structure classique)
        if (isset($data['metadata']['name'])) {
            if (is_array($data['metadata']['name'])) {
                $serviceName = end($data['metadata']['name']);
            } else {
                $serviceName = $data['metadata']['name'];
            }
        }

        // CAS 2 : metadata en liste
        foreach ($data['metadata'] ?? [] as $metadataConfig) {
            if (isset($metadataConfig['name'])) {
                foreach ((array) $metadataConfig['name'] as $name) {
                    $serviceName = $name;
                }
            }
        }

        // CAS 3 : clé plate "metadata.name"
        if (isset($data['metadata.name'])) {
            $serviceName = $data['metadata.name'];
        }

        return $serviceName;
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

        foreach ($data['spec']['tls'] ?? [] as $tlsConfig) {
            foreach ($tlsConfig['hosts'] ?? [] as $host) {
                $hosts[] = $host;
            }
        }
        return $hosts;
    }
}
