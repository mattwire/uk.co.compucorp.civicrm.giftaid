<?php

function civicrm_api3_gift_aid_makepastyearsubmissions($params) {
  // get all contacts with declarations
  $contacts = CRM_Civigiftaid_Declaration::getContactsWithDeclarations();

  // get current declarations of contacts
  $currentDeclarations = CRM_Civigiftaid_Declaration::getCurrentDeclarations($contacts);

  // get current declarations which has "past 4 year" option
  $currentWithPastYearOption = [];
  $contactsIds = [];
  foreach($currentDeclarations as $currentDeclaration) {
    if($currentDeclaration['eligible_for_gift_aid'] == 3){
      $currentWithPastYearOption[] = $currentDeclaration;
    }
  }

  // select all contributions which are not submissions and with eligible financial type
  $contributionsToSubmit =
    CRM_Civigiftaid_Utils_Contribution::getContributionsByDeclarations($currentWithPastYearOption, 100);

  // make submissions
  if(!empty($contributionsToSubmit)) {
    foreach($contributionsToSubmit as $contribution) {
      $submission['entity_id'] = $contribution['contribution_id'];
      $submission['eligible_for_gift_aid'] = CRM_Civigiftaid_Declaration::DECLARATION_IS_PAST_4_YEARS;

      CRM_Civigiftaid_Utils_Contribution::setSubmission($submission);
    }

  }

  return civicrm_api3_create_success(1, $params);
}

function _civicrm_api3_gift_aid_updateeligiblecontributions_spec($params) {
  $params['contribution_id']['title'] = 'Contribution ID';
  $params['contribution_id']['type'] = CRM_Utils_Type::T_INT;
}

function civicrm_api3_gift_aid_updateeligiblecontributions($params) {
  $groupID = civicrm_api3('CustomGroup', 'getvalue', [
    'return' => "id",
    'name' => "gift_aid",
  ]);
  $contributionParams = [
    'return' => [
      'id',
      'contact_id',
      'contribution_status_id',
      'receive_date',
      CRM_Civigiftaid_Utils::getCustomByName('batch_name', $groupID),
      CRM_Civigiftaid_Utils::getCustomByName('Eligible_for_Gift_Aid', $groupID)
    ],
    CRM_Civigiftaid_Utils::getCustomByName('Eligible_for_Gift_Aid', $groupID) => ['IS NULL' => 1],
    'options' => ['limit' => 0],
  ];
  if (!empty($params['contribution_id'])) {
    $contributionParams['id'] = $params['contribution_id'];
  }
  $contributions = civicrm_api3('Contribution', 'get', $contributionParams);
  if (empty($contributions['count'])) {
    return civicrm_api3_create_error('No contributions found or all have Eligible flag set!');
  }
  $contributions = $contributions['values'];

  $updatedIDs = [];
  foreach ($contributions as $key => $contribution) {
    $contact = civicrm_api3('Contact', 'getsingle', [
      'return' => ["contact_type"],
      'id' => $contribution['contact_id'],
    ]);
    if ($contact['contact_type'] !== 'Individual') {
      unset($contributions[$key]);
      continue;
    }
    if (!CRM_Civigiftaid_Utils_Contribution::isEligibleForGiftAid($contribution)) {
      unset($contributions[$key]);
      continue;
    }

    // This must be an eligible contribution - update it
    // We don't touch batchName
    CRM_Civigiftaid_Utils_Contribution::updateGiftAidFields($contribution['id'], CRM_Civigiftaid_Declaration::DECLARATION_IS_YES, NULL);
    $updatedIDs[$contribution['id']] = 1;
  }
  return civicrm_api3_create_success($updatedIDs, $params, 'gift_aid', 'updateeligiblecontributions');
}
