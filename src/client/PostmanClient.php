<?php
declare(strict_types=1);

namespace App\client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;

class PostmanClient
{
    public function __construct(private readonly Client $client)
    {
    }

    public function get(string $endpoint): array
    {
        return $this->request('GET', $endpoint);
    }

    public function post(string $endpoint, array $payload = []): array
    {
        return $this->request('POST', $endpoint, $payload);
    }

    private function request(string $method, string $endpoint, ?array $payload = null): array
    {
        $options = [];
        if ($payload && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $options['json'] = $payload;
        }

        try {
            $response = $this->client->request($method, $endpoint, $options);
            $result = $response->getBody()->getContents();
            return json_decode($result, true) ?? [];
        } catch (RequestException $e) {
            $httpCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
            throw new RuntimeException(
                "Erreur API Postman ($httpCode): " . $e->getMessage(),
                $httpCode,
                $e
            );
        } catch (GuzzleException $e) {
            throw new RuntimeException('Erreur Guzzle : ' . $e->getMessage(), 0, $e);
        }
    }
}
