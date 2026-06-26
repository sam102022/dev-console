<?php
declare(strict_types=1);

namespace App\tests\context;

use App\context\LocaleContext;
use PHPUnit\Framework\TestCase;

class LocaleContextTest extends TestCase
{
    final public function testConstructorSetsDefaultLocaleAndLanguage(): void
    {
        // GIVEN
        $context = new LocaleContext('fr_FR', 'fr');

        // WHEN / THEN
        TestCase::assertSame('fr_FR', $context->getLocale());
        TestCase::assertSame('fr', $context->getLang());
    }

    final public function testLangCanBeUpdated(): void
    {
        // GIVEN
        $context = new LocaleContext('fr_FR', 'fr');

        // WHEN
        $context->setLang('en');

        // THEN
        TestCase::assertSame('en', $context->getLang());
    }

    final public function testLocaleCanBeUpdated(): void
    {
        // GIVEN
        $context = new LocaleContext('fr_FR', 'fr');

        // WHEN
        $context->setLocale('en_US');

        // THEN
        TestCase::assertSame('en_US', $context->getLocale());
    }

    final public function testLocaleAndLangCanBeUpdatedIndependently(): void
    {
        // GIVEN
        $context = new LocaleContext('fr_FR', 'fr');

        // WHEN
        $context->setLang('en');
        $context->setLocale('en_GB');

        // THEN
        TestCase::assertSame('en', $context->getLang());
        TestCase::assertSame('en_GB', $context->getLocale());
    }
}
