{if isset($wipopPayment)}
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
            <th scope="row">{l s='Remote status' d='Modules.Wipop.Admin'}</th>
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
            <th scope="row">{l s='Last webhook' d='Modules.Wipop.Admin'}</th>
            <td>{$wipopPayment.webhook_received_at|escape:'html':'UTF-8'}</td>
          </tr>
        </tbody>
      </table>

      {if $wipopPayment.has_error}
        <div class="alert alert-warning mt-2 mb-0">
          {$wipopPayment.remote_error|escape:'html':'UTF-8'}
        </div>
      {/if}

      <p class="text-muted small mt-2 mb-0">
        {l s='Attempt' d='Modules.Wipop.Admin'} #{$wipopPayment.attempt_id|escape:'html':'UTF-8'}
        / {l s='Cart' d='Modules.Wipop.Admin'} #{$wipopPayment.cart_id|escape:'html':'UTF-8'}
      </p>
    </div>
  </div>
{/if}
