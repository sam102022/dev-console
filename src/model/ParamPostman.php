<?php
declare(strict_types=1);

namespace App\model;

use InvalidArgumentException;

class ParamPostman
{
    /** @var string L'API Key de Postman. */
    private string $postmanApiKey;

    /** @var string L'URL de l'API Postman. */
    private string $postmanApiUrl;

    public function getPostmanApiKey(): string
    {
        return $this->postmanApiKey;
    }

    public function setPostmanApiKey(string $postmanApiKey): void
    {
        $this->postmanApiKey = $postmanApiKey;
    }

    public function getPostmanApiUrl(): string
    {
        return $this->postmanApiUrl;
    }

    public function setPostmanApiUrl(string $postmanApiUrl): void
    {
        $this->postmanApiUrl = $postmanApiUrl;
    }

    public static function parse(array $params): self
    {
        if (!isset($params['postman_api_key'], $params['postman_api_url'])) {
            throw new InvalidArgumentException("Certains paramètres postman requis sont manquants.");
        }

        $paramPostman = new self();
        $paramPostman->setPostmanApiKey($params['postman_api_key'] ?? '');
        $paramPostman->setPostmanApiUrl($params['postman_api_url'] ?? '');

        return $paramPostman;
    }
}