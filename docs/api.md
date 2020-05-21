# API Functions

## GiftAid

### Update Eligible Contributions
GiftAid.updateeligiblecontributions - API to set "Eligible for Gift Aid" on all contributions where
it was not previously set.

This allows to to "fix" any contributions that were created with an older version of the extension.

This will not touch contributions already in a batch.

Note that a contribution's eligibility is based on the financial types of the line items (unless globally enabled) only; it is not based on declaration of the donor: eligibility of a contribution does not mean it is claimable.

#### Parameters
- `contribution_id` (int): Contribution ID: Optional contribution ID to update.
- `limit` (int): Limit number to process in one go: If there are a lot of contributions this can be quite intensive. Optionally limit the number to process in a batch and run this API call multiple times.
- `recalculate_amount` (bool): Recalculate amounts:
   - If missing/false (default), only calculate amounts (and eligibility) for contributions that do not have eligibility known.
   - If true, recalculate Gift Aid amounts (but not eligibility) for contributions that have the eligible flag set.
