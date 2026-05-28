<?php
declare(strict_types=1);

namespace App\repository\model;

use App\model\Project;

class ProjectEntity
{
    private string $name;
    public ?string $serviceName = null;
    private string $sf;
    private string $sfName;
    private string $subsf;
    private bool $cloudGCP;
    private ?string $springBootVersion;
    private ?string $javaVersion;
    private array $urlHealthCheck = [];
    private array $urlLogs = [];

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string|null
     */
    public function getServiceName(): ?string
    {
        return $this->serviceName;
    }

    public function getSf(): string
    {
        return $this->sf;
    }

    public function getSfName(): string
    {
        return $this->sfName;
    }

    public function getSubsf(): string
    {
        return $this->subsf;
    }

    public function isCloudGCP(): bool
    {
        return $this->cloudGCP;
    }

    public function getSpringBootVersion(): ?string
    {
        return $this->springBootVersion;
    }

    public function getJavaVersion(): ?string
    {
        return $this->javaVersion;
    }

    public function getUrlHealthCheck(): array
    {
        return $this->urlHealthCheck;
    }

    public function getUrlLogs(): array
    {
        return $this->urlLogs;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
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

    public function setSf(string $sf): void
    {
        $this->sf = $sf;
    }

    public function setSfName(string $sfName): void
    {
        $this->sfName = $sfName;
    }

    public function setSubsf(string $subsf): void
    {
        $this->subsf = $subsf;
    }

    public function setCloudGCP(bool $cloudGCP): void
    {
        $this->cloudGCP = $cloudGCP;
    }

    public function setSpringBootVersion(?string $springBootVersion): void
    {
        $this->springBootVersion = $springBootVersion;
    }

    public function setJavaVersion(?string $javaVersion): void
    {
        $this->javaVersion = $javaVersion;
    }

    public function setUrlHealthCheck(array $urlHealthCheck): void
    {
        $this->urlHealthCheck = $urlHealthCheck;
    }

    public function setUrlLogs(array $urlLogs): void
    {
        $this->urlLogs = $urlLogs;
    }

    public static function build(
        string  $name,
        ?string $serviceName,
        string  $sf,
        string  $sfName,
        string  $subsf,
        bool    $cloudGCP,
        ?string $springBootVersion,
        ?string $javaVersion,
        array   $urlHealthCheck = [],
        array   $urlLogs = []
    )
    {
        $project = new self();
        $project->setName($name);
        $project->setServiceName($serviceName);
        $project->setSf($sf);
        $project->setSfName($sfName);
        $project->setSubsf($subsf);
        $project->setCloudGCP($cloudGCP);
        $project->setSpringBootVersion($springBootVersion);
        $project->setJavaVersion($javaVersion);
        $project->setUrlHealthCheck($urlHealthCheck);
        $project->setUrlLogs($urlLogs);
        return $project;
    }
}
