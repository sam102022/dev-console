<?php
declare(strict_types=1);

namespace App\util;

use App\model\EnumEnvironment;

/**
 * Classe UtilsFile
 *
 * Fournit des méthodes utilitaires statiques pour la manipulation des fichiers et des répertoires.
 */
class MonitoringUtils
{
    private const string PATTERN_DOMAIN_CLOUD_GCP = '%s.mdm-int.net';
    private const string PATTERN_DOMAIN_RANCHER = 'app%s.xm';

    public static function buildUrlHealthCheck(array $project, EnumEnvironment $env, array $projectsInGke): string
    {
        $cloudGCP = $project['cloudGCP'];
        $projectName = $project['name'];

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
}
