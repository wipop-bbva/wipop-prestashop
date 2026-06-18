{**
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
 *}

<section class="wipop-saved-cards-page">
  <h1 class="h3">{l s='Saved cards' d='Modules.Wipop.Shop'}</h1>

  {if empty($wipopSavedCards.cards)}
    <p>{l s='You do not have any saved cards yet.' d='Modules.Wipop.Shop'}</p>
  {else}
    <div class="card">
      <div class="card-block">
        {foreach from=$wipopSavedCards.cards item=card}
          <div class="wipop-saved-card d-flex justify-content-between align-items-center mb-2">
            <div>
              <strong>{$card.label|escape:'html':'UTF-8'}</strong>
              {if $card.expiry}
                <span class="text-muted">
                  {l s='Expires' d='Modules.Wipop.Shop'} {$card.expiry|escape:'html':'UTF-8'}
                </span>
              {/if}
            </div>
            <form method="POST" action="{$wipopSavedCards.action|escape:'html':'UTF-8'}">
              <input type="hidden" name="token" value="{$wipopSavedCards.token|escape:'html':'UTF-8'}">
              <input type="hidden" name="id_wipop_stored_card" value="{$card.id|intval}">
              <button class="btn btn-outline-secondary btn-sm" type="submit" name="forget_wipop_card" value="1">
                {l s='Forget card' d='Modules.Wipop.Shop'}
              </button>
            </form>
          </div>
        {/foreach}
      </div>
    </div>
  {/if}
</section>
