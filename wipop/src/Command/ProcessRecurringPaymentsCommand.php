<?php
/**
 * 2007-2026 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2026 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Command;

use PrestaShop\Module\Wipop\Recurring\RecurringDueChargeRunnerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

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
