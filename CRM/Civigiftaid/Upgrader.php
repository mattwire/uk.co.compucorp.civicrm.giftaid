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

use CRM_Civigiftaid_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Civigiftaid_Upgrader extends CRM_Civigiftaid_Upgrader_Base {

  const REPORT_CLASS = 'CRM_Civigiftaid_Report_Form_Contribute_GiftAid';
  const REPORT_URL = 'civicrm/contribute/report/uk-giftaid';

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Example: Run an external SQL script when the module is installed
   */
  public function install() {
    $this->setOptionGroups();
    $this->enableOptionGroups(1);
    $this->setCustomFields();
    $this->upgrade_3000();
    $this->upgrade_3101();
    $this->upgrade_3103();
    $this->upgrade_3104();
  }

  /**
   * Example: Run an external SQL script when the module is uninstalled
   */
  public function uninstall() {
    $this->unsetSettings();
  }

  /**
   * Example: Run a simple query when a module is enabled
   */
  public function enable() {
    $this->setOptionGroups();
    $this->enableOptionGroups(1);
  }

  /**
   * Example: Run a simple query when a module is disabled
   *
   */
  public function disable() {
    $this->enableOptionGroups(0);
  }

  /**
   * Perform upgrade to version 2.1
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_2100() {
    $this->log('Applying update 2100');
    self::removeLegacyRegisteredReport();
    return TRUE;
  }

  /**
   * Perform upgrade to version 3.0
   *
   * @return bool
   */
  public function upgrade_3000() {
    $this->log('Applying update 3000');

    // Set default settings.
    $this->setDefaultSettings();

    // Create database schema.
    $this->executeSqlFile('sql/upgrade_3000.sql');

    // Import existing batches.
    self::importBatches();

    return TRUE;
  }

  /*
   * Set up Past Year Submissions Job
   */
  public function upgrade_3101() {
    $this->log('Applying update 3101 - Add past year submissions job');

    $existing = civicrm_api3('Job', 'get', [
      'api_entity' => "gift_aid",
      'api_action' => "makepastyearsubmissions",
    ]);

    if (empty($existing['count'])) {
      $jobParams = [
        'domain_id' => CRM_Core_Config::domainID(),
        'run_frequency' => 'Daily',
        'name' => 'Make Past Year Submissions',
        'description' => 'Make Past Year Submissions',
        'api_entity' => 'gift_aid',
        'api_action' => 'makepastyearsubmissions',
        'is_active' => 0,
      ];
      civicrm_api3('Job', 'create', $jobParams);
    }
    return TRUE;
  }

  public function upgrade_3102() {
    $this->log('Applying update 3102');

    // Alter existing eligible_for_gift_aid columns
    CRM_Core_DAO::executeQuery("ALTER TABLE civicrm_value_gift_aid_declaration MODIFY COLUMN eligible_for_gift_aid int");
    CRM_Core_DAO::executeQuery("ALTER TABLE civicrm_value_gift_aid_submission MODIFY COLUMN eligible_for_gift_aid int");

    // Update custom field type from String to Int
    CRM_Core_DAO::executeQuery("UPDATE civicrm_custom_field SET data_type = 'Int' WHERE name = 'Eligible_for_Gift_Aid'");

    return TRUE;
  }

  public function upgrade_3103() {
    $this->log('Applying update 3103 - delete old report templates');
    $this->removeLegacyRegisteredReport();
    return TRUE;
  }

  public function upgrade_3104() {
    $this->log('Applying update 3104 - change profile(s) to use Individual declaration eligibility field instead of contribution eligibility field');
    $contributionGiftAidField = CRM_Civigiftaid_Utils::getCustomByName('Eligible_For_Gift_Aid', 'Gift_Aid');
    $contactGiftAidField = CRM_Civigiftaid_Utils::getCustomByName('Eligible_For_Gift_Aid', 'Gift_Aid_Declaration');
    $helpPost = '<p>By selecting &#039;Yes&#039; above you are confirming that you are a UK taxpayer and the amount of income and/or capital gains tax you pay is at least as much as we will reclaim on your donations in this tax year.</p>
<p><b>About Gift Aid</b></p>
<p>Gift Aid increases the value of donations to charities by allowing them to reclaim basic rate tax on your gift.  We would like to reclaim gift aid on your behalf.  We can only reclaim Gift Aid if you are a UK taxpayer.  Please confirm that you are a eligible for gift aid above.  <a href="http://www.hmrc.gov.uk/individuals/giving/gift-aid.htm">More about Gift Aid</a>.</p>';

    $query = "UPDATE civicrm_uf_field SET field_name='{$contactGiftAidField}', field_type='Individual', help_post='{$helpPost}' WHERE field_name='{$contributionGiftAidField}'";
    CRM_Core_DAO::executeQuery($query);

    $this->log('Applying update 3104 - checking optiongroups');
    $this->setOptionGroups();

    return TRUE;
  }

  public function upgrade_3105() {
    return TRUE;
  }

  public function upgrade_3106() {
    $this->log('Updating custom fields');
    $this->setCustomFields();
    return TRUE;
  }

  private function setCustomFields() {
    $amountCustomField = civicrm_api3('CustomField', 'getsingle', [
      'custom_group_id' => "Gift_Aid",
      'name' => "Amount",
    ]);
    if ($amountCustomField['label'] !== 'Eligible Amount') {
      civicrm_api3('CustomField', 'create', ['id' => $amountCustomField['id'], 'label' => 'Eligible Amount']);
    }

    $customFields = $this->getCustomFields();
    foreach ($customFields as $customField) {
      try {
        $existingField = civicrm_api3('CustomField', 'getsingle', [
          'custom_group_id' => $customField['custom_group_id'],
          'name' => $customField['name']
        ]);
        if (!empty($customField['option_group_id'])) {
          $customField['option_group_id'] = civicrm_api3('OptionGroup', 'getvalue', ['name' => $customField['option_group_id'], 'return' => 'id']);
        }
      }
      catch (Exception $e) {
        continue;
      }
      $customField = array_merge($customField, ['id' => $existingField['id']]);
      civicrm_api3('CustomField', 'create', $customField);
    }
  }

  private function getCustomFields() {
    $optionGroups = $this->getOptionGroups();
    $customFields = [
      'Eligible_for_Gift_Aid' => [
        'custom_group_id' => 'Gift_Aid',
        'name' => 'Eligible_for_Gift_Aid',
        'label' => 'Eligible for Gift Aid?',
        'data_type' => 'Int',
        'html_type' => 'Radio',
        'is_searchable' => '1',
        'is_search_range' => '0',
        'weight' => '1',
        'help_pre' => '\'Eligible for Gift Aid\' will be set automatically based on the financial type of the contribution if you do not select Yes or No',
        'is_active' => '1',
        'column_name' => 'eligible_for_gift_aid',
        'option_group_id' => 'uk_taxpayer_options',
        'in_selector' => '0'
      ]
    ];
    return $customFields;
  }

  private function getOptionGroups() {
    // eligibility_declaration_options is for the Gift Aid Declaration (3 options)
    // uk_taxpayer_options is for the Gift Aid Contribution (2 options)
    $optionGroups = [
      'eligibility_declaration_options' => [
        'name' => 'eligibility_declaration_options',
        'title' => 'GiftAid eligibility declaration options',
        'is_active' => 1,
        'is_reserved' => 1,
      ],
      'uk_taxpayer_options' => [
        'name' => 'uk_taxpayer_options',
        'title' => 'GiftAid contribution eligibility',
        'is_active' => 1,
        'is_reserved' => 1
      ],
      'giftaid_basic_rate_tax' => [
        'name' => 'giftaid_basic_rate_tax',
        'title' => 'GiftAid basic rate of tax',
        'is_active' => 1,
        'is_reserved' => 1
      ],
      'giftaid_batch_name' => [
        'name' => 'giftaid_batch_name',
        'title' => 'GiftAid batch name',
        'is_active' => 1,
        'is_reserved' => 1
      ],
      'reason_ended' => [
        'name' => 'reason_ended',
        'title' => 'GiftAid reason ended',
        'is_active' => 1,
        'is_reserved' => 1
      ],
    ];
    return $optionGroups;
  }

  private function getOptionValues($optionGroups) {
    $optionValues = [
      [
        'option_group_id' => $optionGroups['eligibility_declaration_options']['id'],
        'label' => 'Yes, today and in the future',
        'value' => 1,
        'name' => 'eligible_for_giftaid',
        'is_default' => 0,
        'weight' => 2,
        'is_reserved' => 1,
      ],
      [
        'option_group_id' => $optionGroups['eligibility_declaration_options']['id'],
        'label' => 'No',
        'value' => 0,
        'name' => 'not_eligible_for_giftaid',
        'is_default' => 0,
        'weight' => 3,
        'is_reserved' => 1,
      ],
      [
        'option_group_id' => $optionGroups['eligibility_declaration_options']['id'],
        'label' => 'Yes, and for donations made in the past 4 years',
        'value' => 3,
        'name' => 'past_four_years',
        'is_default' => 0,
        'weight' => 1,
        'is_reserved' => 1,
      ],
      [
        'option_group_id' => $optionGroups['uk_taxpayer_options']['id'],
        'label' => 'Yes',
        'value' => 1,
        'name' => 'yes_uk_taxpayer',
        'is_default' => 0,
        'is_reserved' => 1,
      ],
      [
        'option_group_id' => $optionGroups['uk_taxpayer_options']['id'],
        'label' => 'No',
        'value' => 0,
        'name' => 'not_uk_taxpayer',
        'is_default' => 0,
        'is_reserved' => 1,
      ],
      [
        'option_group_id' => $optionGroups['uk_taxpayer_options']['id'],
        'label' => 'Yes, in the Past 4 Years',
        'value' => 3,
        'name' => 'uk_taxpayer_past_four_years',
        'is_active' => 0,
        'is_default' => 0,
        'is_reserved' => 1,
      ],

      [
        'option_group_id' => $optionGroups['reason_ended']['id'],
        'label' => 'Contact Declined',
        'value' => 'Contact Declined',
        'name' => 'Contact_Declined',
        'is_default' => 0,
        'weight' => 2,
      ],
      [
        'option_group_id' => $optionGroups['reason_ended']['id'],
        'label' => 'HMRC Declined',
        'value' => 'HMRC Declined',
        'name' => 'HMRC_Declined',
        'is_default' => 0,
        'weight' => 1,
      ],

      [
        'option_group_id' => $optionGroups['giftaid_basic_rate_tax']['id'],
        'label' => 'The basic rate tax',
        'value' => 20,
        'name' => 'basic_rate_tax',
        'is_default' => 0,
        'weight' => 1,
        'is_reserved' => 1,
        'description' => 'The GiftAid basic tax rate (%)'
      ],
    ];

    return $optionValues;
  }

  private function setOptionGroups() {
    foreach ($this->getOptionGroups() as $groupName => $groupParams) {
      $optionGroups[$groupName] = civicrm_api3('OptionGroup', 'get', [
        'name' => $groupName,
      ]);
      if ($optionGroups[$groupName]['id']) {
        $groupParams['id'] = $optionGroups[$groupName]['id'];
      }
      // Add new option groups and options
      $optionGroups[$groupName] = civicrm_api3('OptionGroup', 'create', $groupParams);
    }

    $optionValues = $this->getOptionValues($optionGroups);
    foreach($optionValues as $params) {
      $optionValue = civicrm_api3('OptionValue', 'get', [
        'option_group_id' => $params['option_group_id'],
        'value' => $params['value'],
      ]);
      if (CRM_Utils_Array::value('id', $optionValue)) {
        $params['id'] = $optionValue['id'];
      }
      civicrm_api3('OptionValue', 'create', $params);
    }

    $declarationCustomGroupID = CRM_Utils_Array::value('id', civicrm_api3('CustomGroup', 'getsingle', ['name' => 'Gift_Aid_Declaration']));
    $submissionCustomGroupID = CRM_Utils_Array::value('id', civicrm_api3('CustomGroup', 'getsingle', ['name' => 'Gift_Aid']));

    CRM_Core_DAO::executeQuery("UPDATE civicrm_custom_field SET option_group_id = {$optionGroups['eligibility_declaration_options']['id']}
WHERE name = 'Eligible_for_Gift_Aid' AND custom_group_id = {$declarationCustomGroupID}");
    CRM_Core_DAO::executeQuery("UPDATE civicrm_custom_field SET option_group_id = {$optionGroups['reason_ended']['id']}
WHERE name = 'Reason_Ended' AND custom_group_id = {$declarationCustomGroupID}");
    CRM_Core_DAO::executeQuery("UPDATE civicrm_custom_field SET option_group_id = {$optionGroups['uk_taxpayer_options']['id']}
WHERE name = 'Eligible_for_Gift_Aid' AND custom_group_id = {$submissionCustomGroupID}");
    CRM_Core_DAO::executeQuery("UPDATE civicrm_custom_field SET option_group_id = {$optionGroups['giftaid_batch_name']['id']}
WHERE name = 'Batch_Name' AND custom_group_id = {$submissionCustomGroupID}");

    // Make sure profile is active
    CRM_Core_DAO::executeQuery("UPDATE civicrm_uf_group SET is_active = 1
WHERE name = 'Gift_Aid'");
  }

  /**
   * Enable/Disable option groups
   * @param int $enable
   */
  private function enableOptionGroups($enable = 1) {
    CRM_Core_DAO::executeQuery("UPDATE civicrm_option_group SET is_active = {$enable} WHERE name = 'giftaid_batch_name'");
    CRM_Core_DAO::executeQuery("UPDATE civicrm_option_group SET is_active = {$enable} WHERE name = 'giftaid_basic_rate_tax'");
    CRM_Core_DAO::executeQuery("UPDATE civicrm_option_group SET is_active = {$enable} WHERE name = 'reason_ended'");

    try {
      civicrm_api3('CustomGroup', 'update', [
        'is_active' => $enable,
        'id' => CRM_Utils_Array::value('id', civicrm_api3('CustomGroup', 'getsingle', ['name' => 'Gift_Aid'])),
      ]);
    }
    catch (Exception $e) {
      // Couldn't find CustomGroup, maybe it was manually deleted
    }

    try {
      civicrm_api3('CustomGroup', 'update', [
        'is_active' => $enable,
        'id' => CRM_Utils_Array::value('id', civicrm_api3('CustomGroup', 'getsingle', ['name' => 'Gift_Aid_Declaration'])),
      ]);
    }
    catch (Exception $e) {
      // Couldn't find CustomGroup, maybe it was manually deleted
    }

    try {
      civicrm_api3('UFGroup', 'update', [
        'is_active' => $enable,
        'id' =>  CRM_Utils_Array::value('id',civicrm_api3('UFGroup', 'getsingle', ['name' => 'Gift_Aid'])),
      ]);
    }
    catch (Exception $e) {
      // Couldn't find CustomGroup, maybe it was manually deleted
    }
  }

  /**
   * Remove report templates created by older versions
   */
  private static function removeLegacyRegisteredReport(){
    $report1 = civicrm_api3('OptionValue', 'get', [
      'option_group_id' => "report_template",
      'name' => 'GiftAid_Report_Form_Contribute_GiftAid',
    ]);
    $report2 = civicrm_api3('OptionValue', 'get', [
      'option_group_id' => "report_template",
      'value' => 'civicrm/contribute/uk-giftaid',
    ]);

    $reports = [];
    if (!empty($report1['count'])) {
      $reports[] = CRM_Utils_Array::first($report1['values']);
    }
    if (!empty($report2['count'])) {
      $reports[] = CRM_Utils_Array::first($report2['values']);
    }
    foreach ($reports as $report) {
      civicrm_api3('OptionValue', 'delete', ['id' => $report['id']]);
    }
  }

  private static function migrateOneToTwo($ctx){
    $ctx->executeSqlFile('sql/upgrade_20.sql');
    $query = "SELECT DISTINCT batch_name
              FROM civicrm_value_gift_aid_submission
             ";
    $batchNames = [];
    $dao = CRM_Core_DAO::executeQuery($query);
    while ($dao->fetch()) {
      array_push($batchNames, $dao->batch_name);
    }
    $gId = CRM_Utils_Array::value('id',civicrm_api3('OptionGroup', 'getsingle', ['name' => 'giftaid_batch_name']));
    if ($gId) {
      foreach ($batchNames as $name) {
        $params = [
          'option_group_id' => $gId,
          'name' => $name,
        ];
        $existing = civicrm_api3('OptionValue', 'get', $params);
        if (empty($existing['count'])) {
          $params['label'] = $name;
          $params['value'] = $name;
          $params['is_active'] = 1;
          civicrm_api3('OptionValue', 'create', $params);
        }
      }
    }
  }

  /**
   * Set the default admin settings for the extension.
   */
  private function setDefaultSettings() {
    Civi::settings()->set(E::SHORT_NAME . 'globally_enabled', 1);
    Civi::settings()->set(E::SHORT_NAME . 'financial_types_enabled', []);
  }

  /**
   * Remove the admin settings for the extension.
   */
  private function unsetSettings() {
    Civi::settings()->revert(E::SHORT_NAME . 'globally_enabled');
    Civi::settings()->revert(E::SHORT_NAME . 'financial_types_enabled');
  }

  /**
   * Create default settings for existing batches, for which settings don't already exist.
   */
  private static function importBatches() {
    $sql = "
      SELECT id
      FROM civicrm_batch
      WHERE name LIKE 'GiftAid%'
    ";

    $dao = CRM_Core_DAO::executeQuery($sql);

    $basicRateTax = CRM_Civigiftaid_Utils_Contribution::getBasicRateTax();

    while ($dao->fetch()) {
      // Only add settings for batches for which settings don't exist already
      if (CRM_Civigiftaid_BAO_BatchSettings::findByBatchId($dao->id) === FALSE) {
        // Set globally enabled to TRUE by default, for existing batches
        CRM_Civigiftaid_BAO_BatchSettings::create([
          'batch_id' => (int) $dao->id,
          'financial_types_enabled' => [],
          'globally_enabled' => TRUE,
          'basic_rate_tax' => $basicRateTax
        ]);
      }
    }
  }

  private function log($message) {
    Civi::log()->info($message);
  }
}
