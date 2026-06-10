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

<form method="POST" action="{$wipopCardForm.action nofilter}">
  <input type="hidden" name="token" value="{$wipopCardForm.token|escape:'html':'UTF-8'}">

  {if $wipopCardForm.is_recurring}
    {if $wipopCardForm.requires_login}
      <div class="alert alert-warning mb-2">
        {l s='Log in to pay for recurring products with Wipop.' d='Modules.Wipop.Shop'}
      </div>
    {else}
      <input type="hidden" name="wipop_save_card" value="1">
    {/if}
  {/if}

  {if $wipopCardForm.is_logged}
    {if !empty($wipopCardForm.saved_cards)}
      <div class="wipop-saved-cards">
        <p class="mb-1">{l s='Use a saved card' d='Modules.Wipop.Shop'}</p>
        <label class="d-block">
          <input type="radio" name="wipop_saved_card_id" value="0" checked>
          {l s='Use a new card' d='Modules.Wipop.Shop'}
        </label>
        {foreach from=$wipopCardForm.saved_cards item=card}
          <label class="d-block">
            <input type="radio" name="wipop_saved_card_id" value="{$card.id|intval}">
            {$card.label|escape:'html':'UTF-8'}
            {if $card.expiry}
              <small class="text-muted">
                {l s='Expires' d='Modules.Wipop.Shop'} {$card.expiry|escape:'html':'UTF-8'}
              </small>
            {/if}
          </label>
        {/foreach}
      </div>
    {/if}

    {if !$wipopCardForm.is_recurring}
      <label class="d-block mt-2">
        <input type="checkbox" name="wipop_save_card" value="1">
        {l s='Remember this card for future payments' d='Modules.Wipop.Shop'}
      </label>
    {/if}
  {/if}
</form>
