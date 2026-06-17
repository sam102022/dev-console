<?php
declare(strict_types=1);

namespace App\repository\model;

use App\model\AbstractModel;
use App\model\EnumEnvironment;

class NewRelicEntity extends AbstractModel
{
    public string $name;
    public EnumEnvironment $environment;
    public string $url;

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return NewRelicEntity
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return EnumEnvironment
     */
    public function getEnvironment(): EnumEnvironment
    {
        return $this->environment;
    }

    /**
     * @param EnumEnvironment $environment
     * @return NewRelicEntity
     */
    public function setEnvironment(EnumEnvironment $environment): self
    {
        $this->environment = $environment;
        return $this;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @param string $url
     * @return NewRelicEntity
     */
    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public static function build(string $name, EnumEnvironment $environment, string $url): self
    {
        $project = new self();
        $project->setName($name);
        $project->setEnvironment($environment);
        $project->setUrl($url);

        return $project;
    }

}