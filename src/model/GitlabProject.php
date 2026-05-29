<?php
declare(strict_types=1);

namespace App\model;

class GitlabProject extends AbstractModel
{
    private int $id;
    private ?string $description;
    private string $name;
    private string $nameWithNamespace;
    private string $path;
    private string $pathWithNamespace;
    private string $createdAt;
    private string $defaultBranch;
    private string $webUrl;
    private bool $archived;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return GitlabProject
     */
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string|null $description
     * @return GitlabProject
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return GitlabProject
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getNameWithNamespace(): string
    {
        return $this->nameWithNamespace;
    }

    /**
     * @param string $nameWithNamespace
     * @return GitlabProject
     */
    public function setNameWithNamespace(string $nameWithNamespace): self
    {
        $this->nameWithNamespace = $nameWithNamespace;
        return $this;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @param string $path
     * @return GitlabProject
     */
    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * @return string
     */
    public function getPathWithNamespace(): string
    {
        return $this->pathWithNamespace;
    }

    /**
     * @param string $pathWithNamespace
     * @return GitlabProject
     */
    public function setPathWithNamespace(string $pathWithNamespace): self
    {
        $this->pathWithNamespace = $pathWithNamespace;
        return $this;
    }

    /**
     * @return string
     */
    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    /**
     * @param string $createdAt
     * @return GitlabProject
     */
    public function setCreatedAt(string $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return string
     */
    public function getDefaultBranch(): string
    {
        return $this->defaultBranch;
    }

    /**
     * @param string $defaultBranch
     * @return GitlabProject
     */
    public function setDefaultBranch(string $defaultBranch): self
    {
        $this->defaultBranch = $defaultBranch;
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
     * @return GitlabProject
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

    public static function build(int $id, ?string $description, string $name, string $nameWithNamespace,
                                 string $path, string $path_with_namespace, string $default_branch,
                                 string $created_at, string $web_url, bool $archived)
    {
        $project = new self();
        $project->setId($id);
        $project->setDescription($description);
        $project->setName($name);
        $project->setNameWithNamespace($nameWithNamespace);
        $project->setPath($path);
        $project->setPathWithNamespace($path_with_namespace);
        $project->setDefaultBranch($default_branch);
        $project->setCreatedAt($created_at);
        $project->setWebUrl($web_url);
        $project->setArchived($archived);

        return $project;
    }
}
