<?php
declare(strict_types=1);

namespace App\service;

use App\exception\TechnicalException;

/**
 * Classe Translator
 *
 * Gère les traductions de l'application en chargeant des fichiers de langue
 * et en fournissant une méthode pour récupérer les chaînes traduites.
 */
class Translator
{
    private array $messages = [];

    /**
     * Constructeur de la classe Translator.
     *
     * @param string $locale La locale à utiliser pour les traductions (ex: 'fr', 'en').
     * @param string $translationsPath Le chemin vers le dossier contenant les fichiers de traduction.
     * @throws TechnicalException Exception possible
     */
    public function __construct(
        private readonly string $locale,
        private readonly string $translationsPath
    ) {
        $this->load();
    }

    /**
     * Charge le fichier de traduction correspondant à la locale.
     * @throws TechnicalException Exception possible
     */
    private function load(): void
    {
        $file = "$this->translationsPath/$this->locale.php";
        if (!realpath($file) || !file_exists(realpath($file))) {
            throw TechnicalException::createWithMessage("Translation file for '$this->locale' not found.");
        }
        $this->messages = include $file; // NOSONAR
    }

    /**
     * Récupère une traduction en fonction d'une clé et remplace les placeholders.
     *
     * @param string $key Clé de traduction (ex: "key1.subkey1").
     * @param array $placeholders Variables à remplacer dans la traduction (ex: ['variable' => 'valeur']).
     * @param string $default Valeur par défaut si la clé n'est pas trouvée.
     */
    public function translate(string $key, array $placeholders = [], string $default = ''): string
    {
        $keys = explode('.', $key);
        $value = $this->messages;

        foreach ($keys as $subkey) {
            if (!isset($value[$subkey])) {
                return $default;
            }
            $value = $value[$subkey];
        }

        // Remplace les placeholders
        foreach ($placeholders as $placeholder => $replacement) {
            $value = str_replace("{{$placeholder}}", '' . $replacement, $value);
        }

        return $value;
    }

    /**
     * Retourne tous les messages chargés pour la locale actuelle.
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Retourne la locale actuelle.
     */
    public function getLocale(): string
    {
        return $this->locale;
    }
}
