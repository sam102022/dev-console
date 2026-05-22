<?php
declare(strict_types=1);

namespace App;

use App\context\LocaleContext;
use Monolog\Level;

/**
 * Classe Container
 *
 * Conteneur d'injection de dépendances pour l'environnement de production.
 * Cette classe étend AbstractContainer et le configure avec les paramètres
 * spécifiques à la production (chemins des logs, templates, niveau de log, etc.).
 */
final class Container extends AbstractContainer
{
    /**
     * Constructeur de la classe Container.
     *
     * Initialise le conteneur parent avec la configuration de production.
     *
     * @param LocaleContext $localeContext Le contexte de la locale (langue, région).
     */
    public function __construct(LocaleContext $localeContext)
    {
        $dirname = dirname(__DIR__);
        parent::__construct(
            $dirname . '/' . LOG_FILE_DEFAULT,
            ENVIRONMENT_PROD,
            $dirname . '/templates',
            Level::Debug,
            $localeContext
        );
    }

}
