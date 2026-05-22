<?php
declare(strict_types=1);

namespace App\exception;

use Exception;
use JsonSerializable;
use Throwable;

/**
 * Classe FunctionalException
 *
 * Exception personnalisée pour représenter les erreurs fonctionnelles de l'application
 * (ex: violation d'une règle métier, données invalides soumises par l'utilisateur).
 * Implémente JsonSerializable pour faciliter la sérialisation de l'exception en JSON.
 */
class FunctionalException extends Exception implements JsonSerializable
{
    /**
     * Constructeur de la classe FunctionalException.
     *
     * @param string $message Le message de l'exception.
     * @param int $code Le code de l'erreur.
     * @param Throwable|null $previous L'exception précédente, utilisée pour le chaînage d'exceptions.
     */
    public function __construct(string $message, int $code = 0, Throwable|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Méthode de fabrique pour créer une exception avec un simple message.
     *
     * @param string $message Le message de l'exception.
     * @return self
     */
    public static function createWithMessage(string $message): self
    {
        return new self($message, 0, null);
    }

    /**
     * Méthode de fabrique pour créer une exception avec un message et un code.
     *
     * @param string $message Le message de l'exception.
     * @param int $code Le code de l'erreur.
     * @return self
     */
    public static function createWithCode(string $message, int $code): self
    {
        return new self($message, $code, null);
    }

    /**
     * Représentation textuelle de l'objet exception.
     */
    public function __toString(): string
    {
        return __CLASS__ . ": [$this->code]: $this->message\n";
    }

    /**
     * Spécifie les données qui doivent être sérialisées en JSON.
     *
     * @return array Un tableau contenant les propriétés de l'exception.
     */
    public function jsonSerialize(): array
    {
        $props = (array) $this;
        $result = [];
        foreach ($props as $key => $value) {
            if (preg_match('/^\0.*\0(.+)$/', $key, $m)) {
                $name = $m[1];
            } else {
                $name = $key;
            }
            $result[$name] = $value;
        }
        return $result;
    }
}
