<?php
declare(strict_types=1);

namespace App\context;

/**
 * Classe LocaleContext
 *
 * Gère le contexte de la localisation de l'application.
 * Elle encapsule la langue (lang) et la locale (locale) utilisées pour
 * la traduction et le formatage.
 */
final class LocaleContext
{
    /**
     * @var string La locale actuelle (ex: 'fr_FR', 'en_US').
     */
    private string $locale;

    /**
     * @var string La langue actuelle (ex: 'fr', 'en').
     */
    private string $lang;

    /**
     * Constructeur de la classe LocaleContext.
     *
     * @param string $defaultLocale La locale par défaut.
     * @param string $defaultLanguage La langue par défaut.
     */
    public function __construct(string $defaultLocale, string $defaultLanguage)
    {
        $this->locale = $defaultLocale;
        $this->lang = $defaultLanguage;
    }

    /**
     * Définit la langue actuelle.
     *
     * @param string $lang Le code de la langue (ex: 'fr').
     */
    public function setLang(string $lang): void
    {
        $this->lang = $lang;
    }

    /**
     * Récupère la langue actuelle.
     *
     * @return string Le code de la langue.
     */
    public function getLang(): string
    {
        return $this->lang;
    }

    /**
     * Définit la locale actuelle.
     *
     * @param string $locale Le code de la locale (ex: 'fr_FR').
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    /**
     * Récupère la locale actuelle.
     *
     * @return string Le code de la locale.
     */
    public function getLocale(): string
    {
        return $this->locale;
    }
}
