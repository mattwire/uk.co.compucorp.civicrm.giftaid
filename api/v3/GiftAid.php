<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

/**
 * @param array $params
 */
function _civicrm_api3_gift_aid_updateeligiblecontributions_spec(&$params) {
  $params['contribution_id']['title'] = 'Contribution ID';
  $params['contribution_id']['description'] = 'Optional contribution ID to update';
  $params['contribution_id']['type'] = CRM_Utils_Type::T_INT;
  $params['contribution_id']['api.required'] = FALSE;
  $params['limit'] = [
    'type' => CRM_Utils_Type::T_INT,
    'title' => ts('Limit number to process in one go'),
    'description' => 'If there are a lot of contributions this can be quite intensive. Optionally limit the number to process in a batch and run this API call multiple times',
  ];
  $params['recalculate_amount']['title'] = 'Recalculate amounts';
  $params['recalculate_amount']['description'] = 'Recalculate Gift Aid amounts even if they already have the eligible flag set. This will not touch contributions already in a batch.';
  $params['recalculate_amount']['type'] = CRM_Utils_Type::T_BOOLEAN;
}

/**
 * @param array $params
 *
 * @return array
 * @throws \CRM_Extension_Exception
 * @throws \CiviCRM_API3_Exception
 */
function civicrm_api3_gift_aid_updateeligiblecontributions($params) {
  $contributionParams = [
    'return' => [
      'id',
      'contact_id',
      'contribution_status_id',
      'receive_date',
      CRM_Civigiftaid_Utils::getCustomByName('batch_name', 'Gift_Aid'),
      CRM_Civigiftaid_Utils::getCustomByName('Eligible_for_Gift_Aid', 'Gift_Aid')
    ],
    'options' => ['limit' => $params['limit'] ?? 0],
  ];
  if (empty($params['recalculate_amount'])) {
    // Only retrieve contributions that do not have eligibility set
    $contributionParams[CRM_Civigiftaid_Utils::getCustomByName('Eligible_for_Gift_Aid', 'Gift_Aid')] = ['IS NULL' => 1];
  }
  else {
    // Retrieve all contributions that are eligible for gift aid
    $contributionParams[CRM_Civigiftaid_Utils::getCustomByName('Eligible_for_Gift_Aid', 'Gift_Aid')] = 1;
  }
  if (!empty($params['contribution_id'])) {
    $contributionParams['id'] = $params['contribution_id'];
  }
  $contributions = civicrm_api3('Contribution', 'get', $contributionParams)['values'];
  if (empty($contributions)) {
    return civicrm_api3_create_error('No contributions found or all have Eligible flag set!');
  }

  foreach ($contributions as $contributionID => $contributionDetail) {
    // Check batch name here because it may be NULL or empty string and we can't check that using API3.
    if (!empty($contributionDetail[CRM_Civigiftaid_Utils::getCustomByName('batch_name', 'Gift_Aid')])) {
      continue;
    }
    CRM_Civigiftaid_SetContributionGiftAidEligibility::setGiftAidEligibilityStatus($contributionID);
    $updatedIDs[] = $contributionID;
  }

  return civicrm_api3_create_success($updatedIDs ?? [], $params, 'GiftAid', 'updateeligiblecontributions');
}
