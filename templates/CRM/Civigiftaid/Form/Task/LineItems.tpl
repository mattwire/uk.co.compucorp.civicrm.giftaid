{*
 * https://civicrm.org/license
 *}

{if $row.line_items}
    <table class="line-items" id="line-items-{$contributionId}">
        <tr>
            <th>Item</th>
            <th>Quantity</th>
            <th>Type</th>
            <th>Description</th>
            <th>Total Amount</th>
        </tr>
        {assign var="count" value=0}
        {foreach from=$row.line_items item=item}
            <tr {if $count % 2 !== 0 }class="odd"{/if}>
                <td>{$item.item}</td>
                <td>{$item.qty}</td>
                <td>{$item.financial_type}</td>
                <td>{$item.description}</td>
                <td>{$item.amount}</td>
            </tr>
            {assign var="count" value=$count+1}
        {/foreach}
    </table>
{else}
    <div class="line-items" id="line-items-{$contributionId}">
        <p>No gift aidable line items found for the contribution.</p>
    </div>
{/if}