<?php
declare(strict_types=1);

namespace App\tests\service;

use App\service\IconService;
use App\service\Translator;
use App\service\TwigTranslationExtension;
use App\tests\AbstractTestCase;
use Twig\TwigFilter;
use Twig\TwigFunction;

class TwigTranslationExtensionTest extends AbstractTestCase
{
    private Translator $translatorMock;
    private TwigTranslationExtension $extension;

    protected function setUp(): void
    {
        $this->translatorMock = $this->createMock(Translator::class);
        $this->extension = new TwigTranslationExtension($this->translatorMock);

        // Initialize the singleton for the test environment
        IconService::getInstance();
    }

    final public function testGetFunctions(): void
    {
        $functions = $this->extension->getFunctions();
        $this->assertCount(2, $functions);

        $this->assertInstanceOf(TwigFunction::class, $functions[0]);
        $this->assertEquals('icon', $functions[0]->getName());

        $this->assertInstanceOf(TwigFunction::class, $functions[1]);
        $this->assertEquals('translate', $functions[1]->getName());
    }

    final public function testGetFilters(): void
    {
        $filters = $this->extension->getFilters();
        $this->assertCount(1, $filters);

        $this->assertInstanceOf(TwigFilter::class, $filters[0]);
        $this->assertEquals('translate', $filters[0]->getName());
    }

    final public function testIcon(): void
    {
        $iconHtml = $this->extension->icon('calendar');
        $this->assertStringContainsString('class="fas fa-calendar-alt"', $iconHtml);
    }

    final public function testTranslateSimple(): void
    {
        $this->translatorMock->method('getLocale')->willReturn('fr');
        $this->translatorMock->method('translate')
            ->with('welcome', [], 'fr')
            ->willReturn('Bienvenue');

        $result = $this->extension->translate('welcome');
        $this->assertEquals('Bienvenue', $result);
    }

    final public function testTranslateWithParams(): void
    {
        $this->translatorMock->method('getLocale')->willReturn('fr');
        $this->translatorMock->method('translate')
            ->with('msg.welcome', [], 'fr')
            ->willReturn('Bienvenue, {username}!');

        $result = $this->extension->translate('msg.welcome', ['username' => 'John']);
        $this->assertEquals('Bienvenue, John!', $result);
    }

    final public function testTranslateWithFallbackToEnglish(): void
    {
        $this->translatorMock->method('getLocale')->willReturn('fr');
        $this->translatorMock->expects($this->exactly(2))
            ->method('translate')
            ->willReturnMap([
                ['unknown.key', [], 'fr', 'unknown.key'], // Simulate translation not found in 'fr'
                ['unknown.key', [], 'en', 'Found in English'], // Simulate translation found in 'en'
            ]);

        $result = $this->extension->translate('unknown.key');
        $this->assertEquals('Found in English', $result);
    }

    final public function testTranslateWithFinalFallback(): void
    {
        $this->translatorMock->method('getLocale')->willReturn('fr');
        $this->translatorMock->expects($this->exactly(2))
            ->method('translate')
            ->willReturn('nonexistent.key'); // Always return the key itself

        $result = $this->extension->translate('nonexistent.key');
        $this->assertEquals('??nonexistent.key??', $result);
    }

    final public function testTranslateUsesCache(): void
    {
        $this->translatorMock->method('getLocale')->willReturn('fr');
        $this->translatorMock->expects($this->once()) // Should be called only once
            ->method('translate')
            ->with('cached.key', [], 'fr')
            ->willReturn('Cached Value');

        // First call - should trigger translation
        $this->extension->translate('cached.key');
        // Second call - should use cache
        $result = $this->extension->translate('cached.key');

        $this->assertEquals('Cached Value', $result);
    }
}