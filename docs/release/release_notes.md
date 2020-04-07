## Release 3.3.3

* Include first donation in the batch
* Due to the timestamp on the declaration is created after the contribution hence the first donation doesn't gets included in batch. Set the timestamp as the date rather than time.
* Clear batch_name if we created a new contribution in a recur series (it's copied across by default by Contribution.repeattransaction).
* Check and set label for 'Eligible amount' field on contribution.
* Always make sure current declaration is set if we have one - fixes issue with overwriting declaration with 'No'.
* Fix [#5](https://github.com/mattwire/uk.co.compucorp.civicrm.giftaid/issues/5) Donations included in batch although financial types disabled in settings.
* Trigger create of new gift aid declaration from contribution form if required.

## Release 3.3.2

* Handle transitions between the 3 declaration states without losing information - create a new declaration when state is changed.
* Refactor creating/updating declaration when contribution is created/updated.
* Properly escape SQL parameters when updating gift aid declaration.
* Extract code to check if charity column exists.

## Release 3.3.1

* Major performance improvement to "Add to Batch".

## Release 3.3
**In this release we update profiles to use the declaration eligibility field instead of the contribution.
This allows us to create a new declaration (as it will be the user filling in a profile via contribution page etc.)
 and means we don't create a declaration when time a contribution is created / imported with the "eligible" flag set to Yes.**

**IMPORTANT: Make sure you run the extension upgrades (3104).**

* Fix status message on AddToBatch.
* Fix crash on enable/disable extension.
* Fix creating declarations every time we update a contribution.
* Refactor insert/updateDeclaration.
* Refactor loading of optiongroups/values - we load them in the upgrader in PHP meaning that we always ensure they are up to date with the latest extension.
* Add documentation in mkdocs format (just extracted from README for now).
* Make sure we properly handle creating/ending and creating a declaration again (via eg. contribution page).
* Allow for both declaration eligibility and individual contribution eligibility to be different on same profile (add both fields).
* Fix PHP notice in GiftAid report.
* Match on OptionValue value when running upgrader as name is not always consistent.

## Release 3.2
* Be stricter checking eligible_for_gift_aid variable type
* Fix issues with entity definition and regenerate
* Fix PHP notice
* Refactor addtobatch for performance, refactor upgrader for reliability
* Add API to update the eligible_for_gift_aid flag on contributions

## Release 3.1
* Be stricter checking eligible_for_gift_aid variable type


