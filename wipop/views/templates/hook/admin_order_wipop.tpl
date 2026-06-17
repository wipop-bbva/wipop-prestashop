{if isset($wipopPayment)}
  {if $wipopPayment.native_refunds_disabled}
    <style>
      .standard-refund-display,
      .partial-refund-display,
      .return-product-display,
      .cancel-product-element {
        display: none !important;
      }
    </style>
  {/if}

  <div class="card">
    <h3 class="card-header">
      {l s='Wipop' d='Modules.Wipop.Admin'}
    </h3>
    <div class="card-body">
      <table class="table table-sm mb-0">
        <tbody>
          <tr>
            <th scope="row">{l s='Payment status' d='Modules.Wipop.Admin'}</th>
            <td>
              <span class="badge badge-{$wipopPayment.status_badge_class|escape:'html':'UTF-8'}">
                {$wipopPayment.status_label|escape:'html':'UTF-8'}
              </span>
            </td>
          </tr>
          <tr>
            <th scope="row">{l s='Wipop status' d='Modules.Wipop.Admin'}</th>
            <td>{$wipopPayment.remote_status|escape:'html':'UTF-8'}</td>
          </tr>
          <tr>
            <th scope="row">{l s='Method' d='Modules.Wipop.Admin'}</th>
            <td>{$wipopPayment.payment_method|escape:'html':'UTF-8'}</td>
          </tr>
          <tr>
            <th scope="row">{l s='Amount' d='Modules.Wipop.Admin'}</th>
            <td>{$wipopPayment.amount|escape:'html':'UTF-8'}</td>
          </tr>
          <tr>
            <th scope="row">{l s='Wipop order' d='Modules.Wipop.Admin'}</th>
            <td>{$wipopPayment.wipop_order_id|escape:'html':'UTF-8'}</td>
          </tr>
          <tr>
            <th scope="row">{l s='Transaction' d='Modules.Wipop.Admin'}</th>
            <td>{$wipopPayment.wipop_transaction_id|escape:'html':'UTF-8'}</td>
          </tr>
          <tr>
            <th scope="row">{l s='Last update' d='Modules.Wipop.Admin'}</th>
            <td>{$wipopPayment.webhook_received_at|escape:'html':'UTF-8'}</td>
          </tr>
        </tbody>
      </table>

      {if $wipopPayment.has_error}
        <div class="alert alert-warning mt-2 mb-0">
          {$wipopPayment.remote_error|escape:'html':'UTF-8'}
        </div>
      {/if}

      {if $wipopPayment.native_refunds_disabled}
        <div class="alert alert-info mt-2 mb-0">
          {l s='Wipop manages refunds for this payment.' d='Modules.Wipop.Admin'}
        </div>
      {/if}

      {if $wipopPayment.can_manage_preauthorization}
        <hr>
        <div class="d-flex flex-column">
          <form method="post" action="{$wipopPayment.preauthorization_capture_url|escape:'html':'UTF-8'}" class="mb-2">
            <input type="hidden" name="_csrf_token" value="{$wipopPayment.preauthorization_csrf_token|escape:'html':'UTF-8'}">
            <button type="submit" class="btn btn-primary btn-sm btn-block">
              {l s='Capture preauthorization with Wipop' d='Modules.Wipop.Admin'}
            </button>
          </form>
          <form method="post" action="{$wipopPayment.preauthorization_reverse_url|escape:'html':'UTF-8'}">
            <input type="hidden" name="_csrf_token" value="{$wipopPayment.preauthorization_csrf_token|escape:'html':'UTF-8'}">
            <button type="submit" class="btn btn-outline-danger btn-sm btn-block">
              {l s='Void preauthorization with Wipop' d='Modules.Wipop.Admin'}
            </button>
          </form>
        </div>
      {/if}

      {if $wipopPayment.has_preauthorization_events}
        <hr>
        <table class="table table-sm mb-0">
          <thead>
            <tr>
              <th>{l s='Event' d='Modules.Wipop.Admin'}</th>
              <th>{l s='Status' d='Modules.Wipop.Admin'}</th>
              <th>{l s='Amount' d='Modules.Wipop.Admin'}</th>
              <th>{l s='Transaction' d='Modules.Wipop.Admin'}</th>
              <th>{l s='Date' d='Modules.Wipop.Admin'}</th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$wipopPayment.preauthorization_events item=event}
              <tr>
                <td>{$event.type|escape:'html':'UTF-8'}</td>
                <td>{$event.status|escape:'html':'UTF-8'}</td>
                <td>{$event.amount|escape:'html':'UTF-8'}</td>
                <td>{$event.transaction_id|escape:'html':'UTF-8'}</td>
                <td>{$event.received_at|escape:'html':'UTF-8'}</td>
              </tr>
              {if $event.has_error}
                <tr>
                  <td colspan="5">
                    <div class="alert alert-warning mb-0">
                      {$event.error|escape:'html':'UTF-8'}
                    </div>
                  </td>
                </tr>
              {/if}
            {/foreach}
          </tbody>
        </table>
      {/if}

      {if $wipopPayment.has_refund}
        <hr>
        <table class="table table-sm mb-0">
          <tbody>
            <tr>
              <th scope="row">{l s='Refund status' d='Modules.Wipop.Admin'}</th>
              <td>{$wipopPayment.refund_status|escape:'html':'UTF-8'}</td>
            </tr>
            <tr>
              <th scope="row">{l s='Refunded amount' d='Modules.Wipop.Admin'}</th>
              <td>{$wipopPayment.refunded_amount|escape:'html':'UTF-8'}</td>
            </tr>
            <tr>
              <th scope="row">{l s='Last refund' d='Modules.Wipop.Admin'}</th>
              <td>{$wipopPayment.last_refund_transaction_id|escape:'html':'UTF-8'}</td>
            </tr>
            <tr>
              <th scope="row">{l s='Last refund amount' d='Modules.Wipop.Admin'}</th>
              <td>{$wipopPayment.last_refund_amount|escape:'html':'UTF-8'}</td>
            </tr>
            <tr>
              <th scope="row">{l s='Last refund status' d='Modules.Wipop.Admin'}</th>
              <td>{$wipopPayment.last_refund_status|escape:'html':'UTF-8'}</td>
            </tr>
            <tr>
              <th scope="row">{l s='Last refund update' d='Modules.Wipop.Admin'}</th>
              <td>{$wipopPayment.last_refund_received_at|escape:'html':'UTF-8'}</td>
            </tr>
            {if $wipopPayment.has_refund_order_slip}
              <tr>
                <th scope="row">{l s='Credit slip' d='Modules.Wipop.Admin'}</th>
                <td>#{$wipopPayment.last_refund_order_slip_id|escape:'html':'UTF-8'}</td>
              </tr>
            {/if}
          </tbody>
        </table>

        {if $wipopPayment.has_refund_error}
          <div class="alert alert-warning mt-2 mb-0">
            {$wipopPayment.last_refund_error|escape:'html':'UTF-8'}
          </div>
        {/if}
      {/if}

      {if isset($wipopPayment.recurring) && $wipopPayment.recurring.has_recurring}
        <hr>
        <table class="table table-sm mb-0">
          <tbody>
            <tr>
              <th scope="row">{l s='Recurring payment' d='Modules.Wipop.Admin'}</th>
              <td>
                <span class="badge badge-{$wipopPayment.recurring.status_badge_class|escape:'html':'UTF-8'}">
                  {$wipopPayment.recurring.status_label|escape:'html':'UTF-8'}
                </span>
              </td>
            </tr>
            <tr>
              <th scope="row">{l s='Period' d='Modules.Wipop.Admin'}</th>
              <td>{$wipopPayment.recurring.period|escape:'html':'UTF-8'}</td>
            </tr>
            <tr>
              <th scope="row">{l s='Recurring amount' d='Modules.Wipop.Admin'}</th>
              <td>{$wipopPayment.recurring.amount|escape:'html':'UTF-8'}</td>
            </tr>
            <tr>
              <th scope="row">{l s='Next charge' d='Modules.Wipop.Admin'}</th>
              <td>{$wipopPayment.recurring.next_attempt_at|escape:'html':'UTF-8'}</td>
            </tr>
            <tr>
              <th scope="row">{l s='Next sequence' d='Modules.Wipop.Admin'}</th>
              <td>{$wipopPayment.recurring.next_sequence|escape:'html':'UTF-8'}</td>
            </tr>
            <tr>
              <th scope="row">{l s='Last recurring transaction' d='Modules.Wipop.Admin'}</th>
              <td>{$wipopPayment.recurring.last_transaction_id|escape:'html':'UTF-8'}</td>
            </tr>
          </tbody>
        </table>

        {if $wipopPayment.recurring.can_stop}
          <form method="post" action="{$wipopPayment.recurring.stop_url|escape:'html':'UTF-8'}" class="mt-2">
            <input type="hidden" name="_csrf_token" value="{$wipopPayment.recurring.csrf_token|escape:'html':'UTF-8'}">
            <button type="submit" class="btn btn-outline-danger btn-sm btn-block">
              {l s='Stop recurring payments' d='Modules.Wipop.Admin'}
            </button>
          </form>
        {/if}
      {/if}

      <p class="text-muted small mt-2 mb-0">
        {l s='Attempt' d='Modules.Wipop.Admin'} #{$wipopPayment.attempt_id|escape:'html':'UTF-8'}
        / {l s='Cart' d='Modules.Wipop.Admin'} #{$wipopPayment.cart_id|escape:'html':'UTF-8'}
      </p>
    </div>
  </div>
{/if}
