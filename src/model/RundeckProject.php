<?php
declare(strict_types=1);

namespace App\model;

class RundeckProject
{
    private string $nom = '';
    private string $env = '';
    private string $sf = '';
    private ?string $category = null;
    private string $token = '';
    private ?string $path = null;
    private ?string $projectName = null;

    /**
     * @return string
     */
    public function getNom(): string
    {
        return $this->nom;
    }

    /**
     * @param string $nom
     * @return RundeckProject
     */
    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    /**
     * @return string
     */
    public function getEnv(): string
    {
        return $this->env;
    }

    /**
     * @param string $env
     * @return RundeckProject
     */
    public function setEnv(string $env): self
    {
        $this->env = $env;
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
     * @return RundeckProject
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
     * @return RundeckProject
     */
    public function setCategory(?string $category): self
    {
        $this->category = $category;
        return $this;
    }

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @param string $token
     * @return RundeckProject
     */
    public function setToken(string $token): self
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
     * @return RundeckProject
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
     * @return RundeckProject
     */
    public function setProjectName(?string $projectName): self
    {
        $this->projectName = $projectName;
        return $this;
    }
}
