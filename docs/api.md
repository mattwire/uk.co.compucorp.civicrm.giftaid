# API Functions

## GiftAid

### Update Eligible Contributions
[GiftAid.updateeligiblecontributions](../api.md) - API to set "Eligible for Gift Aid" on all contributions where
it was not previously set.

This allows to to "fix" any contributions that were created with an older version of the extension.

This will not touch contributions already in a batch.

##### Parameters
- `contribution_id` (int): Contribution ID: Optional contribution ID to update.
- `limit` (int): Limit number to process in one go: If there are a lot of contributions this can be quite intensive. Optionally limit the number to process in a batch and run this API call multiple times.
- `recalculate_amount` (bool): Recalculate amounts: Recalculate Gift Aid amounts even if they already have the eligible flag set.
