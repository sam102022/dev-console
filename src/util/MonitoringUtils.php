<?php
declare(strict_types=1);

namespace App\util;

use App\model\EnumActuatorEndpoint;
use App\model\EnumEnvironment;
use App\model\Project;
use App\model\RundeckProject;
use DateMalformedStringException;
use DateTime;
use DateTimeZone;

/**
 * Classe MonitoringUtils
 *
 * Fournit des méthodes utilitaires statiques pour la manipulation des fichiers et des répertoires.
 */
class MonitoringUtils
{
    private const string PATTERN_DOMAIN_CLOUD_GCP = '%s.mdm-int.net';
    private const string PATTERN_DOMAIN_RANCHER = 'app%s.xm';

    // URL Kibana de base (à adapter avec la vraie URL de votre entreprise)
    private const string KIBANA_URL_BASE = 'http://kibana.gestionlogs.app%s.xm';
    private const string ZEND_URL_PATTERN = 'https://intranet%s.siege.xm/portail/public/%s/index';

    /**
     * Analyse le contenu d'un fichier deploy.yml pour extraire les url hôtes.
     *
     * @param string|null $deployYamlContent Le contenu du fichier deploy.yml.
     * @return array|null Le nom des hôtes extraites, ou null s'il n'a pas pu être trouvé.
     */
    public static function parseHosts(?string $deployYamlContent): ?array
    {
        if ($deployYamlContent && preg_match('/tls:\s*\r\n\s*- hosts:\s*\r\n(\s*- .+\r\n?)+/s', $deployYamlContent, $matches)) {
            $hostsBlock = $matches[1];
            preg_match_all('/-\s*(.+)/', $hostsBlock, $hostMatches);
            return $hostMatches[1] ?? null;
        }
        return null;
    }

    /**
     * Analyse le contenu d'un fichier application.yml pour extraire le nom de la souscription Pub/Sub.
     *
     * @param string|null $yamlContent Le contenu du fichier YAML.
     * @return string|null Le nom de la souscription, ou null s'il n'a pas pu être trouvé.
     */
    public static function parseSubscriptionName(?string $yamlContent): ?string
    {
        if (!$yamlContent) {
            return null;
        }

        if (preg_match('/subscription\.name:\s*([^\s]+)/', $yamlContent, $matches)) {
            return trim($matches[1]);
        }

        return null;
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

    /**
     * Analyse le contenu d'un fichier package.json pour déterminer la technologie frontend (React ou Nuxt).
     *
     * @param string|null $packageContent Le contenu du fichier package.json.
     * @return string|null La technologie ('react' ou 'nuxt'), ou null si elle n'est pas identifiée.
     */
    public static function parsePackage(?string $packageContent): ?string
    {
        if (!$packageContent) {
            return null;
        }

        $data = json_decode($packageContent, true);

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

    /**
     * Construit l'URL du point de terminaison Health Check d'un projet pour un environnement donné.
     *
     * @param Project $project L'objet projet contenant les informations du service.
     * @param EnumEnvironment|null $env L'environnement ciblé.
     * @param array $projectsInGke Liste des projets déployés sur GKE.
     * @return string L'URL construite du Health Check.
     */
    public static function buildUrlActuatorHealth(Project $project, ?EnumEnvironment $env, array $projectsInGke): string
    {
        return self::buildUrlActuator($project, $env, $projectsInGke, EnumActuatorEndpoint::HEALTH);
    }

    /**
     * Construit l'URL du point de terminaison Health Check d'un projet pour un environnement donné.
     *
     * @param Project $project L'objet projet contenant les informations du service.
     * @param EnumEnvironment|null $env L'environnement ciblé.
     * @param array $projectsInGke Liste des projets déployés sur GKE.
     * @return string L'URL construite du Health Check.
     */
    public static function buildUrlActuatorInfo(Project $project, ?EnumEnvironment $env, array $projectsInGke): string
    {
        return self::buildUrlActuator($project, $env, $projectsInGke, EnumActuatorEndpoint::INFO);
    }

    /**
     * Construit l'URL du point de terminaison Health Check d'un projet pour un environnement donné.
     *
     * @param Project $project L'objet projet contenant les informations du service.
     * @param EnumEnvironment|null $env L'environnement ciblé.
     * @param array $projectsInGke Liste des projets déployés sur GKE.
     * @param EnumActuatorEndpoint $actuatorEndpoint Actuator endpoint
     * @return string L'URL construite du Health Check.
     */
    public static function buildUrlActuator(Project $project, ?EnumEnvironment $env, array $projectsInGke, EnumActuatorEndpoint $actuatorEndpoint): string
    {
        $projectName = $project->getname();
        $cloudGCP = $project->isCloudGCP();

        // Construction de l'url health check
        $envLocal = $env->value;
        if ($cloudGCP) {
            $domain = self::PATTERN_DOMAIN_CLOUD_GCP;
        } else {
            $domain = self::PATTERN_DOMAIN_RANCHER;
            if ($env->value === EnumEnvironment::PROD->value && in_array($projectName, $projectsInGke, true)) {
                $domain = self::PATTERN_DOMAIN_CLOUD_GCP;
            } else {
                $domain = $project->getSf() . "." . $domain;
                $envLocal = $env->value === EnumEnvironment::PROD->value ? '' : '-' . $env->value;
            }
        }

        $uriActuator = '';
        // TODO Pas terrible, trouver une autre solution plus propre pour l'api store stock
        if ($projectName !== 'api-store-stock' && str_starts_with($projectName, 'api')) {
            $uriActuator .= '/v1';
        }
        $uriActuator .= '/actuator/' . $actuatorEndpoint->value;

        $urlHealthCheck = "https://management-$projectName.$domain$uriActuator";

        return sprintf($urlHealthCheck, $envLocal);
    }

    /**
     * Construit l'URL pour accéder aux logs du projet (vers GCP ou Kibana selon le déploiement).
     *
     * @param Project $project L'objet projet contenant les informations.
     * @param EnumEnvironment|null $env L'environnement ciblé.
     * @return string L'URL permettant d'accéder aux logs.
     * @throws DateMalformedStringException
     */
    public static function buildLogUrl(Project $project, ?EnumEnvironment $env): string
    {
        if ($project->isCloudGCP()) {
            return self::buildGCPLogUrl($project, $env);
        }
        return self::buildKibanaLogUrl($project, $env);
    }

    /**
     * Construit l'URL Kibana pour consulter les logs d'un projet déployé sur Rancher/On-Premise.
     *
     * @param Project $project L'objet projet.
     * @param EnumEnvironment|null $env L'environnement ciblé.
     * @return string L'URL Kibana pointant vers les logs du projet.
     */
    public static function buildKibanaLogUrl(Project $project, ?EnumEnvironment $env): string
    {
        $projectName = $project->getServiceName() ?? $project->getname();
        $subSf = $project->getSf();

        $envName = $env?->value ?? '%s';

        $key1 = 'kubernetes.namespace.keyword';
        $key2 = 'kubernetes.container.name.keyword';
        $value1 = $subSf . '-' . $envName;
        $value2 = $projectName;

        if ($env) {
            if ($envName === EnumEnvironment::PROD->value) {
                $url = sprintf(self::KIBANA_URL_BASE, '') . "/app/kibana#/dashboard/";
            } else {
                $url = sprintf(self::KIBANA_URL_BASE, '-dev') . "/app/dashboards#/view/";
            }
        } else {
            $url = self::KIBANA_URL_BASE . "%s";
        }

        $url .= "410c2c80-8dd8-11e9-bab3-47b86eb95c19?_g=(filters:!(),refreshInterval:(pause:!t,value:0),time:(from:now-30m,to:now))";
        $url .= "&_a=(description:'',filters:!(('\$state':(store:appState),meta:(alias:!n,controlledBy:'1560429562986',disabled:!f,index:'703f0680-e486-11e9-915a-d563e49bee67',key:$key1,negate:!f,params:(query:$value1),type:phrase),query:(match_phrase:($key1:$value1))),('\$state':(store:appState),meta:(alias:!n,controlledBy:'1560429093025',disabled:!f,index:'703f0680-e486-11e9-915a-d563e49bee67',key:$key2,negate:!f,params:(query:$value2),type:phrase),query:(match_phrase:($key2:$value2)))),fullScreenMode:!f,options:(hidePanelTitles:!f,useMargins:!t),query:(language:kuery,query:''),tags:!(),timeRestore:!f,title:'Logs%20MdM%20(Kubernetes)',viewMode:view)";
        return $url;
    }

    /**
     * Construit l'URL Google Cloud Console pour consulter les logs d'un projet déployé sur GCP.
     *
     * @param Project $project L'objet projet.
     * @param EnumEnvironment|null $env L'environnement ciblé.
     * @return string L'URL Google Cloud Logs Explorer pointant vers les logs du projet.
     * @throws DateMalformedStringException En cas d'erreur lors de la manipulation de la date.
     */
    public static function buildGCPLogUrl(Project $project, ?EnumEnvironment $env): string
    {
        $projectName = $project->getname();
        $subSf = $project->getSf();

        $envName = $env?->value ?? '%s';

        $projectId = 'mdm-observability-' . $envName;

        $value1 = urlencode('"' . $subSf . '"');
        $value2 = urlencode('"' . $projectName . '"');
        $value3 = '';
        $prefix = explode('-', $projectName)[0]; // Récupère 'api', 'flow', ou 'batch'
        if (in_array($prefix, ['api', 'flow', 'batch'], true)) {
            $value3 = urlencode(sprintf('"app-java-%s"', $prefix));
        }
        $date = new DateTime('now', new DateTimeZone('UTC'));

        // Ex :
//        resource.type="k8s_container"
//        resource.labels.location="europe-west1"
//        resource.labels.cluster_name="kube-prod"
//        resource.labels.container_name !~ "^istio.*" // resource.labels.container_name%20!~%20%22%5Eistio.*%22%0D
        //https://console.cloud.google.com/logs/query;query=resource.type%3D%22k8s_container%22%0D%0Aresource.labels.location%3D%22europe-west1%22%0D%0Aresource.labels.cluster_name%3D%22kube-prod%22%0D%0Aresource.labels.container_name%20!~%20%22%5Eistio.*%22%0D%0Aresource.labels.namespace_name%3D%22stores%22%0D%0Alabels.k8s-pod%2Fapp_kubernetes_io%2Finstance%3D%22api-store-operator%22%20%0D%0Aseverity%3E%3DDEFAULT;storageScope=storage,projects%2Fmdm-observability-prod%2Flocations%2Feu%2Fbuckets%2Fmdm-observability-prod.common_logs%2Fviews%2F_AllLogs;cursorTimestamp=2026-06-03T07:18:37.655023114Z;duration=P1D?project=mdm-observability-prod

        $data = [
            'resource.labels.namespace_name' => $value1,
            urlencode('labels.k8s-pod/app_kubernetes_io/instance') => $value2,
            'resource.labels.container_name' => $value3,
        ];

        $items = array_map(
            static fn($key, $value) => $key . '%3D' . $value, // %3D => "="
            array_keys($data),
            $data
        );
        $query = implode('%0A', $items);
        // Ex : resource.labels.namespace_name%3D%22stores%22%0Alabels.k8s-pod%2Fapp_kubernetes_io%2Finstance:%22api-store-reception%22%0Aresource.labels.container_name%3D%22app-java-api%22

        $currentTimestamp = $date->format('Y-m-d\TH:i:s.v\Z');

        //https://console.cloud.google.com/logs/query;query=resource.labels.namespace_name%3D%22stores%22%0Alabels.k8s-pod%2Fapp_kubernetes_io%2Finstance:%22api-store-reception%22%0Aresource.labels.container_name%3D%22app-java-api%22;storageScope=storage,projects%2Fmdm-observability-rec%2Flocations%2Feu%2Fbuckets%2Fmdm-observability-rec.common_logs%2Fviews%2F_AllLogs,projects%2Fmdm-observability-rec%2Flocations%2Feu%2Fbuckets%2Fmdm-observability-rec.fin_logs%2Fviews%2F_AllLogs,projects%2Fmdm-observability-rec%2Flocations%2Feu%2Fbuckets%2Fmdm-observability-rec.infra_logs%2Fviews%2F_AllLogs,projects%2Fmdm-observability-rec%2Flocations%2Fglobal%2Fbuckets%2F_Default%2Fviews%2F_AllLogs,projects%2Fmdm-observability-rec%2Flocations%2Fglobal%2Fbuckets%2F_Default%2Fviews%2F_Default,projects%2Fmdm-observability-rec%2Flocations%2Fglobal%2Fbuckets%2F_Required%2Fviews%2F_AllLogs;cursorTimestamp=2026-05-28T09:48:02.657Z;histogramBreakdownField=severity;duration=P14D?invt=AbtxPQ&project=mdm-observability-rec
        return "https://console.cloud.google.com/logs/query;query=$query;storageScope=storage,projects%2F$projectId%2Flocations%2Feu%2Fbuckets%2F$projectId.common_logs%2Fviews%2F_AllLogs,projects%2F$projectId%2Flocations%2Feu%2Fbuckets%2F$projectId.infra_logs%2Fviews%2F_AllLogs,projects%2F$projectId%2Flocations%2Fglobal%2Fbuckets%2F_Default%2Fviews%2F_AllLogs,projects%2F$projectId%2Flocations%2Fglobal%2Fbuckets%2F_Default%2Fviews%2F_Default,projects%2F$projectId%2Flocations%2Fglobal%2Fbuckets%2F_Required%2Fviews%2F_AllLogs;cursorTimestamp=$currentTimestamp;histogramBreakdownField=severity;duration=P14D?invt=AbtxOw&project=$projectId";
    }

    /**
     * Construit l'URL du frontend React d'un projet pour un environnement donné.
     *
     * @param Project $project L'objet projet frontend.
     * @param EnumEnvironment|null $env L'environnement ciblé.
     * @param string $tokenE107 Le token d'authentification pour accéder à l'application.
     * @return string L'URL publique de l'application frontend.
     */
    public static function buildFrontReactUrl(Project $project, ?EnumEnvironment $env, string $tokenE107): string
    {
        $projectName = $project->getname();

        // Ex : https://front-store-employee.stores.app-dev.xm/?lk=<token>
        // https://front-gestion-magasin.dev.mdm-int.net/
        //https://front-gestion-magasin.prod.mdm-int.net/
        // Exception front-store-reception-gap -> https://front-store-reception-arbitration.stores.app-dev.xm/?lk=cy1kbmFpcmJ8Y3kxa2JtRnBjbUk9fGJXOWpMbVZrYm05dGRXUnpibTl6YVdGdFFHUnVZV2x5WW5NPQ
        if ($projectName === 'front-store-reception-gap') {
            $projectName = 'front-store-reception-arbitration';
        }
        $url = 'https://' . $projectName;
        if (!$project->isCloudGCP()) {
            $envPart = ($env->value !== EnumEnvironment::PROD->value) ? '-' . $env->value : '';
            $url .= sprintf('.%s.app%s.xm', $project->getSf(), $envPart);
        } else {
            $url .= '.' . $env->value . '.mdm-int.net';
        }
        $url .= '/?lk=' . $tokenE107;

        if ($projectName === 'front-store-till-contact') {
            $url .= '&idMag=124&CodeLng=fr';
        } elseif ($projectName === 'front-dossier-client') {
            $url .= '&nobl=75226562';
        }
        return $url;
    }

    /**
     * Construit l'URL de la file d'attente Google Cloud Pub/Sub d'un projet.
     *
     * @param Project $project L'objet projet contenant la configuration Pub/Sub.
     * @param EnumEnvironment|null $env L'environnement ciblé.
     * @return string L'URL Google Cloud Console pour inspecter les messages du topic Pub/Sub.
     */
    public static function buildPubSubUrl(Project $project, ?EnumEnvironment $env): string
    {
        //https://console.cloud.google.com/cloudpubsub/topic/detail/flow-store-received-delivery-note_store-received-delivery-note-events_ops?inv=1&invt=Ab5XmQ&project=dev-mdm-buyers&tab=messages
        $url = 'https://console.cloud.google.com/cloudpubsub/topic/detail/';
        $url .= $project->getSubscriptionName() . '_ops';
        $url .= '?project=';
        if ($env !== EnumEnvironment::PROD) {
            $url .= $env->value . '-';
        }
        $url .= 'mdm-' . $project->getSf() . '&inv=1&invt=Ab5XmQ&tab=messages';

        return $url;
    }

    /**
     * Construit l'URL d'un frontend PHP classique (hors React/Nuxt).
     *
     * @param Project $project L'objet projet PHP.
     * @param EnumEnvironment $env L'environnement ciblé.
     * @return string L'URL du frontend PHP, ou une chaîne vide si non applicable (DEV, PP).
     */
    public static function buildFrontPhpUrl(Project $project, EnumEnvironment $env): string
    {
        if ($env->value !== EnumEnvironment::DEV->value && $env->value !== EnumEnvironment::PP->value) {
            $envPart = ($env->value === EnumEnvironment::REC->value) ? '-rec' : '';
            return sprintf(self::ZEND_URL_PATTERN, $envPart, $project->getName());
        }
        return '';
    }

    /**
     * Construit l'URL du serveur rundeck d'un sf.
     *
     * @param Project $project L'objet projet PHP.
     * @param EnumEnvironment $env L'environnement ciblé.
     * @param RundeckProject|null $rundeckProject L'objet rundeck projet.
     * @return string L'URL du rundeck.
     */
    public static function buildRundeckUrl(Project $project, EnumEnvironment $env, ?RundeckProject $rundeckProject): string
    {
        if ($rundeckProject !== null) {
            return self::buildRundeckUrlFromRundeckProject($rundeckProject, $env);
        }
        $pattern = 'https://rundeck-%s.siege.xm/project/%s/jobs';
        return sprintf($pattern, $env->value, $project->getSf());
    }

    /**
     * Construit l'URL du serveur rundeck d'un sf.
     *
     * @param RundeckProject $rundeckProject L'objet rundeck projet.
     * @param EnumEnvironment $env L'environnement ciblé.
     * @return string L'URL du rundeck.
     */
    public static function buildRundeckUrlFromRundeckProject(RundeckProject $rundeckProject, EnumEnvironment $env): string
    {
        $pattern = 'https://rundeck-%s.siege.xm/project/%s/job/show/%s';
        $token = $rundeckProject->getToken();
        $tokenVal = '';
        if (is_array($token)) {
            $tokenObj = $token[0] ?? [];
            $tokenVal = $tokenObj[$env->value] ?? '';
        }
        return sprintf($pattern, $env->value, $rundeckProject->getSf(), $tokenVal);
    }

    /**
     * Construit l'URL de deploiement GCP d'un projet.
     *
     * @param Project $project L'objet projet PHP.
     * @param EnumEnvironment $env L'environnement ciblé.
     * @return string L'URL de deploiement GCP.
     */
    public static function buildDeploymentGcpUrl(Project $project, EnumEnvironment $env): string
    {
        // Ex : https://console.cloud.google.com/kubernetes/deployment/europe-west1/kube-dev/stores-stock/flow-stores-stock-composition/overview?hl=fr&project=mdm-core-infra-dev&supportedpurview=project
        $urlBase = 'https://console.cloud.google.com/kubernetes/deployment/europe-west1/kube-%s/%s/%s/overview';
        $query = '?hl=fr&project=mdm-core-infra-%s&supportedpurview=project';
        $pattern = $urlBase . $query;

        return sprintf($pattern, $env->value, $project->getSf(), $project->getName(), $env->value);
    }
}