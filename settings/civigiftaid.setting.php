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

return [
  'civigiftaid_globally_enabled' => [
    'admin_group' => 'civigiftaid_general',
    'admin_grouptitle' => 'Gift Aid Financial Types',
    'admin_groupdescription' => 'Customise which financial types gift aid should apply to.',
    'group_name' => 'CiviGiftAid Settings',
    'group' => 'civigiftaid',
    'name' => 'civigiftaid_globally_enabled',
    'type' => 'Boolean',
    'html_type' => 'Checkbox',
    'default' => 1,
    'add' => '5.0',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Enable gift aid for line items of any financial type',
    'html_attributes' => [],
  ],

  // financial_type
  'civigiftaid_financial_types_enabled' => [
    'admin_group' => 'civigiftaid_general',
    'group_name' => 'CiviGiftAid Settings',
    'group' => 'civigiftaid',
    'name' => 'civigiftaid_financial_types_enabled',
    'type' => 'Array',
    'html_type' => 'select2',
    'default' => [],
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Enabled Financial Types',
    'html_attributes' => [
      'placeholder' => E::ts('- select -'),
      'class' => 'huge',
      'multiple' => TRUE
    ],
  ],
];
