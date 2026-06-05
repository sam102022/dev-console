<?php
declare(strict_types=1);

namespace App\model;

use InvalidArgumentException;

class ParamGitLab
{
    /** @var string L'URL du serveur gitlab. */
    private string $gitlabUrl;

    /** @var string L'URL du serveur gitlab. */
    private string $gitlabToken;

    /** @var int L'ID du projet de contrat commercial GitLab. */
    private int $gitlabBusinessContractProjectId;

    /** @var string Le chemin par défaut du groupe GitLab. */
    private string $gitlabPathGroupDefault;

    /** @var array Les projets migrés sur GKE séparés par une virgule. */
    private array $projectsInGke;

    /** @var array Les projets à exclure séparés par une virgule. */
    private array $excludeProjects;

    public function getGitlabUrl(): string
    {
        return $this->gitlabUrl;
    }

    public function setGitlabUrl(string $gitlabUrl): void
    {
        $this->gitlabUrl = $gitlabUrl;
    }

    public function getGitlabToken(): string
    {
        return $this->gitlabToken;
    }

    public function setGitlabToken(string $gitlabToken): void
    {
        $this->gitlabToken = $gitlabToken;
    }

    public function getGitlabBusinessContractProjectId(): int
    {
        return $this->gitlabBusinessContractProjectId;
    }

    public function setGitlabBusinessContractProjectId(int $gitlabBusinessContractProjectId): void
    {
        $this->gitlabBusinessContractProjectId = $gitlabBusinessContractProjectId;
    }

    public function getGitlabPathGroupDefault(): string
    {
        return $this->gitlabPathGroupDefault;
    }

    public function setGitlabPathGroupDefault(string $gitlabPathGroupDefault): void
    {
        $this->gitlabPathGroupDefault = $gitlabPathGroupDefault;
    }

    public function getProjectsInGke(): array
    {
        return $this->projectsInGke;
    }

    public function setProjectsInGke(array $projectsInGke): void
    {
        $this->projectsInGke = $projectsInGke;
    }

    public function getExcludeProjects(): array
    {
        return $this->excludeProjects;
    }

    public function setExcludeProjects(array $excludeProjects): void
    {
        $this->excludeProjects = $excludeProjects;
    }

    public static function parse(array $params): self
    {
        if (!isset(
            $params['gitlab_url'], $params['gitlab_token'],
            $params['gitlab_business_contract_project_id'], $params['gitlab_path_group_default'])) {
            throw new InvalidArgumentException("Certains paramètres gitlab requis sont manquants.");
        }

        $paramGitLab = new self();
        $paramGitLab->setGitlabUrl($params['gitlab_url'] ?? '');
        $paramGitLab->setGitlabToken($params['gitlab_token'] ?? '');
        $paramGitLab->setGitlabBusinessContractProjectId((int)($params['gitlab_business_contract_project_id'] ?? 0));
        $paramGitLab->setGitlabPathGroupDefault($params['gitlab_path_group_default'] ?? '');

        $projectsInGke = [];
        if (!empty($params['projects_in_gke'])) {
            $projectsInGke = array_map('trim', explode(',', $params['projects_in_gke']));
        }
        $paramGitLab->setProjectsInGke($projectsInGke);

        $excludeProjects = [];
        if (!empty($params['exclude_projects'])) {
            $excludeProjects = array_map('trim', explode(',', $params['exclude_projects']));
        }
        $paramGitLab->setExcludeProjects($excludeProjects);

        return $paramGitLab;
    }
}