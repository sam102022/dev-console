<?php
declare(strict_types=1);

namespace App\repository\model;

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
    private ?string $techno = null;
    private ?string $subscriptionName = null;
    private ?string $mdmWorkloadVersion = null;
    private string $webUrl;
    private bool $archived;
    private array $urlHealthCheck = [];
    private array $urlLogs = [];
    private array $urlFronts = [];
    private array $urlPubsubs = [];
    private array $urlsRundeck = [];
    private array $deploymentGcpUrl = [];

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

    public function getTechno(): ?string
    {
        return $this->techno;
    }

    public function getSubscriptionName(): ?string
    {
        return $this->subscriptionName;
    }

    public function getMdmWorkloadVersion(): ?string
    {
        return $this->mdmWorkloadVersion;
    }

    /**
     * @return string
     */
    public function getWebUrl(): string
    {
        return $this->webUrl;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function getUrlHealthCheck(): array
    {
        return $this->urlHealthCheck;
    }

    public function getUrlLogs(): array
    {
        return $this->urlLogs;
    }

    public function getUrlFronts(): array
    {
        return $this->urlFronts;
    }

    public function getUrlPubsubs(): array
    {
        return $this->urlPubsubs;
    }

    public function getUrlsRundeck(): array
    {
        return $this->urlsRundeck;
    }

    public function getDeploymentGcpUrl(): array
    {
        return $this->deploymentGcpUrl;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @param string|null $serviceName
     * @return ProjectEntity
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

    public function setTechno(?string $techno): void
    {
        $this->techno = $techno;
    }

    public function setSubscriptionName(?string $subscriptionName): void
    {
        $this->subscriptionName = $subscriptionName;
    }

    public function setMdmWorkloadVersion(?string $mdmWorkloadVersion): void
    {
        $this->mdmWorkloadVersion = $mdmWorkloadVersion;
    }

    /**
     * @param string $webUrl
     * @return ProjectEntity
     */
    public function setWebUrl(string $webUrl): self
    {
        $this->webUrl = $webUrl;
        return $this;
    }

    public function setArchived(bool $archived): self
    {
        $this->archived = $archived;
        return $this;
    }

    public function setUrlHealthCheck(array $urlHealthCheck): void
    {
        $this->urlHealthCheck = $urlHealthCheck;
    }

    public function setUrlLogs(array $urlLogs): void
    {
        $this->urlLogs = $urlLogs;
    }

    public function setUrlFronts(array $urlFronts): void
    {
        $this->urlFronts = $urlFronts;
    }

    public function setUrlPubsubs(array $urlPubsubs): void
    {
        $this->urlPubsubs = $urlPubsubs;
    }

    public function setUrlsRundeck(array $urlsRundeck): void
    {
        $this->urlsRundeck = $urlsRundeck;
    }

    public function setDeploymentGcpUrl(array $deploymentGcpUrl): void
    {
        $this->deploymentGcpUrl = $deploymentGcpUrl;
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
        ?string $techno,
        ?string $subscriptionName,
        string  $webUrl,
        bool    $archived,
        array   $urlHealthCheck = [],
        array   $urlLogs = [],
        array   $urlFronts = [],
        array   $urlPubsubs = [],
        ?string $mdmWorkloadVersion = null,
        array   $urlsRundeck = [],
        array   $deploymentGcpUrl = []
    ): self
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
        $project->setTechno($techno);
        $project->setSubscriptionName($subscriptionName);
        $project->setMdmWorkloadVersion($mdmWorkloadVersion);
        $project->setUrlHealthCheck($urlHealthCheck);
        $project->setUrlLogs($urlLogs);
        $project->setUrlFronts($urlFronts);
        $project->setUrlPubsubs($urlPubsubs);
        $project->setUrlsRundeck($urlsRundeck);
        $project->setDeploymentGcpUrl($deploymentGcpUrl);
        $project->setWebUrl($webUrl);
        $project->setArchived($archived);
        return $project;
    }
}