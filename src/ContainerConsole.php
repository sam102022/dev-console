<?php
declare(strict_types=1);

namespace App;

use App\context\LocaleContext;
use Monolog\Level;

/**
 * Classe ContainerConsole
 *
 * Conteneur d'injection de dépendances pour l'environnement de la console (CLI).
 * Cette classe étend AbstractContainer et le configure avec les paramètres
 * spécifiques à la console (fichier de log CLI, etc.).
 */
final class ContainerConsole extends AbstractContainer
{
    /**
     * Constructeur de la classe ContainerConsole.
     *
     * Initialise le conteneur parent avec la configuration pour la console.
     *
     * @param LocaleContext $localeContext Le contexte de la locale (langue, région).
     */
    public function __construct(LocaleContext $localeContext)
    {
        $dirname = dirname(__DIR__);
        parent::__construct(
            $dirname . '/' . LOG_CLI_FILE_DEFAULT,
            ENVIRONMENT_PROD,
            $dirname . '/templates',
            Level::Info,
            $localeContext
        );
    }

}
