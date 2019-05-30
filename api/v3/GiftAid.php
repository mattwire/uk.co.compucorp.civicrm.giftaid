<?php

function civicrm_api3_gift_aid_makepastyearsubmissions($params) {
  // get all contacts with declarations
  $contacts = CRM_Civigiftaid_Utils_GiftAid::getContactsWithDeclarations();
  
  // get current declarations of contacts
  $currentDeclarations = CRM_Civigiftaid_Utils_GiftAid::getCurrentDeclarations($contacts);

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
          CRM_Civigiftaid_Utils_GiftAid::getContributionsByDeclarations($currentWithPastYearOption, 100);

  // make submissions
  if(!empty($contributionsToSubmit)) {
    foreach($contributionsToSubmit as $contribution) {
      $submission['entity_id'] = $contribution['contribution_id'];
      $submission['eligible_for_gift_aid'] = 3;

      CRM_Civigiftaid_Utils_GiftAid::setSubmission($submission);
    }

  }
  
  return civicrm_api3_create_success(1, $params);
}
