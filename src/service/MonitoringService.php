<?php
declare(strict_types=1);

namespace App\service;

use App\exception\FunctionalException;
use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\model\EnumEnvironment;
use App\util\UtilsLog;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Monolog\Logger;

class MonitoringService
{
    private Logger $logger;

    public function __construct(
        private readonly GitlabService   $gitLabService,
        private readonly ClientInterface $client,
        LoggerFactory                    $loggerFactory
    )
    {
        $this->logger = $loggerFactory->get(__CLASS__);
    }

    /**
     * Vérifie si une application est en vie (healthcheck) et récupère l'url des logs.
     *
     * @param string $projectCode Code d'un projet
     * @param EnumEnvironment|null $env Environnement (dev, rec, pp ou prod)
     * @return array
     * @throws FunctionalException|TechnicalException
     */
    public function getMonitoringData(string $projectCode, ?EnumEnvironment $env): array
    {
        if ($env === null) {
            throw new FunctionalException("L'environnement n'est pas renseigné", 404, null);
        }
        $project = $this->gitLabService->getProjectByCode($projectCode);

        if ($project === null) {
            throw new FunctionalException("Projet '$projectCode' non trouvé.", 404, null);
        }

        $urlsActuatorInfo = $project->getUrlActuatorInfo();
        $urlActuatorInfo = $urlsActuatorInfo[$env->value] ?? '';

        $urlsHealth = $project->getUrlHealthCheck();
        $urlHealthCheck = $urlsHealth[$env->value] ?? '';

        $urlsLogs = $project->getUrlLogs();
        $urlLogs = $urlsLogs[$env->value] ?? '';

        $healthCheckResult = $this->callAndCheckHealth($urlHealthCheck);
        $actuatorInfoResult = null;
        if ($healthCheckResult['httpCode'] === 200) {
            $actuatorInfoResult = $this->callAndGetVersion($urlActuatorInfo);
        }

        return [
            'actuatorInfo' => $actuatorInfoResult,
            'health' => $healthCheckResult,
            'urls' => [
                'actuatorInfoUrl' => $urlActuatorInfo,
                'healthCheckUrl' => $urlHealthCheck,
                'logsUrl' => $urlLogs
            ]
        ];
    }

    /**
     * Appelle une url et retourne le résultat json
     * @param string $urlHealthCheck URL
     * @return array
     */
    private function callAndCheckHealth(string $urlHealthCheck): array
    {
        $status = 'DOWN';

        if (empty($urlHealthCheck)) {
            return ['status' => 'N/A', 'httpCode' => null, 'error' => 'URL non définie'];
        }

        $json = $this->call($urlHealthCheck);
        if ($json['body']) {
            $body = $json['body'];
            if (($body['status'] ?? null) === 'UP') {
                $status = 'UP';
            }
        }
        return [
            'status' => $status,
            'httpCode' => $json['httpCode'],
            'error' => $json['error'],
        ];
    }

    /**
     * Appelle une url et retourne le résultat json
     * @param string $urlActuatorInfo URL
     * @return array
     */
    private function callAndGetVersion(string $urlActuatorInfo): array
    {
        $version = 'N/A';

        if (empty($urlActuatorInfo)) {
            return ['version' => 'N/A', 'httpCode' => null, 'error' => 'URL non définie'];
        }

        $json = $this->call($urlActuatorInfo);
        if ($json['body']) {
            $body = $json['body'];
            if (($body['build'] && $body['build']['version'] ?? null) !== null) {
                $version = $body['build']['version'];
            }
        }
        return [
            'version' => $version,
            'httpCode' => $json['httpCode'],
            'error' => $json['error'],
        ];
    }

    /**
     * Appelle une url et retourne le résultat json
     * @param string $url URL
     * @return array
     */
    private function call(string $url): array
    {
        $httpCode = null;
        $error = null;
        $body = null;

        if (empty($url)) {
            return ['status' => 'N/A', 'httpCode' => null, 'error' => 'URL non définie'];
        }

        try {
            $this->logger->debug(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
                . 'url: ' . $url);

            $response = $this->client->request('GET', $url);
            $httpCode = $response->getStatusCode();

            if ($httpCode === 200 && $response->getBody()) {
                $body = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
            }
        } catch (JsonException $e) {
            $error = 'JSON invalide';
        } catch (GuzzleException $e) {
            $httpCode = $e->getCode();
            $error = $e->getMessage();
            $this->logger->error(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__) . 'Erreur lors de la requête: ' . $e->getMessage());
        }

        return [
            'body' => $body,
            'httpCode' => $httpCode,
            'error' => $error,
        ];
    }
}
