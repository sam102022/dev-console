<?php
declare(strict_types=1);

namespace App;

use App\context\LocaleContext;
use Monolog\Level;

/**
 * Classe TestContainer
 *
 * Conteneur d'injection de dépendances pour l'environnement de test.
 * Cette classe étend AbstractContainer et le configure avec les paramètres
 * spécifiques aux tests (fichier de log de test, etc.).
 */
final class TestContainer extends AbstractContainer
{
    /**
     * Constructeur de la classe TestContainer.
     *
     * Initialise le conteneur parent avec la configuration de test.
     * Si aucun contexte de locale n'est fourni, il utilise les valeurs par défaut.
     *
     * @param LocaleContext|null $localeContext Le contexte de la locale (langue, région).
     */
    public function __construct(?LocaleContext $localeContext = null)
    {
        $dirname = dirname(__DIR__);
        parent::__construct(
            $dirname . '/' . TEST_LOG_FILE,
            ENVIRONMENT_TEST,
            $dirname . '/templates',
            Level::Debug,
            $localeContext ?? new LocaleContext(Kernel::LOCALE_DEFAULT, Kernel::LANGUAGE_DEFAULT)
        );
    }

}
