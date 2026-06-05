<?php
declare(strict_types=1);

namespace App\model;

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

}
