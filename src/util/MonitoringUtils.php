<?php
declare(strict_types=1);

namespace App\util;

use App\model\EnumEnvironment;
use App\model\Project;
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

    public static function parseServiceName(?string $deployYamlContent): ?string
    {
        if ($deployYamlContent && preg_match('/metadata:\s*name:\s*([^\s]+)/s', $deployYamlContent, $matches)
            && $matches[1] !== '$CI_PROJECT_NAME') {
            return $matches[1];
        }
        return null;
    }

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

    public static function parseVariableInValuesFile(string $yamlContent, string $variableName): ?string
    {
        // On cherche le nom de la variable (qui est peut-être imbriquée) suivi de deux points
        // Ex: CLICK_AND_COLLECT_REPORTS_SUBSCRIPTION_NAME: "my-subscription-name"
        if (preg_match('/' . preg_quote($variableName, '/') . ':\s*"?([^"\s]+)"?/', $yamlContent, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

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

    private static function hasNuxtScript(array $scripts): bool
    {
        foreach ($scripts as $script) {
            if (str_contains($script, 'nuxt')) {
                return true;
            }
        }
        return false;
    }

    private static function hasNuxtScope(array $dependencies): bool
    {
        foreach (array_keys($dependencies) as $packageName) {
            if (str_starts_with($packageName, '@nuxt/')) {
                return true;
            }
        }
        return false;
    }

    public static function buildUrlHealthCheck(Project $project, ?EnumEnvironment $env, array $projectsInGke): string
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
                $envLocal = $env->value === EnumEnvironment::PROD->value ? '' : '-' . $env->value;
            }
        }

        $uriHealth = '';
        if (str_starts_with($projectName, 'api')) {
            $uriHealth .= '/v1';
        }
        $uriHealth .= '/actuator/health';

        $urlHealthCheck = "https://management-$projectName.$domain$uriHealth";

        return sprintf($urlHealthCheck, $envLocal);
    }

    /**
     * @throws DateMalformedStringException
     */
    public static function buildLogUrl(Project $project, ?EnumEnvironment $env): string
    {
        if ($project->isCloudGCP()) {
            return self::buildGCPLogUrl($project, $env);
        }
        return self::buildKibanaLogUrl($project, $env);
    }

    public static function buildKibanaLogUrl(Project $project, ?EnumEnvironment $env): string
    {
        $projectName = $project->getServiceName() ?? $project->getname();
        $subSf = $project->getSubsf();

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
     * @throws DateMalformedStringException
     */
    public static function buildGCPLogUrl(Project $project, ?EnumEnvironment $env): string
    {
        $projectName = $project->getname();
        $subSf = $project->getSubsf();

        $envName = $env?->value ?? '%s';

        $projectId = 'mdm-observability-' . $envName;

        $key1 = 'resource.labels.namespace_name';
        $key2 = urlencode('labels.k8s-pod/app_kubernetes_io/instance');
        $key3 = 'resource.labels.container_name';
        $value1 = urlencode('"' . $subSf . '"');
        $value2 = urlencode('"' . $projectName . '"');

        $value3 = '';
        $prefix = explode('-', $projectName)[0]; // Récupère 'api', 'flow', ou 'batch'
        if ($prefix === 'api') {
            $value3 = urlencode(sprintf('"app-java-%s"', $prefix));
        }
        if (in_array($prefix, ['batch', 'flow'], true)) {
            $value3 = '"java"';
        }
        $date = new DateTime('now', new DateTimeZone('UTC'));

        $query = "$key1%3D$value1%0A$key2:$value2%0A$key3%3D$value3";
        // Ex : resource.labels.namespace_name%3D%22stores%22%0Alabels.k8s-pod%2Fapp_kubernetes_io%2Finstance:%22api-store-reception%22%0Aresource.labels.container_name%3D%22app-java-api%22

        $currentTimestamp = $date->format('Y-m-d\TH:i:s.v\Z');

        $url = "https://console.cloud.google.com/logs/query;query=$query;storageScope=storage,projects%2F$projectId%2Flocations%2Feu%2Fbuckets%2F$projectId.common_logs%2Fviews%2F_AllLogs,projects%2F$projectId%2Flocations%2Feu%2Fbuckets%2F$projectId.infra_logs%2Fviews%2F_AllLogs,projects%2F$projectId%2Flocations%2Fglobal%2Fbuckets%2F_Default%2Fviews%2F_AllLogs,projects%2F$projectId%2Flocations%2Fglobal%2Fbuckets%2F_Default%2Fviews%2F_Default,projects%2F$projectId%2Flocations%2Fglobal%2Fbuckets%2F_Required%2Fviews%2F_AllLogs;cursorTimestamp=$currentTimestamp;histogramBreakdownField=severity;duration=P14D?invt=AbtxOw&project=$projectId";
        //https://console.cloud.google.com/logs/query;query=resource.labels.namespace_name%3D%22stores%22%0Alabels.k8s-pod%2Fapp_kubernetes_io%2Finstance:%22api-store-reception%22%0Aresource.labels.container_name%3D%22app-java-api%22;storageScope=storage,projects%2Fmdm-observability-rec%2Flocations%2Feu%2Fbuckets%2Fmdm-observability-rec.common_logs%2Fviews%2F_AllLogs,projects%2Fmdm-observability-rec%2Flocations%2Feu%2Fbuckets%2Fmdm-observability-rec.fin_logs%2Fviews%2F_AllLogs,projects%2Fmdm-observability-rec%2Flocations%2Feu%2Fbuckets%2Fmdm-observability-rec.infra_logs%2Fviews%2F_AllLogs,projects%2Fmdm-observability-rec%2Flocations%2Fglobal%2Fbuckets%2F_Default%2Fviews%2F_AllLogs,projects%2Fmdm-observability-rec%2Flocations%2Fglobal%2Fbuckets%2F_Default%2Fviews%2F_Default,projects%2Fmdm-observability-rec%2Flocations%2Fglobal%2Fbuckets%2F_Required%2Fviews%2F_AllLogs;cursorTimestamp=2026-05-28T09:48:02.657Z;histogramBreakdownField=severity;duration=P14D?invt=AbtxPQ&project=mdm-observability-rec

        return $url;
    }

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
            $url .= sprintf('.%s.app%s.xm', $project->getSubsf(), $envPart);
        } else {
            $url .= '.' . $env->value . '.mdm-int.net';
        }
        $url .= '/?lk=' . $tokenE107;

        if ($projectName === 'front-store-till-contact') {
            $url .= '&idMag=124&CodeLng=fr';
        }
        elseif($projectName === 'front-dossier-client'){
            $url .= '&nobl=75226562';
        }
        return $url;
    }

    public static function buildPubSubUrl(Project $project, ?EnumEnvironment $env)
    {
        //https://console.cloud.google.com/cloudpubsub/topic/detail/flow-store-received-delivery-note_store-received-delivery-note-events_ops?inv=1&invt=Ab5XmQ&project=dev-mdm-buyers&tab=messages
        $url = 'https://console.cloud.google.com/cloudpubsub/topic/detail/';
        $url .= $project->getSubscriptionName() . '_ops';
        $url .= '?project=' . $env->value . '-mdm-' . $project->getSubsf() . '&inv=1&invt=Ab5XmQ&tab=messages';

        return $url;
    }
}