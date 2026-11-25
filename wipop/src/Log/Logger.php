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

namespace PrestaShop\Module\Wipop\Log;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class Logger implements LoggerInterface
{
    /**
     * @param array<string, int|string> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(1, $message, $context);
    }

    /**
     * @param array<string, int|string> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(2, $message, $context);
    }

    /**
     * @param array<string, int|string> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(3, $message, $context);
    }

    /**
     * @param array<string, int|string> $context
     */
    private function log(int $severity, string $message, array $context): void
    {
        $formattedMessage = '[wipop] ' . $message;

        if ($context !== []) {
            $formattedContext = json_encode($context);
            if (is_string($formattedContext)) {
                $formattedMessage .= ' ' . $formattedContext;
            }
        }

        \PrestaShopLogger::addLog($formattedMessage, $severity, null, 'Wipop');
    }
}
