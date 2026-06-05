<?php
declare(strict_types=1);

namespace App\model;

use InvalidArgumentException;

class ParamNewRelic
{
    private string $apiUser;
    private string $apiKeyRec;
    private string $apiKeyProd;
    private int $accountIdDev = 0;
    private int $accountIdRec = 0;
    private int $accountIdPreprod = 0;
    private int $accountIdProd = 0;

    public function getApiUser(): string
    {
        return $this->apiUser;
    }

    public function setApiUser(string $apiUser): self
    {
        $this->apiUser = $apiUser;
        return $this;
    }

    public function getAccountIdDev(): int
    {
        return $this->accountIdDev;
    }

    public function setAccountIdDev(int $accountIdDev): self
    {
        $this->accountIdDev = $accountIdDev;
        return $this;
    }

    public function getApiKeyRec(): string
    {
        return $this->apiKeyRec;
    }

    public function setApiKeyRec(string $apiKeyRec): self
    {
        $this->apiKeyRec = $apiKeyRec;
        return $this;
    }

    public function getApiKeyProd(): string
    {
        return $this->apiKeyProd;
    }

    public function setApiKeyProd(string $apiKeyProd): self
    {
        $this->apiKeyProd = $apiKeyProd;
        return $this;
    }

    public function getAccountIdRec(): int
    {
        return $this->accountIdRec;
    }

    public function setAccountIdRec(int $accountIdRec): self
    {
        $this->accountIdRec = $accountIdRec;
        return $this;
    }

    public function getAccountIdPreprod(): int
    {
        return $this->accountIdPreprod;
    }

    public function setAccountIdPreprod(int $accountIdPreprod): self
    {
        $this->accountIdPreprod = $accountIdPreprod;
        return $this;
    }

    public function getAccountIdProd(): int
    {
        return $this->accountIdProd;
    }

    public function setAccountIdProd(int $accountIdProd): self
    {
        $this->accountIdProd = $accountIdProd;
        return $this;
    }

    public static function parse(array $params): self
    {
        if (!isset($params['newrelic-api-user'],
            //$params['newrelic-account-id-dev'],
            $params['newrelic-account-id-rec'],
            //$params['newrelic-account-id-pp'],
            $params['newrelic-account-id-prod'],
            $params['newrelic-api-key-rec'], $params['newrelic-api-key-prod'])) {
            throw new InvalidArgumentException("Certains paramètres newRelic requis sont manquants.");
        }

        $paramNewRelic = new self();
        $paramNewRelic->setApiUser($params['newrelic-api-user'] ?? '');
        $paramNewRelic->setApiKeyRec($params['newrelic-api-key-rec'] ?? '');
        $paramNewRelic->setApiKeyProd($params['newrelic-api-key-prod'] ?? '');
        $paramNewRelic->setAccountIdDev((int)($params['newrelic-account-id-dev'] ?? 0));
        $paramNewRelic->setAccountIdRec((int)($params['newrelic-account-id-rec'] ?? 0));
        $paramNewRelic->setAccountIdPreprod((int)($params['newrelic-account-id-pp'] ?? 0));
        $paramNewRelic->setAccountIdProd((int)($params['newrelic-account-id-prod'] ?? 0));

        return $paramNewRelic;
    }
}