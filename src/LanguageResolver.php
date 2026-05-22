<?php
declare(strict_types=1);

namespace App;

/**
 * Classe LanguageResolver
 *
 * Détermine la langue la plus appropriée pour l'utilisateur en se basant sur
 * différentes sources, avec un ordre de priorité défini.
 */
final class LanguageResolver
{
    /**
     * Constructeur de la classe LanguageResolver.
     *
     * @param array $supportedLanguages La liste des langues supportées par l'application (ex: ['fr', 'en']).
     * @param string $defaultLanguage La langue à utiliser si aucune langue supportée n'est trouvée.
     */
    public function __construct(
        private readonly array $supportedLanguages,
        private readonly string $defaultLanguage
    ) {
    }

    /**
     * Résout la langue à utiliser en suivant un ordre de priorité.
     *
     * L'ordre de priorité est le suivant :
     * 1. Langue spécifiée dans le paramètre de requête GET.
     * 2. Langue stockée en session.
     * 3. Langue négociée à partir de l'en-tête HTTP 'Accept-Language'.
     * 4. Langue par défaut.
     *
     * @param string|null $queryLang La langue provenant du paramètre GET.
     * @param string|null $sessionLang La langue provenant de la session.
     * @param string|null $acceptLanguageHeader La valeur de l'en-tête HTTP 'Accept-Language'.
     */
    public function resolve(
        ?string $queryLang,
        ?string $sessionLang,
        ?string $acceptLanguageHeader
    ): string {
        if ($queryLang && $this->isSupported($queryLang)) {
            return $queryLang;
        }

        if ($sessionLang && $this->isSupported($sessionLang)) {
            return $sessionLang;
        }

        if ($acceptLanguageHeader) {
            foreach ($this->parseAcceptLanguage($acceptLanguageHeader) as $lang) {
                if ($this->isSupported($lang)) {
                    return $lang;
                }
            }
        }

        return $this->defaultLanguage;
    }

    /**
     * Analyse l'en-tête HTTP 'Accept-Language'.
     *
     * @param string $header La valeur de l'en-tête.
     */
    private function parseAcceptLanguage(string $header): array
    {
        $langs = [];

        foreach (explode(',', $header) as $part) {
            [$lang] = explode(';', trim($part));
            $langs[] = substr($lang, 0, 2); // fr-FR → fr
        }

        return $langs;
    }

    /**
     * Vérifie si une langue est supportée par l'application.
     *
     * @param string $lang Le code de la langue à vérifier.
     */
    private function isSupported(string $lang): bool
    {
        return in_array($lang, $this->supportedLanguages, true);
    }
}
