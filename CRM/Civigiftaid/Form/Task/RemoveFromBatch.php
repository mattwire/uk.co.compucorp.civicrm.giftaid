<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.3                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2011                                |
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
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2011
 * $Id$
 *
 */

require_once 'CRM/Contribute/Form/Task.php';

/**
 * This class provides the functionality to delete a group of  contribution from batch.
 */

require_once 'CRM/Utils/String.php';

class CRM_Civigiftaid_Form_Task_RemoveFromBatch extends CRM_Contribute_Form_Task {

  protected $_id = null;

    /**
     * build all the data structures needed to build the form
     *
     * @return void
     * @access public
     */
  function preProcess(){

    parent::preProcess( );

      require_once 'CRM/Civigiftaid/Utils/Contribution.php';
      list( $total, $toRemove, $notInBatch, $alreadySubmited) = CRM_Civigiftaid_Utils_Contribution::validationRemoveContributionFromBatch( $this->_contributionIds );

      $this->assign('selectedContributions', $total);
      $this->assign('totalToRemoveContributions', count($toRemove));
      $this->assign('notInBatchContributions', count($notInBatch));
      $this->assign('alreadySubmitedContributions', count($alreadySubmited));
      $this->assign(
        'onlineSubmissionExtensionInstalled',
        CRM_Civigiftaid_Utils_Contribution::isOnlineSubmissionExtensionInstalled()
      );

      $contributionsToRemoveRows = CRM_Civigiftaid_Utils_Contribution::getContributionDetails ( $toRemove );
      $this->assign('contributionsToRemoveRows', $contributionsToRemoveRows );

      $contributionsAlreadySubmitedRows = CRM_Civigiftaid_Utils_Contribution::getContributionDetails ( $alreadySubmited );
      $this->assign( 'contributionsAlreadySubmitedRows', $contributionsAlreadySubmitedRows );

      $contributionsNotInBatchRows = CRM_Civigiftaid_Utils_Contribution::getContributionDetails ( $notInBatch );
      $this->assign( 'contributionsNotInBatchRows', $contributionsNotInBatchRows );
  }


    /**
     * Build the form
     *
     * @access public
     * @return void
     */
    function buildQuickForm( ) {
    $attributes = CRM_Core_DAO::getAttribute( 'CRM_Batch_DAO_Batch' );
        $this->addDefaultButtons( ts('Remove from batch') );

    }

    /**
     * process the form after the input has been submitted and validated
     *
     * @access public
     * @return None
     */
    public function postProcess() {
      require_once 'CRM/Core/Transaction.php';
      $transaction = new CRM_Core_Transaction( );

      require_once 'CRM/Civigiftaid/Utils/Contribution.php';
      list( $total, $removed, $notRemoved ) = CRM_Civigiftaid_Utils_Contribution::removeContributionFromBatch( $this->_contributionIds );

      if ( $removed <= 0 ) {
        //$transaction->rollback( );
        $status = ts('Could not removed contribution from batch, as there were no valid contribution(s) to be removed.');
      } else {
        $transaction->commit( );
        $status = ts('Total Selected Contribution(s): %1', [1 => $total]);
        CRM_Core_Session::setStatus( $status );

        if ( $removed ) {
          $status = ts('Total Contribution(s) removed from batch: %1', [1 => $removed]);
        }
        if ( $notRemoved ) {
          $status = ts('Total Contribution(s) not removed from batch: %1', [1 => $notRemoved]);
        }

     }
     CRM_Core_Session::setStatus( $status );

  }//end of function
}
