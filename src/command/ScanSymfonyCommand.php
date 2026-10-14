<?php
declare(strict_types=1);

namespace App\command;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Classe ScanSymfonyCommand
 *
 * Commande standard de la console Symfony pour scanner les projets GitLab.
 */
class ScanSymfonyCommand extends Command
{
    protected static string $defaultName = 'ScanCommand';
    protected static string $defaultDescription = 'Scan des projets GitLab';

    public function __construct(
        private readonly ScanCommand $scanCommand
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('ScanCommand')
             ->setDescription('Scan des projets GitLab')
             ->addOption('sf', 's', InputOption::VALUE_OPTIONAL, 'Système fonctionnel (SF) à scanner sélectivement', null);
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $arrInput = [];
        $sf = $input->getOption('sf');
        if ($sf !== null && trim((string)$sf) !== '') {
            $arrInput['sf'] = trim((string)$sf);
        }

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
