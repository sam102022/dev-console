<?php
declare(strict_types=1);

namespace App\model;

use InvalidArgumentException;

class ParamRepository
{
    /** @var string L'hôte de la base de données. */
    private string $databaseHost;

    /** @var int Le port de la base de données. */
    private int $databasePort;

    /** @var string Le nom de la base de données. */
    private string $databaseName;

    /** @var string Le nom d'utilisateur pour la base de données. */
    private string $databaseUser;

    /** @var string Le mot de passe pour la base de données. */
    private string $databasePassword;

    public function getDatabaseHost(): string
    {
        return $this->databaseHost;
    }

    public function setDatabaseHost(string $databaseHost): void
    {
        $this->databaseHost = $databaseHost;
    }

    public function getDatabasePort(): int
    {
        return $this->databasePort;
    }

    public function setDatabasePort(int $databasePort): void
    {
        $this->databasePort = $databasePort;
    }

    public function getDatabaseName(): string
    {
        return $this->databaseName;
    }

    public function setDatabaseName(string $databaseName): void
    {
        $this->databaseName = $databaseName;
    }

    public function getDatabaseUser(): string
    {
        return $this->databaseUser;
    }

    public function setDatabaseUser(string $databaseUser): void
    {
        $this->databaseUser = $databaseUser;
    }

    public function getDatabasePassword(): string
    {
        return $this->databasePassword;
    }

    public function setDatabasePassword(string $databasePassword): void
    {
        $this->databasePassword = $databasePassword;
    }

    public static function parse(array $params): self
    {
        if (!isset($params['database_host'], $params['database_port'], $params['database_user'],
            $params['database_password'], $params['database_name'])) {
            throw new InvalidArgumentException("Certains paramètres database requis sont manquants.");
        }

        $paramRepository = new self();
        $paramRepository->setDatabaseHost($params['database_host'] ?? 'localhost');
        $paramRepository->setDatabasePort((int)($params['database_port'] ?? 3306));
        $paramRepository->setDatabaseUser($params['database_user'] ?? '');
        $paramRepository->setDatabasePassword($params['database_password'] ?? '');
        $paramRepository->setDatabaseName($params['database_name'] ?? '');

        return $paramRepository;
    }
}