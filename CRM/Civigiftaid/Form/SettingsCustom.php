<?php
/*--------------------------------------------------------------------+
 | CiviCRM version 4.7                                                |
+--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2017                                |
+--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +-------------------------------------------------------------------*/

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
