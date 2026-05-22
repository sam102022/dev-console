<?php
declare(strict_types=1);

namespace App\service;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Définitions de fonctions personnalisées pour twig
 */
class TwigTranslationExtension extends AbstractExtension
{
    /**
     * @param Translator $translator Traducteur
     */
    public function __construct(private readonly Translator $translator)
    {
    }

    /**
     * Cache interne simple pour éviter d'appeler deux fois le même texte
     */
    private array $cache = [];

    public function getFunctions(): array
    {
        return [
            new TwigFunction('icon', [
                $this,
                'icon'
            ]),
            new TwigFunction('translate', [
                $this,
                'translate'
            ])
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('translate', [
                $this,
                'translate'
            ])
        ];
    }

    public function icon(string $key): string
    {
        return IconService::getInstance()->get($key);
    }

    /**
     * Traduction avec paramètres.
     *
     * Exemple :
     * {{ translate('msg.welcome', { username: 'Sam' }) }}
     */
    /**
     * Traduction avancée avec :
     * - paramètres
     * - plusieurs syntaxes de variables
     * - fallback si clé inconnue
     * - petit cache
     */
    public function translate(string $key, array $params = [], ?string $locale = null): string
    {
        $locale = $locale ?? $this->translator->getLocale();
        $cacheKey = "t:$locale:$key:" . md5(json_encode($params));

        // Cache interne
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // 1️⃣ Traduction brute
        $translated = $this->translator->translate($key, [], $locale);

        // 2️⃣ Fallback locale → 'en'
        if ($translated === $key && $locale !== 'en') {
            $translated = $this->translator->translate($key, [], 'en');
        }

        // 3️⃣ Fallback final : si toujours rien → afficher "??key??"
        if ($translated === $key) {
            return $this->cache[$cacheKey] = "??$key??";
        }

        // 4️⃣ Application des paramètres
        if (!empty($params)) {
            $translated = $this->translateStringWithParams($translated, $params);
        }

        return $this->cache[$cacheKey] = $translated;
    }

    private function translateStringWithParams(string $text, array $params): string
    {
        foreach ($params as $k => $v) {
            $text = strtr($text, [
                '{' . $k . '}' => $v,
                '%' . $k . '%' => $v,
                ':' . $k => $v
            ]);
        }
        return $text;
    }
}
