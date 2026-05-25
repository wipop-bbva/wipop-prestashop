{*
* 2007-2025 PrestaShop
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
*  @copyright 2007-2025 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

{if isset($wipopPaymentReturn)}
  <p>{l s='Your Wipop payment has been confirmed.' d='Modules.Wipop.Shop'}</p>
  <ul>
    <li>
      {l s='Order reference:' d='Modules.Wipop.Shop'}
      <strong>{$wipopPaymentReturn.order_reference|escape:'html':'UTF-8'}</strong>
    </li>
    <li>
      {l s='Amount:' d='Modules.Wipop.Shop'}
      <span class="price">{$wipopPaymentReturn.amount|escape:'html':'UTF-8'}</span>
    </li>
    {if $wipopPaymentReturn.transaction_id}
      <li>
        {l s='Transaction:' d='Modules.Wipop.Shop'}
        <strong>{$wipopPaymentReturn.transaction_id|escape:'html':'UTF-8'}</strong>
      </li>
    {/if}
  </ul>
{/if}
