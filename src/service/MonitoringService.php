<?php
declare(strict_types=1);

namespace App\service;

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
     * @throws FunctionalException
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
        
        $urlsHealth = $project->getUrlHealthCheck();
        $urlHealthCheck = $urlsHealth[$env->value] ?? '';

        $urlsLogs = $project->getUrlLogs();
        $urlLogs = $urlsLogs[$env->value] ?? '';

        $healthCheckResult = $this->callAndCheck($urlHealthCheck);
        
        return [
            'health' => $healthCheckResult,
            'urls' => [
                'healthCheckUrl' => $urlHealthCheck,
                'logsUrl' => $urlLogs
            ]
        ];
    }

    /**
     * Appelle l'url healthCheck et vérifie si l'application est en vie (UP).
     * @param string $urlHealthCheck URL healthCheck
     * @return array
     */
    private function callAndCheck(string $urlHealthCheck): array
    {
        $status = 'DOWN';
        $httpCode = null;
        $error = null;

        if (empty($urlHealthCheck)) {
            return ['status' => 'N/A', 'httpCode' => null, 'error' => 'URL non définie'];
        }

        try {
            $this->logger->debug(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__) . 'urlHealthCheck: ' . $urlHealthCheck);

            $response = $this->client->request('GET', $urlHealthCheck);
            $httpCode = $response->getStatusCode();

            if ($httpCode === 200 && $response->getBody()) {
                $json = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
                if (($json['status'] ?? null) === 'UP') {
                    $status = 'UP';
                }
            }
        } catch (JsonException $e) {
            $error = 'JSON invalide';
        } catch (GuzzleException $e) {
            $httpCode = $e->getCode();
            $error = $e->getMessage();
            $this->logger->error(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__) . 'Erreur lors de la requête: ' . $e->getMessage());
        }

        return [
            'status' => $status,
            'httpCode' => $httpCode,
            'error' => $error,
        ];
    }
}
