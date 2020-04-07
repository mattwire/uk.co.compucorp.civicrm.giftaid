<?php

require_once 'civigiftaid.civix.php';
use CRM_Civigiftaid_ExtensionUtil as E;

define('CIVICRM_GIFTAID_ADD_TASKID', 1435);
define('CIVICRM_GIFTAID_REMOVE_TASKID', 1436);

/**
 * Implementation of hook_civicrm_config
 */
function civigiftaid_civicrm_config(&$config) {
  _civigiftaid_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function civigiftaid_civicrm_xmlMenu(&$files) {
  _civigiftaid_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function civigiftaid_civicrm_install() {
  _civigiftaid_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function civigiftaid_civicrm_uninstall() {
  _civigiftaid_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function civigiftaid_civicrm_enable() {
  _civigiftaid_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function civigiftaid_civicrm_disable() {
  _civigiftaid_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op    string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function civigiftaid_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _civigiftaid_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function civigiftaid_civicrm_managed(&$entities) {
  _civigiftaid_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function civigiftaid_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _civigiftaid_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * @param $objectType
 * @param $tasks
 */
function civigiftaid_civicrm_searchTasks($objectType, &$tasks) {
  if ($objectType == 'contribution') {
    $tasks[CIVICRM_GIFTAID_ADD_TASKID] = [
      'title'  => E::ts('Add to Gift Aid batch'),
      'class'  => 'CRM_Civigiftaid_Form_Task_AddToBatch',
      'result' => FALSE
    ];
    $tasks[CIVICRM_GIFTAID_REMOVE_TASKID] = [
      'title'  => E::ts('Remove from Gift Aid batch'),
      'class'  => 'CRM_Civigiftaid_Form_Task_RemoveFromBatch',
      'result' => FALSE
    ];
  }
}

/**
 * Intercept form functions
 */
function civigiftaid_civicrm_buildForm($formName, &$form) {
  switch ($formName) {
    case 'CRM_Civigiftaid_Form_Settings':
      CRM_Core_Resources::singleton()
        ->addScriptFile(E::LONG_NAME, 'resources/js/settings.js', 1, 'html-header');
      break;

    case 'CRM_Civigiftaid_Form_Task_AddToBatch':
    case 'CRM_Civigiftaid_Form_Task_RemoveFromBatch':
    CRM_Core_Resources::singleton()
      ->addScriptFile(E::LONG_NAME, 'resources/js/batch.js', 1, 'html-header')
      ->addStyleFile(E::LONG_NAME, 'resources/css/batch.css', 1, 'html-header');
      break;
  }
}

/**
 * Implementation of hook_civicrm_postProcess
 * To copy the contact's home address to the declaration, when the declaration is created
 * Only for offline contribution
 */
function civigiftaid_civicrm_postProcess($formName, &$form) {
  // Get and store the gift aid declaration value if set for use in civigiftaid_update_declaration_amount
  if (!isset(Civi::$statics[E::LONG_NAME]['uktaxpayer'])) {
    $ukTaxPayerField = CRM_Civigiftaid_Utils::getCustomByName('eligible_for_gift_aid', 'Gift_Aid_Declaration');
    if (isset($form->_submitValues[$ukTaxPayerField])) {
      Civi::$statics[E::LONG_NAME]['uktaxpayer'] = $form->_submitValues[$ukTaxPayerField];
    }
  }

  if ($formName != 'CRM_Contact_Form_CustomData') {
    return;
  }

  $groupID = $form->getVar('_groupID');
  $contactId = $form->getVar('_entityId');
  $customGroupTableName = civicrm_api3('CustomGroup', 'getvalue', [
    'id' => $groupID,
    'return' => 'table_name',
  ]);

  if ($customGroupTableName == 'civicrm_value_gift_aid_declaration') {
    //FIXME: dirty hack to get the latest declaration for the contact
    $sql = "
      SELECT MAX(id) FROM {$customGroupTableName}
      WHERE entity_id = %1";
    $params = [1 => [$contactId, 'Integer']];
    $rowId = CRM_Core_DAO::singleValueQuery($sql, $params);

    // Get the home address of the contact
    list($addressDetails, $postCode) = _civigiftaid_civicrm_custom_get_address_and_postal_code($contactId, 1);

    $sql = "
      UPDATE {$customGroupTableName}
      SET  address = %1,
      post_code = %2
      WHERE  id = %3";

    CRM_Core_DAO::executeQuery(
      $sql,
      [
        1 => [$addressDetails, 'String'],
        2 => [$postCode, 'String'],
        3 => [$rowId, 'Integer'],
      ]
    );
  }
}

function civigiftaid_update_declaration_amount($contributionID, $op) {
  $contributionCustomGiftAidEligibleFieldName = CRM_Civigiftaid_Utils::getCustomByName('Eligible_for_Gift_Aid', 'Gift_Aid');
  $contributionCustomGiftAidBatchNameFieldName = CRM_Civigiftaid_Utils::getCustomByName('Batch_Name', 'Gift_Aid');

  $contribution = civicrm_api3('Contribution', 'getsingle', [
    'id' => $contributionID,
    'return' => ['contact_id',
      'receive_date',
      $contributionCustomGiftAidEligibleFieldName,
      $contributionCustomGiftAidBatchNameFieldName,
      'contribution_recur_id'
    ]
  ]);

  if (!empty($contribution['contribution_recur_id']) && ($op === 'create')) {
    $contribution[$contributionCustomGiftAidBatchNameFieldName] = '';
  }

  // Don't touch gift aid fields if already part of a batch
  if (!empty($contribution[$contributionCustomGiftAidBatchNameFieldName])) {
    return;
  }

  // If declaration updated via contribution page etc. it will have been set in postProcess
  if (isset(Civi::$statics[E::LONG_NAME]['uktaxpayer'])) {
    $contactGiftAidEligibleStatus = Civi::$statics[E::LONG_NAME]['uktaxpayer'];
  }
  else {
    $contactGiftAidEligibleStatus = CRM_Civigiftaid_Utils_GiftAid::isEligibleForGiftAid($contribution);
  }
  $contributionGiftAidEligibleStatus = $contribution[$contributionCustomGiftAidEligibleFieldName];

  // Get the gift aid eligible status
  // If it's not a valid number don't do any further processing
  if (!$contactGiftAidEligibleStatus) {
    return;
  }

  // We could specify both declaration + contribution fields on the profile, so you could sign a declaration but say this contribution is not eligible for example.
  if (!$contributionGiftAidEligibleStatus) {
    $contributionGiftAidEligibleStatus = $contactGiftAidEligibleStatus;
  }

  list($addressDetails, $postCode) = _civigiftaid_civicrm_custom_get_address_and_postal_code($contribution['contact_id'], 1);

  $params = [
    'entity_id'             => $contribution['contact_id'],
    'start_date'            => CRM_Utils_Date::isoToMysql($contribution['receive_date']),
    'address'               => $addressDetails,
    'post_code'             => $postCode,
  ];
  CRM_Civigiftaid_Utils_GiftAid::setDeclaration($params);
  if ($contactGiftAidEligibleStatus) {
    CRM_Civigiftaid_Utils_Contribution::updateGiftAidFields($contributionID, $contributionGiftAidEligibleStatus);
  }
}

/**
 * If a contribution is created/edited create/edit the slave contributions
 * @param $op
 * @param $objectName
 * @param $objectId
 * @param $objectRef
 *
 * @throws \CiviCRM_API3_Exception
 */
function civigiftaid_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  $hooks = [
    new CRM_Civigiftaid_Hook_Post_SetContributionGiftAidEligibility(),
  ];
  foreach ($hooks as $hook) {
    $hook->run($op, $objectName, $objectId, $objectRef);
  }

  switch ($objectName) {
    case 'Contribution':
      if ($op == 'edit' || $op == 'create') {

        $callbackParams = [
          'entity' => $objectName,
          'op' => $op,
          'id' => $objectId,
          'details' => $objectRef,
        ];
        if (CRM_Core_Transaction::isActive()) {
          CRM_Core_Transaction::addCallback(CRM_Core_Transaction::PHASE_POST_COMMIT, 'civigiftaid_callback_civicrm_post_contribution', [$callbackParams]);
        }
        else {
          civigiftaid_callback_civicrm_post_contribution($callbackParams);
        }
      }
      break;

  }
}

function civigiftaid_callback_civicrm_post_contribution($params) {
  if (isset(Civi::$statics[E::LONG_NAME]['updatedDeclarationAmount'])) {
    return;
  }
  Civi::$statics[E::LONG_NAME]['updatedDeclarationAmount'] = TRUE;
  civigiftaid_update_declaration_amount($params['id'], $params['op']);
}

/**
 * Implementation of hook_civicrm_validateForm
 * Validate set of Gift Aid declaration records on Individual,
 * from multi-value custom field edit form:
 * - check end > start,
 * - check for overlaps between declarations.
 */
function civigiftaid_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors) {
  $errors = [];

  if ($formName == 'CRM_Contact_Form_CustomData') {
    $groupID = $form->getVar('_groupID');
    $contactID = $form->getVar('_entityId');
    $tableName = civicrm_api3('CustomGroup', 'getvalue', [
      'return' => 'table_name',
      'id' => $groupID,
    ]);

    if ($tableName == 'civicrm_value_gift_aid_declaration') {
      // Assemble multi-value field values from custom_X_Y into
      // array $declarations of sets of values as column_name => value
      $columnNames = civicrm_api3('CustomField', 'get', [
        'return' => ["column_name"],
        'custom_group_id' => $groupID,
      ]);
      $columnNames = CRM_Utils_Array::collect('column_name', CRM_Utils_Array::value('values', $columnNames));

      $declarations = [];
      foreach ($fields as $name => $value) {
        if (preg_match('/^custom_(\d+)_(-?\d+)$/', $name, $matches)) {
          $columnName = CRM_Utils_Array::value($matches[1], $columnNames);
          if ($columnName) {
            $declarations[$matches[2]][$columnName]['value'] = $value;
            $declarations[$matches[2]][$columnName]['name'] = $name;
          }
        }
      }

      // Iterate through each distinct pair of declarations, checking for overlap.
      foreach ($declarations as $id1 => $values1) {
        $start1 = CRM_Utils_Date::processDate($values1['start_date']['value']);
        if ($values1['end_date']['value'] == '') {
          $end1 = '25000101000000';
        }
        else {
          $end1 = CRM_Utils_Date::processDate($values1['end_date']['value']);
        }

        if ($values1['end_date']['value'] != '' && $start1 >= $end1) {
          $errors[$values1['end_date']['name']] =
            'End date must be later than start date.';
          continue;
        }

        $charity1 = NULL;
        if (array_key_exists('charity', $values1)) {
          $charity1 = CRM_Utils_Array::value('value', $values1['charity']);
        }

        foreach ($declarations as $id2 => $values2) {
          $charity2 = NULL;
          if (array_key_exists('charity', $values2)) {
            $charity2 = CRM_Utils_Array::value('value', $values2['charity']);
          }
          if (($id2 <= $id1) || ($charity1 != $charity2)) {
            continue;
          }

          $start2 = CRM_Utils_Date::processDate(
            $values2['start_date']['value']
          );

          if ($values2['end_date']['value'] == '') {
            $end2 = '25000101000000';
          }
          else {
            $end2 = CRM_Utils_Date::processDate($values2['end_date']['value']);
          }

          if ($start1 < $end2 && $end1 > $start2) {
            $message = 'This declaration overlaps with the one from '
              . $values2['start_date']['value'];

            if ($values2['end_date']['value']) {
              $message .= ' to ' . $values2['end_date']['value'];
            }

            $errors[$values1['start_date']['name']] = $message;
            $message = 'This declaration overlaps with the one from '
              . $values1['start_date']['value'];

            if ($values1['end_date']['value']) {
              $message .= ' to ' . $values1['end_date']['value'];
            }

            $errors[$values2['start_date']['name']] = $message;
          }
        }
      }

      // Check if the contact has a home address
      foreach ($declarations as $id3 => $values3) {
        $address = civicrm_api3("Address", "get",
          [
            'contact_id'       => $contactID,
            'location_type_id' => 1
          ]
        );
        if ($address['count'] == 0) {
          $errors[$values3['eligible_for_gift_aid']['name']] =
            E::ts('You will not be able to create giftaid declaration because there is no home address recorded for this contact. If you want to create a declaration, please add home address for this contact.');
        }
      }
    }
  }

  if (!empty($errors)) {
    return $errors;
  }
}

/**
 * Add navigation for GiftAid under "Administer/CiviContribute" menu
 */
function civigiftaid_civicrm_navigationMenu(&$menu) {
  // Get optionvalue ID for basic rate tax setting
  $result = civicrm_api3('OptionValue', 'getsingle', ['name' => 'basic_rate_tax']);
  if ($result['id']) {
    $ovId = $result['id'];
    $ogId = $result['option_group_id'];
  }

  $item[] =  [
    'label' => E::ts('GiftAid'),
    'name'       => 'admin_giftaid',
    'url'        => NULL,
    'permission' => 'access CiviContribute',
    'operator'   => NULL,
    'separator'  => 1,
  ];
  _civigiftaid_civix_insert_navigation_menu($menu, 'Administer/CiviContribute', $item[0]);

  $item[] = [
    'label' => E::ts('GiftAid Basic Rate Tax'),
    'name'       => 'giftaid_basic_rate_tax',
    'url'        => "civicrm/admin/options?action=update&id=$ovId&gid=$ogId&reset=1",
    'permission' => 'access CiviContribute',
    'operator'   => NULL,
    'separator'  => NULL,
  ];
  _civigiftaid_civix_insert_navigation_menu($menu, 'Administer/CiviContribute/admin_giftaid', $item[1]);

  $item[] = [
    'label'      => E::ts('Settings'),
    'name'       => 'settings',
    'url'        => "civicrm/admin/giftaid/settings",
    'permission' => 'access CiviContribute',
    'operator'   => NULL,
    'separator'  => NULL,
  ];
  _civigiftaid_civix_insert_navigation_menu($menu, 'Administer/CiviContribute/admin_giftaid', $item[2]);

  _civigiftaid_civix_navigationMenu($menu);
}

/**
 * Function to get full address and postal code for a contact
 */
function _civigiftaid_civicrm_custom_get_address_and_postal_code($contactId, $location_type_id = 1) {
  if (empty($contactId)) {
    // @fixme Maybe this should throw an exception as it's unclear what happens if we don't have a contact ID here
    return ['', ''];
  }

  $fullFormattedAddress = $postalCode = '';

  // get Address & Postal Code of the contact
  $address = civicrm_api3("Address", "get", [
    'contact_id'       => $contactId,
    'location_type_id' => $location_type_id
  ]);
  if ($address['count'] > 0) {
    if (!isset($address['id'])) { //check if the contact has more than one home address so use the first one
      $addressValue = array_shift(array_values($address['values']));
      $postalCode = CRM_Utils_Array::value('postal_code', $addressValue, '');
    }
    else {
      $addressValue = $address['values'][$address['id']];
      $postalCode = CRM_Utils_Array::value('postal_code', $address['values'][$address['id']], '');
    }
    $fullFormattedAddress =
      _civigiftaid_civicrm_custom_get_address_and_postal_code_format_address($addressValue);

  }

  return [$fullFormattedAddress, $postalCode];
}

/**
 * Function to format the address , to avoid empty spaces or commas
 */
function _civigiftaid_civicrm_custom_get_address_and_postal_code_format_address(
  $contactAddress
) {
  if (!is_array($contactAddress)) {
    return 'NULL';
  }
  $tempAddressArray = [];
  if (isset($contactAddress['address_name'])
    AND $contactAddress['address_name']
  ) {
    $tempAddressArray[] = $contactAddress['address_name'];
  }
  if (isset($contactAddress['street_address'])
    AND $contactAddress['street_address']
  ) {
    $tempAddressArray[] = $contactAddress['street_address'];
  }
  if (isset($contactAddress['supplemental_address_1'])
    AND $contactAddress['supplemental_address_1']
  ) {
    $tempAddressArray[] = $contactAddress['supplemental_address_1'];
  }
  if (isset($contactAddress['supplemental_address_2'])
    AND $contactAddress['supplemental_address_2']
  ) {
    $tempAddressArray[] = $contactAddress['supplemental_address_2'];
  }
  if (isset($contactAddress['city']) AND $contactAddress['city']) {
    $tempAddressArray[] = $contactAddress['city'];
  }
  if (isset($contactAddress['state_province_id'])
    AND $contactAddress['state_province_id']
  ) {
    $tempAddressArray[] = CRM_Core_PseudoConstant::stateProvince(
      $contactAddress['state_province_id']
    );
  }

  return implode(', ', $tempAddressArray);
}

