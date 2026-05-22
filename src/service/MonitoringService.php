<?php
declare(strict_types=1);

namespace App\service;

use App\config\AppConfig;
use App\exception\FunctionalException;
use App\factory\LoggerFactory;
use App\model\EnumEnvironment;
use App\util\UtilsLog;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Monolog\Logger;

class MonitoringService
{
    private const string PATTERN_DOMAIN_CLOUD_GCP = '%s.mdm-int.net';
    private const string PATTERN_DOMAIN_RANCHER = 'app%s.xm';

    private Logger $logger;

    public function __construct(
        private readonly GitlabService   $gitLabService,
        private readonly ClientInterface $client,
        private readonly AppConfig       $appConfig,
        LoggerFactory                    $loggerFactory
    )
    {
        $this->logger = $loggerFactory->get(__CLASS__);
    }

    /**
     * Vérifie si une application est en vie (healthcheck)
     *
     * @param string $projectCode Code d'un projet
     * @param EnumEnvironment|null $env Environnement (dev, rec, pp ou prod)
     * @return array
     * @throws FunctionalException|GuzzleException
     */
    public function checkOne(string $projectCode, ?EnumEnvironment $env): array
    {
        if ($env === null) {
            throw new FunctionalException("L'environnement n'est pas renseigné", 404, null);
        }
        $project = $this->gitLabService->getProjectByCode($projectCode);

        if ($project !== null) {
            $projectsInGke = $this->appConfig->getParamConfig()->getProjectsInGke();
            $urlHealthCheck = $this->buildUrlHealthCheck($project, $env, $projectsInGke);

            return $this->callAndCheck($urlHealthCheck);
        }
        return [];
    }

    private function buildUrlHealthCheck(array $project, EnumEnvironment $env, array $projectsInGke): string
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
        // Si le projet est déployé automatiquement sur GKE
        $this->logger->debug(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
            . '$cloudGCP: ' . $cloudGCP . '$env->value: ' . $env->value . '$env: ' . $envLocal . ', in_array:' . in_array($projectName, $projectsInGke, true));


        $uriHealth = '';
        if (str_starts_with($projectName, 'api')) {
            $uriHealth .= '/v1';
        }
        $uriHealth .= '/actuator/health';

        $urlHealthCheck = "https://management-$projectName.$domain$uriHealth";

        return sprintf($urlHealthCheck, $envLocal);
    }

    /**
     * Appelle l'url healthCheck et vérifie si l'application est en vie (UP)
     * @param string $urlHealthCheck URL healthCheck
     * @return array
     */
    private function callAndCheck(string $urlHealthCheck): array
    {
        $status = 'DOWN';
        try {
            $this->logger->debug(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
                . 'urlHealthCheck: ' . $urlHealthCheck);

            $response = $this->client->request('GET', $urlHealthCheck);
            try {
                $httpCode = $response->getStatusCode();
                if ($httpCode === 200 && $response->getBody()) {
                    $json = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

                    if (($json['status'] ?? null) === 'UP') {
                        $status = 'UP';
                    }
                }
            } catch (JsonException $e) {
                $error = 'ERROR JSON';
            }

        } catch (GuzzleException $e) {
            $httpCode = 500;
            $error = $e->getMessage();
            $this->logger->error(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
                . 'Erreur lors de la requête:' . $e->getMessage());
        }
        return [
            'status' => $status,
            'httpCode' => $httpCode,
            'error' => $error ?? null
        ];
    }
}