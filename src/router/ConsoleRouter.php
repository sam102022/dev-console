<?php
declare(strict_types=1);

namespace App\router;

use App\command\ScanSymfonyCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Classe ConsoleRouter
 *
 * Point d'entrée et routeur pour les commandes exécutées en ligne de commande (CLI) utilisant Symfony Console.
 */
class ConsoleRouter
{
    public function __construct(
        private readonly ContainerInterface $container
    ) {}

    /**
     * Distribue la requête de la console au composant Symfony Console.
     *
     * @param array $argv Les arguments de la ligne de commande.
     */
    public function dispatch(array $argv): void
    {
        $application = new Application('Dev Console CLI', '1.0');
        $application->setAutoExit(false); // Permet d'éviter exit() durant les tests PHPUnit

        // Récupère et ajoute la commande standard Symfony depuis le conteneur
        $scanCommand = $this->container->get(ScanSymfonyCommand::class);
        $application->add($scanCommand);

        $application->run(new ArgvInput($argv), new ConsoleOutput());
    }
}
