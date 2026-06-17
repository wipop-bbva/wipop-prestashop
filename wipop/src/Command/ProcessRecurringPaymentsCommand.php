<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Command;

use PrestaShop\Module\Wipop\Recurring\RecurringDueChargeRunnerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ProcessRecurringPaymentsCommand extends Command
{
    protected static $defaultName = 'wipop:recurring:process';

    public function __construct(private RecurringDueChargeRunnerInterface $runner)
    {
        parent::__construct(self::$defaultName);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Processes due Wipop recurring payments.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum subscriptions to process.', 50);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = max(1, (int) $input->getOption('limit'));
        $processed = $this->runner->runDue($limit);

        $output->writeln(sprintf('Processed %d Wipop recurring payment(s).', $processed));

        return 0;
    }
}
