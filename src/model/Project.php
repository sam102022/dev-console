<?php
declare(strict_types=1);

namespace App\model;

class Project extends AbstractModel
{
    public string $name;
    public ?string $serviceName = null;
    public ?string $sf;
    public ?string $sfName;
    public ?string $subsf;
    public bool $cloudGCP;
    public ?string $springBoot;
    public ?string $java;
    private string $webUrl;
    private bool $archived;
    public array $urlHealthCheck = [];
    public array $urlLogs = [];

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return Project
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getServiceName(): ?string
    {
        return $this->serviceName;
    }

    /**
     * @param string|null $serviceName
     * @return Project
     */
    public function setServiceName(?string $serviceName): self
    {
        $this->serviceName = $serviceName;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getSf(): ?string
    {
        return $this->sf;
    }

    /**
     * @param string|null $sf
     * @return Project
     */
    public function setSf(?string $sf): self
    {
        $this->sf = $sf;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getSfName(): ?string
    {
        return $this->sfName;
    }

    /**
     * @param string|null $sfName
     * @return Project
     */
    public function setSfName(?string $sfName): self
    {
        $this->sfName = $sfName;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getSubsf(): ?string
    {
        return $this->subsf;
    }

    /**
     * @param string|null $subsf
     * @return Project
     */
    public function setSubsf(?string $subsf): self
    {
        $this->subsf = $subsf;
        return $this;
    }

    /**
     * @return bool
     */
    public function isCloudGCP(): bool
    {
        return $this->cloudGCP;
    }

    /**
     * @param bool $cloudGCP
     * @return Project
     */
    public function setCloudGCP(bool $cloudGCP): self
    {
        $this->cloudGCP = $cloudGCP;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getSpringBoot(): ?string
    {
        return $this->springBoot;
    }

    /**
     * @param string|null $springBoot
     * @return Project
     */
    public function setSpringBoot(?string $springBoot): self
    {
        $this->springBoot = $springBoot;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getJava(): ?string
    {
        return $this->java;
    }

    /**
     * @param string|null $java
     * @return Project
     */
    public function setJava(?string $java): self
    {
        $this->java = $java;
        return $this;
    }

    /**
     * @return string
     */
    public function getWebUrl(): string
    {
        return $this->webUrl;
    }

    /**
     * @param string $webUrl
     * @return Project
     */
    public function setWebUrl(string $webUrl): self
    {
        $this->webUrl = $webUrl;
        return $this;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function setArchived(bool $archived): self
    {
        $this->archived = $archived;
        return $this;
    }

    public function getUrlHealthCheck(): array
    {
        return $this->urlHealthCheck;
    }

    public function setUrlHealthCheck(array $urlHealthCheck): self
    {
        $this->urlHealthCheck = $urlHealthCheck;
        return $this;
    }

    public function getUrlLogs(): array
    {
        return $this->urlLogs;
    }

    public function setUrlLogs(array $urlLogs): self
    {
        $this->urlLogs = $urlLogs;
        return $this;
    }

    public static function build(string $name, ?string $serviceName, ?string $sf, ?string $sfName, ?string $subsf, bool $cloudGCP,
        ?string $springBoot, ?string $java, string  $webUrl, bool $archived, array $urlHealthCheck, array $urlLogs): self
    {
        $project = new self();
        $project->setName($name);
        $project->setServiceName($serviceName);
        $project->setSf($sf);
        $project->setSfName($sfName);
        $project->setSubsf($subsf);
        $project->setCloudGCP($cloudGCP);
        $project->setSpringBoot($springBoot);
        $project->setJava($java);
        $project->setWebUrl($webUrl);
        $project->setArchived($archived);
        $project->setUrlHealthCheck($urlHealthCheck);
        $project->setUrlLogs($urlLogs);
        return $project;
    }

}
