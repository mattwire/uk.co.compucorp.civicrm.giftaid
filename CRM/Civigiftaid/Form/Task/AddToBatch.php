<?php

/**
 * https://civicrm.org/license
 */

use CRM_Civigiftaid_ExtensionUtil as E;

/**
 * This class provides the functionality to add a group of contribution to a batch.
 */

class CRM_Civigiftaid_Form_Task_AddToBatch extends CRM_Contribute_Form_Task {

  /**
   * @var int
   */
  protected $_id = NULL;

  public function preProcess() {
    parent::preProcess();

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

  public function buildQuickForm() {
    $attributes = CRM_Core_DAO::getAttribute('CRM_Batch_DAO_Batch');

    $this->add('text', 'title', E::ts('Batch Title'), $attributes['title'], TRUE);

    $this->addRule(
      'title',
      E::ts('Label already exists in Database.'),
      'objectExists',
      ['CRM_Batch_DAO_Batch', $this->_id, 'title']
    );

    $this->add(
      'textarea',
      'description',
      E::ts('Description:') . ' ',
      $attributes['description']
    );

    $batchName = CRM_Batch_BAO_Batch::generateBatchName();
    $defaults = ['title' => E::ts('GiftAid ' . $batchName)];

    $this->setDefaults($defaults);

    $this->addDefaultButtons(E::ts('Add to batch'));
  }

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

    $transaction = new CRM_Core_Transaction();

    $createdBatch = CRM_Batch_BAO_Batch::create($batchParams);

    $batchID = $createdBatch->id;
    $batchLabel = $batchParams['title'];

    // Save current settings for the batch
    CRM_Civigiftaid_BAO_BatchSettings::create(['batch_id' => $batchID]);

    list($total, $added, $notAdded) =
      CRM_Civigiftaid_Utils_Contribution::addContributionToBatch(
        $this->_contributionIds,
        $batchID
      );

    if ($added <= 0) {
      // rollback since there were no contributions added, and we might not want to keep an empty batch
      $transaction->rollback();
      $status = E::ts(
        'Could not create batch "%1", as there were no valid contribution(s) to be added.',
        [1 => $batchLabel]
      );
    }
    else {
      $status = [
        E::ts('Added Contribution(s) to %1', [1 => $batchLabel]),
        E::ts('Total Selected Contribution(s): %1', [1 => $total])
      ];
      if ($added) {
        $status[] = E::ts(
          'Total Contribution(s) added to batch: %1',
          [1 => $added]
        );
      }
      if ($notAdded) {
        $status[] = E::ts(
          'Total Contribution(s) already in batch or not valid: %1',
          [1 => $notAdded]
        );
      }
      $status = implode('<br/>', $status);
    }
    $transaction->commit();
    CRM_Core_Session::setStatus($status);
  }

}
