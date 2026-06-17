<?php
declare(strict_types=1);

namespace App\client;

use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\model\EnumEnvironment;
use App\model\ParamNewRelic;
use App\util\UtilsLog;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Monolog\Logger;

/**
 * Service pour interagir avec l'API GraphQL de New Relic.
 */
class NewRelicClient
{
    public const string API_URL = 'https://api.newrelic.com/graphql';

    private Logger $logger;

    public function __construct(
        private readonly Client        $client,
        private readonly ParamNewRelic $paramNewRelic,
        LoggerFactory                  $loggerFactory
    )
    {
        $this->logger = $loggerFactory->get(__CLASS__);
    }

    /**
     * @throws TechnicalException|JsonException
     */
    public function getEntityGuid(string $applicationName, EnumEnvironment $env): ?string
    {
        $query = <<<'GRAPHQL'
        query ($name: String!) {
          actor {
            entitySearch(query: $name) {
              results {
                entities {
                  guid
                  name
                  accountId
                }
              }
            }
          }
        }
        GRAPHQL;

        $apiKey = $this->getApiKey($env);
        $accountId = $this->getAccountId($env);

        $this->logger->debug(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
            . 'pour l\'application ' . $applicationName. ', l\'environnement ' . $env->value . ' et l\'id compte ' . $accountId);

        try {
            $response = $this->client->post(self::API_URL, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'API-Key' => $apiKey,
                ],
                'json' => [
                    'query' => $query,
                    'variables' => [
                        'name' => sprintf("name = '%s' AND accountId = %d", $applicationName, $accountId),
                    ],
                ],
            ]);

            $data = json_decode(
                (string)$response->getBody(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            return $data['data']['actor']['entitySearch']['results']['entities'][0]['guid']
                ?? null;
        } catch (GuzzleException $e) {
            $this->logger->error(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
                . 'Un erreur est survenue : ' . $e->getMessage());
            throw new TechnicalException($e->getMessage());
        }
    }

    /**
     * Génère l'URL de redirection vers l'entité New Relic.
     *
     * @param string $guid
     * @return string
     */
    public function generateEntityUrl(string $guid): string
    {
        return sprintf(
            'https://one.newrelic.com/redirect/entity/%s',
            urlencode($guid)
        );
    }

    /**
     * Récupère tous les projets d'un ARM
     *
     * @param EnumEnvironment $env
     * @return mixed|null
     * @throws JsonException
     * @throws TechnicalException
     */
    public function getAllProjects(EnumEnvironment $env)
    {
        $query = <<<'GRAPHQL'
        {
          actor {
            entitySearch(query: "domain = 'APM'") {
              results {
                entities {
                  guid
                  name
                  type
                }
              }
            }
          }
        }
        GRAPHQL;

        $accountId = $this->getAccountId($env);

        $this->logger->debug(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
            . 'pour l\'environnement ' . $env->value . ' et l\'id compte ' . $accountId);

        try {
            $response = $this->client->post(self::API_URL, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'API-Key' => $this->getApiKey($env),
                ],
                'json' => [
                    'query' => $query,
                    'variables' => [
                        'name' => sprintf("accountId = %d", $accountId),
                    ],
                ],
            ]);

            $data = json_decode(
                (string)$response->getBody(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            return $data ?? null;
        } catch (GuzzleException $e) {
            $this->logger->error(UtilsLog::prefixLog(__CLASS__, __METHOD__, __LINE__)
                . 'Un erreur est survenue : ' . $e->getMessage());
            throw new TechnicalException($e->getMessage());
        }
    }

    /**
     * Récupère la clé API New Relic en fonction de l'environnement.
     *
     * @param EnumEnvironment $env
     * @return string
     */
    private function getApiKey(EnumEnvironment $env): string
    {
        return $env->value === EnumEnvironment::PROD->value ?
            $this->paramNewRelic->getApiKeyProd() : $this->paramNewRelic->getApiKeyRec();
    }

    private function getAccountId(EnumEnvironment $env): ?int
    {
        return match ($env) {
            EnumEnvironment::DEV => $this->paramNewRelic->getAccountIdDev(),
            EnumEnvironment::REC => $this->paramNewRelic->getAccountIdRec(),
            EnumEnvironment::PP => $this->paramNewRelic->getAccountIdPreprod(),
            EnumEnvironment::PROD => $this->paramNewRelic->getAccountIdProd()
        };
    }
}