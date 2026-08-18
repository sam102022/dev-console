<?php
declare(strict_types=1);

namespace App\tests\service;

use App\service\Translator;
use App\tests\AbstractTestCase;
use App\exception\TechnicalException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Classe TranslatorTest
 *
 * Tests unitaires paramétrés pour le service Translator.
 */
class TranslatorTest extends AbstractTestCase
{
    private string $translationsPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->translationsPath = dirname(__DIR__, 2) . '/translations';
    }

    /**
     * Teste les traductions simples de clés statiques pour différentes langues.
     */
    #[DataProvider('translationDataProvider')]
    public function testTranslate(string $lang, string $key, string $expected): void
    {
        $translator = new Translator($lang, $this->translationsPath);
        
        $this->assertEquals($lang, $translator->getLocale());
        $this->assertEquals($expected, $translator->translate($key));
    }

    /**
     * Fournisseur de données pour tester les traductions simples.
     */
    public static function translationDataProvider(): array
    {
        return [
            // Traductions en français
            'fr_action' => ['fr', 'labels.action', 'Action'],
            'fr_close' => ['fr', 'labels.close', 'Fermer'],
            'fr_yes' => ['fr', 'labels.yes', 'Oui'],
            
            // Traductions en anglais
            'en_action' => ['en', 'labels.action', 'Action'],
            'en_close' => ['en', 'labels.close', 'Close'],
            'en_yes' => ['en', 'labels.yes', 'Yes'],

            // Clés inconnues (doivent retourner la valeur par défaut ou chaîne vide)
            'fr_unknown' => ['fr', 'labels.non_existent_key_xyz', ''],
            'en_unknown' => ['en', 'labels.non_existent_key_xyz', ''],
        ];
    }

    /**
     * Teste la traduction avec substitution de paramètres dynamiques (placeholders {{key}}).
     */
    #[DataProvider('parameterizedTranslationDataProvider')]
    public function testTranslateWithParameters(string $lang, string $key, array $placeholders, string $default, string $expected): void
    {
        $translator = new Translator($lang, $this->translationsPath);
        $result = $translator->translate($key, $placeholders, $default);
        
        $this->assertEquals($expected, $result);
    }

    /**
     * Fournisseur de données pour tester les traductions paramétrées.
     */
    public static function parameterizedTranslationDataProvider(): array
    {
        return [
            'fr_non_existent_with_default' => [
                'fr',
                'labels.some_non_existent',
                [],
                'Default Value',
                'Default Value'
            ],
            'fr_with_custom_placeholders' => [
                'fr',
                'labels.close', // just a key we know exists
                ['close' => 'custom'], // but won't be replaced because it does not have {{close}}
                '',
                'Fermer'
            ]
        ];
    }

    /**
     * Teste qu'une exception de type TechnicalException est levée si le fichier de traduction n'existe pas.
     */
    public function testLoadMissingTranslationFileThrowsException(): void
    {
        $this->expectException(TechnicalException::class);
        $this->expectExceptionMessage("Translation file for 'de' not found.");
        
        new Translator('de', $this->translationsPath);
    }
}
