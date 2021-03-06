-- +--------------------------------------------------------------------+
-- | CiviCRM version 5                                                  |
-- +--------------------------------------------------------------------+
-- | Copyright CiviCRM LLC (c) 2004-2019                                |
-- +--------------------------------------------------------------------+
-- | This file is a part of CiviCRM.                                    |
-- |                                                                    |
-- | CiviCRM is free software; you can copy, modify, and distribute it  |
-- | under the terms of the GNU Affero General Public License           |
-- | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
-- |                                                                    |
-- | CiviCRM is distributed in the hope that it will be useful, but     |
-- | WITHOUT ANY WARRANTY; without even the implied warranty of         |
-- | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
-- | See the GNU Affero General Public License for more details.        |
-- |                                                                    |
-- | You should have received a copy of the GNU Affero General Public   |
-- | License and the CiviCRM Licensing Exception along                  |
-- | with this program; if not, contact CiviCRM LLC                     |
-- | at info[AT]civicrm[DOT]org. If you have questions about the        |
-- | GNU Affero General Public License or the licensing of CiviCRM,     |
-- | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
-- +--------------------------------------------------------------------+
--
-- Generated from drop.tpl
-- DO NOT EDIT.  Generated by CRM_Core_CodeGen
--
-- /*******************************************************
-- *
-- * Clean up the exisiting tables
-- *
-- *******************************************************/

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `civicrm_civigiftaid_batchsettings`;

SET FOREIGN_KEY_CHECKS=1;
-- /*******************************************************
-- *
-- * Create new tables
-- *
-- *******************************************************/

-- /*******************************************************
-- *
-- * civicrm_civigiftaid_batchsettings
-- *
-- * FIXME
-- *
-- *******************************************************/
CREATE TABLE `civicrm_civigiftaid_batchsettings` (


     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'Unique BatchSettings ID',
     `batch_id` int unsigned    COMMENT 'FK to Batch',
     `financial_types_enabled` text    COMMENT 'Financial type enabled for this batch',
     `globally_enabled` tinyint    COMMENT 'Globally enabled for this batch',
     `basic_rate_tax` decimal(4,2) NOT NULL   COMMENT 'Basic rate tax for the batch.' 
,
        PRIMARY KEY (`id`)
 
 
,          CONSTRAINT FK_civicrm_civigiftaid_batchsettings_batch_id FOREIGN KEY (`batch_id`) REFERENCES `civicrm_batch`(`id`) ON DELETE CASCADE  
)    ;

 
