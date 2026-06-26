<?php
declare(strict_types=1);

namespace App\repository\model;

class RundeckProjectEntity
{
    private string $name;
    private string $domain = '';
    private string $sf = '';
    private ?string $category = null;
    private array $token = [];
    private ?string $path = null;
    private ?string $projectName = null;

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return RundeckProjectEntity
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * @param string $domain
     * @return RundeckProjectEntity
     */
    public function setDomain(string $domain): self
    {
        $this->domain = $domain;
        return $this;
    }

    /**
     * @return string
     */
    public function getSf(): string
    {
        return $this->sf;
    }

    /**
     * @param string $sf
     * @return RundeckProjectEntity
     */
    public function setSf(string $sf): self
    {
        $this->sf = $sf;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getCategory(): ?string
    {
        return $this->category;
    }

    /**
     * @param string|null $category
     * @return RundeckProjectEntity
     */
    public function setCategory(?string $category): self
    {
        $this->category = $category;
        return $this;
    }

    /**
     * @return array
     */
    public function getToken(): array
    {
        return $this->token;
    }

    /**
     * @param array $token
     * @return RundeckProjectEntity
     */
    public function setToken(array $token): self
    {
        $this->token = $token;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * @param string|null $path
     * @return RundeckProjectEntity
     */
    public function setPath(?string $path): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getProjectName(): ?string
    {
        return $this->projectName;
    }

    /**
     * @param string|null $projectName
     * @return RundeckProjectEntity
     */
    public function setProjectName(?string $projectName): self
    {
        $this->projectName = $projectName;
        return $this;
    }
}
