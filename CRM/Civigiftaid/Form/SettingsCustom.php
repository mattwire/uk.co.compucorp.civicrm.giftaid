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
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Civigiftaid_Form_SettingsCustom extends CRM_Civigiftaid_Form_Settings {

  /**
   * @param \CRM_Core_Form $form
   * @param string $name
   * @param array $setting
   *
   * @throws \CiviCRM_API3_Exception
   */
  public static function addSelect2Element(&$form, $name, $setting) {
    switch ($name) {
      case 'financial_types_enabled':
        $financialTypes = civicrm_api3('FinancialType', 'get', [
          'is_active' => 1,
          'options' => ['limit' => 0, 'sort' => "name ASC"],
        ]);
        $types = [];
        foreach ($financialTypes['values'] as $type) {
          $types[] = [
            'id' => $type['id'],
            'text' => $type['name'],
          ];

        }
        $form->add('select2', $name, $setting['description'], $types, FALSE, $setting['html_attributes']);
        break;
    }
  }

}
