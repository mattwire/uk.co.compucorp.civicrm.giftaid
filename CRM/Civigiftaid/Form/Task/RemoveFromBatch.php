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
 * Class CRM_Civigiftaid_Form_Task_RemoveFromBatch
 *
 * This class provides the functionality to delete a group of  contribution from batch.
 */
class CRM_Civigiftaid_Form_Task_RemoveFromBatch extends CRM_Contribute_Form_Task {

  public function preProcess() {
    parent::preProcess();
    list($total, $toRemove, $notInBatch, $alreadySubmitted) = CRM_Civigiftaid_Utils_Contribution::validationRemoveContributionFromBatch($this->_contributionIds);

    $this->assign('selectedContributions', $total);
    $this->assign('totalToRemoveContributions', count($toRemove));
    $this->assign('notInBatchContributions', count($notInBatch));
    $this->assign('alreadySubmitedContributions', count($alreadySubmitted));
    $this->assign(
      'onlineSubmissionExtensionInstalled',
      CRM_Civigiftaid_Utils_Contribution::isOnlineSubmissionExtensionInstalled()
    );

    $contributionsToRemoveRows = CRM_Civigiftaid_Utils_Contribution::getContributionDetails($toRemove);
    $this->assign('contributionsToRemoveRows', $contributionsToRemoveRows);

    $contributionsAlreadySubmitedRows = CRM_Civigiftaid_Utils_Contribution::getContributionDetails($alreadySubmitted);
    $this->assign('contributionsAlreadySubmitedRows', $contributionsAlreadySubmitedRows);

    $contributionsNotInBatchRows = CRM_Civigiftaid_Utils_Contribution::getContributionDetails($notInBatch);
    $this->assign('contributionsNotInBatchRows', $contributionsNotInBatchRows);
  }

  public function buildQuickForm() {
    $this->addDefaultButtons(E::ts('Remove from batch'), 'next', 'cancel');
  }

  public function postProcess() {
    $transaction = new CRM_Core_Transaction();

    list($total, $removed, $notRemoved) = CRM_Civigiftaid_Utils_Contribution::removeContributionFromBatch($this->_contributionIds);

    if ($removed <= 0) {
      $status = E::ts('Could not removed contribution from batch, as there were no valid contribution(s) to be removed.');
    } else {
      $transaction->commit();
      $status = E::ts('Total Selected Contribution(s): %1', [1 => $total]);
      CRM_Core_Session::setStatus($status);

      if ($removed) {
        $status = E::ts('Total Contribution(s) removed from batch: %1', [1 => $removed]);
      }
      if ($notRemoved) {
        $status = E::ts('Total Contribution(s) not removed from batch: %1', [1 => $notRemoved]);
      }
    }
    CRM_Core_Session::setStatus($status);
  }

}
