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
 * @package   CRM
 * @copyright CiviCRM LLC (c) 2004-2011
 * $Id$
 *
 */
require_once 'CRM/Contribute/Form/Task.php';

/**
 * This class provides the functionality to add a group of contribution to a batch.
 */
require_once 'CRM/Utils/String.php';

class CRM_Civigiftaid_Form_Task_AddToBatch extends CRM_Contribute_Form_Task {

  protected $_id = NULL;

  /**
   * build all the data structures needed to build the form
   *
   * @return void
   * @access public
   */
  function preProcess() {
    parent::preProcess();

    require_once 'CRM/Civigiftaid/Utils/Contribution.php';
    list($total, $added, $alreadyAdded, $notValid) =
      CRM_Civigiftaid_Utils_Contribution::validateContributionToBatch($this->_contributionIds);
    $this->assign('selectedContributions', $total);
    $this->assign('totalAddedContributions', count($added));
    $this->assign('alreadyAddedContributions', count($alreadyAdded));
    $this->assign('notValidContributions', count($notValid));

    // get details of contribution that will be added to this batch.
    $contributionsAddedRows =
      CRM_Civigiftaid_Utils_Contribution::getContributionDetails($added);
    $this->assign('contributionsAddedRows', $contributionsAddedRows);

    // get details of contribution thatare already added to this batch.
    $contributionsAlreadyAddedRows = [];
    $contributionsAlreadyAddedRows =
      CRM_Civigiftaid_Utils_Contribution::getContributionDetails($alreadyAdded);
    $this->assign(
      'contributionsAlreadyAddedRows',
      $contributionsAlreadyAddedRows
    );

    // get details of contribution that are not valid for giftaid
    $contributionsNotValid = [];
    $contributionsNotValid =
      CRM_Civigiftaid_Utils_Contribution::getContributionDetails($notValid);
    $this->assign('contributionsNotValid', $contributionsNotValid);
  }

  /**
   * Build the form
   *
   * @access public
   * @return void
   */
  function buildQuickForm() {
    $attributes = CRM_Core_DAO::getAttribute('CRM_Batch_DAO_Batch');

    $this->add('text', 'title', ts('Batch Title'), $attributes['title'], TRUE);

    $this->addRule(
      'title',
      ts('Label already exists in Database.'),
      'objectExists',
      ['CRM_Batch_DAO_Batch', $this->_id, 'title']
    );

    $this->add(
      'textarea',
      'description',
      ts('Description:') . ' ',
      $attributes['description']
    );

    require_once 'CRM/Batch/BAO/Batch.php';
    $batchName = CRM_Batch_BAO_Batch::generateBatchName();
    $defaults = ['title' => ts('GiftAid ' . $batchName)];

    $this->setDefaults($defaults);

    $this->addDefaultButtons(ts('Add to batch'));
  }

  /**
   * process the form after the input has been submitted and validated
   *
   * @access public
   * @return None
   */
  public function postProcess() {
    $params = $this->controller->exportValues();
    $batchParams = [];
    $batchParams['title'] = $params['title'];
    $batchParams['name'] = CRM_Utils_String::titleToVar($params['title'], 63);
    $batchParams['description'] = $params['description'];
    $batchParams['batch_type'] = "Gift Aid";

    $session = CRM_Core_Session::singleton();
    $batchParams['created_id'] = $session->get('userID');
    $batchParams['created_date'] = date("YmdHis");
    $batchParams['status_id'] = 0;

    $batchMode = CRM_Core_PseudoConstant::get(
      'CRM_Batch_DAO_Batch',
      'mode_id',
      ['labelColumn' => 'name']
    );
    $batchParams['mode_id'] = CRM_Utils_Array::key('Manual Batch', $batchMode);

    $batchParams['modified_date'] = date('YmdHis');
    $batchParams['modified_id'] = $session->get('userID');

    require_once 'CRM/Core/Transaction.php';
    $transaction = new CRM_Core_Transaction();

    //require_once 'CRM/Core/BAO/Batch.php'; //version 4.2
    require_once 'CRM/Batch/BAO/Batch.php';
    $createdBatch = CRM_Batch_BAO_Batch::create($batchParams);

    $batchID = $createdBatch->id;
    $batchLabel = $batchParams['title'];

    // Save current settings for the batch
    CRM_Civigiftaid_BAO_BatchSettings::create(['batch_id' => $batchID]);

    require_once 'CRM/Civigiftaid/Utils/Contribution.php';
    list($total, $added, $notAdded) =
      CRM_Civigiftaid_Utils_Contribution::addContributionToBatch(
        $this->_contributionIds,
        $batchID
      );

    if ($added <= 0) {
      // rollback since there were no contributions added, and we might not want to keep an empty batch
      $transaction->rollback();
      $status = ts(
        'Could not create batch "%1", as there were no valid contribution(s) to be added.',
        [1 => $batchLabel]
      );
    }
    else {
      $status = [
        ts('Added Contribution(s) to %1', [1 => $batchLabel]),
        ts('Total Selected Contribution(s): %1', [1 => $total])
      ];
      if ($added) {
        $status[] = ts(
          'Total Contribution(s) added to batch: %1',
          [1 => $added]
        );
      }
      if ($notAdded) {
        $status[] = ts(
          'Total Contribution(s) already in batch or not valid: %1',
          [1 => $notAdded]
        );
      }
      $status = implode('<br/>', $status);
    }
    $transaction->commit();
    CRM_Core_Session::setStatus($status);
  }//end of function
}
