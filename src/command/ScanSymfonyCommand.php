<?php
declare(strict_types=1);

namespace App\command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Classe ScanSymfonyCommand
 *
 * Commande standard de la console Symfony pour scanner les projets GitLab.
 */
class ScanSymfonyCommand extends Command
{
    protected static $defaultName = 'ScanCommand';
    protected static $defaultDescription = 'Scan des projets GitLab';

    public function __construct(
        private readonly ScanCommand $scanCommand
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('ScanCommand')
             ->setDescription('Scan des projets GitLab');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $arrInput = [];
        $arrOutput = [];
        $this->scanCommand->execute($arrInput, $arrOutput);

        if ($arrOutput['result'] === ScanCommand::RESULT_OK) {
            $output->writeln('<info>Scan terminé avec succès !</info>');
            return Command::SUCCESS;
        }

        $output->writeln('<error>Le scan a échoué.</error>');
        return Command::FAILURE;
    }
}
