<?php
declare(strict_types=1);

namespace App\client;

use RuntimeException;

class PostmanClientCopy
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct(string $baseUrl, string $apiKey)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
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
        $ch = curl_init($this->baseUrl . $endpoint);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'X-Api-Key: ' . $this->apiKey,
                'Content-Type: application/json'
            ]
        ]);

        if ($payload && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $result = curl_exec($ch);

        if ($result === false) {
            throw new RuntimeException('Erreur cURL : ' . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        //curl_close($ch);

        $decoded = json_decode($result, true);

        if ($httpCode >= 400) {
            throw new RuntimeException(
                "Erreur API Postman ($httpCode)",
                $httpCode
            );
        }

        return $decoded ?? [];
    }
}
