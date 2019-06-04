<?php

/**
 * https://civicrm.org/license
 */

class CRM_Civigiftaid_Utils_Contribution {

  /**
   * Given an array of contributionIDs, add them to a batch
   *
   * @param array $contributionIDs
   * @param int $batchID
   *
   * @return array
   *           (total, added, notAdded) ids of contributions added to the batch
   * @throws \CRM_Extension_Exception
   * @throws \CiviCRM_API3_Exception
   */
  public static function addContributionToBatch($contributionIDs, $batchID) {
    $contributionsAdded = [];
    $contributionsNotAdded = [];

    // Get the batch name
    $batch = new CRM_Batch_DAO_Batch();
    $batch->id = $batchID;
    $batch->find(TRUE);
    $batchName = $batch->title;

    $batchNameGroup = civicrm_api3('OptionGroup', 'getsingle', ['sequential' => 1, 'name' => 'giftaid_batch_name']);
    if ($batchNameGroup['id']) {
      $groupId = $batchNameGroup['id'];
      $params = [
        'sequential'      => 1,
        'option_group_id' => $groupId,
        'value'           => $batchName,
        'label'           => $batchName
      ];
      civicrm_api3('OptionValue', 'create', $params);
    }

    foreach ($contributionIDs as $contributionID) {
      $batchContribution = new CRM_Batch_DAO_EntityBatch();
      $batchContribution->entity_table = 'civicrm_contribution';
      $batchContribution->entity_id = $contributionID;

      // check if the selected contribution id already in a batch
      // if not, add to batchContribution else keep the count of contributions that are not added
      if ($batchContribution->find(TRUE)) {
        $contributionsNotAdded[] = $contributionID;
        continue;
      }

      // get additional info
      // get contribution details from Contribution using contribution id
      $params = ['id' => $contributionID];
      CRM_Contribute_BAO_Contribution::retrieve($params, $contribution, $ids);
      $contactId = $contribution['contact_id'];

      // check if contribution is valid for gift aid
      if (CRM_Civigiftaid_Utils_GiftAid::isEligibleForGiftAid(
          $contactId,
          $contribution['receive_date'],
          $contributionID
        ) AND $contribution['contribution_status_id'] == 1
      ) {
        $batchContribution->batch_id = $batchID;
        $batchContribution->save();

        self::updateGiftAidFields($contributionID, $batchName);

        $contributionsAdded[] = $contributionID;
      }
      else {
        $contributionsNotAdded[] = $contributionID;
      }
    }

    if (!empty($contributionsAdded)) {
      // if there is any extra work required to be done for contributions that are batched,
      // should be done via hook
      CRM_Civigiftaid_Utils_Hook::batchContributions(
        $batchID,
        $contributionsAdded
      );
    }

    return [
      count($contributionIDs),
      count($contributionsAdded),
      count($contributionsNotAdded)
    ];
  }

  /**
   * @param int $contributionID
   * @param string $batchName
   *
   * @throws \CRM_Extension_Exception
   * @throws \CiviCRM_API3_Exception
   */
  public static function updateGiftAidFields($contributionID, $batchName = '') {
    $totalAmount = civicrm_api3('Contribution', 'getvalue', [
      'return' => "total_amount",
      'id' => $contributionID,
    ]);
    $giftAidableContribAmt = self::getGiftAidableContribAmt($totalAmount, $contributionID);

    $giftAidAmount = self::calculateGiftAidAmt($giftAidableContribAmt, self::getBasicRateTax());

    // FIXME: check if there is customTable method
    $query = "
                          INSERT INTO civicrm_value_gift_aid_submission
                          (entity_id, eligible_for_gift_aid, gift_aid_amount , amount , batch_name)
                          VALUES
                            ( %1, 1, %2, %3 , %4 )
                          ON DUPLICATE KEY UPDATE
                          gift_aid_amount = %2 ,
                          amount = %3 ,
                          batch_name = %4
                          ";
    $sqlParams = [
      1 => [$contributionID, 'Integer'],
      2 => [$giftAidAmount, 'Money'],
      3 => [$totalAmount, 'Money'],
      4 => [$batchName, 'String'],
    ];
    CRM_Core_DAO::executeQuery($query, $sqlParams);
  }

  /**
   * @param array $contributionIDs
   *
   * @return array
   */
  public static function removeContributionFromBatch($contributionIDs) {
    $contributionRemoved = [];
    $contributionNotRemoved = [];

    list($total, $contributionsToRemove, $notInBatch, $alreadySubmited) =
      self::validationRemoveContributionFromBatch($contributionIDs);

    $contributions = self::getContributionDetails($contributionsToRemove);

    if (!empty($contributions)) {
      foreach ($contributions as $contribution) {
        if (!empty($contribution['batch_id'])) {

          $batchContribution = new CRM_Batch_DAO_EntityBatch();
          $batchContribution->entity_table = 'civicrm_contribution';
          $batchContribution->entity_id = $contribution['contribution_id'];
          $batchContribution->batch_id = $contribution['batch_id'];
          $batchContribution->delete();

          // FIXME: check if there API to user
          $query = "UPDATE civicrm_value_gift_aid_submission
                      SET gift_aid_amount = NULL,
                          amount = NULL,
                          batch_name = NULL
                      WHERE entity_id = %1";

          $sqlParams = [
            1 => [$contribution['contribution_id'], 'Integer']
          ];
          CRM_Core_DAO::executeQuery($query, $sqlParams);

          array_push($contributionRemoved, $contribution['contribution_id']);

        }
        else {
          array_push($contributedNotRemoved, $contribution['contribution_id']);
        }
      }
    }

    return [
      count($contributionIDs),
      count($contributionRemoved),
      count($contributionNotRemoved)
    ];
  }

  /**
   * Get the total amount for line items, for a contribution given by its ID,
   * having financial type which have been enabled in Gift Aid extension's
   * settings.
   *
   * @param int $contributionId
   *
   * @return float|int
   */
  public static function getContribAmtForEnabledFinanceTypes($contributionId) {
    $sql = "
      SELECT SUM(line_total) total
      FROM civicrm_line_item
      WHERE contribution_id = {$contributionId}";

    if (!(bool) CRM_Civigiftaid_Settings::getValue('globally_enabled')) {
      $enabledTypes = (array) CRM_Civigiftaid_Settings::getValue('financial_types_enabled');
      $enabledTypesStr = implode(', ', $enabledTypes);

      // if no financial types are selected, don't return anything from query
      $sql .= $enabledTypesStr
        ? " AND financial_type_id IN ({$enabledTypesStr})"
        : " AND 0";
    }

    $dao = CRM_Core_DAO::executeQuery($sql);

    $contributionAmount = 0;
    while ($dao->fetch()) {
      $contributionAmount = (float) $dao->total;
    }

    return $contributionAmount;
  }

  /**
   * This function calculate the gift aid amount.
   * Formula used is: (contributed amount * basic rate of year) / (100 - basic rate of year)
   * E.g. For a donation of £100 and basic rate of tax of 20%, gift aid amount = £100 * 20 / 80. In other words, £25
   * for every £100, or 25p for every £1.
   *
   * TODO: Move to utils.
   *
   * @param $contribAmt
   * @param $basicTaxRate
   *
   * @return float
   */
  public static function calculateGiftAidAmt($contribAmt, $basicTaxRate) {
    return (($contribAmt * $basicTaxRate) / (100 - $basicTaxRate));
  }

  /**
   * Get the basic tax rate currently defined in the settings.
   * TODO: Move to utils.
   *
   * @return float
   * @throws \CRM_Extension_Exception
   * @throws \CiviCRM_API3_Exception
   */
  public static function getBasicRateTax() {
    if (!isset(Civi::$statics[__CLASS__]['basictaxrate'])) {
      $rate = NULL;

      $gResult = civicrm_api3('OptionGroup', 'getsingle', ['name' => 'giftaid_basic_rate_tax']);

      if ($gResult['id']) {
        $params = [
          'sequential' => 1,
          'option_group_id' => $gResult['id'],
          'name' => 'basic_rate_tax',
        ];
        $result = civicrm_api3('OptionValue', 'get', $params);

        if ($result['values']) {
          foreach ($result['values'] as $ov) {
            if ($result['id'] == $ov['id'] && $ov['value'] !== '') {
              $rate = $ov['value'];
            }
          }
        }
      }

      if (is_null($rate)) {
        throw new CRM_Extension_Exception(
          'Basic Tax Rate not currently set! Please set it in the Gift Aid extension settings.'
        );
      }

      Civi::$statics[__CLASS__]['basictaxrate'] = (float) $rate;
    }
    return Civi::$statics[__CLASS__]['basictaxrate'];
  }

  /**
   * @return bool
   */
  public static function isOnlineSubmissionExtensionInstalled() {
    try {
      civicrm_api3('Extension', 'getsingle', [
        'is_active' => 1,
        'full_name' => 'uk.co.vedaconsulting.module.giftaidonline',
      ]);
    }
    catch (Exception $e) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * @param array $contributionIDs
   *
   * @return array
   */
  public static function validationRemoveContributionFromBatch($contributionIDs) {
    $contributionsAlreadySubmited = [];
    $contributionsNotInBatch = [];
    $contributionsToRemove = [];

    foreach ($contributionIDs as $contributionID) {

      $batchContribution = new CRM_Batch_DAO_EntityBatch();
      $batchContribution->entity_table = 'civicrm_contribution';
      $batchContribution->entity_id = $contributionID;

      // check if the selected contribution id is in a batch
      if ($batchContribution->find(TRUE)) {
        if (self::isOnlineSubmissionExtensionInstalled()) {

          if (self::isBatchAlreadySubmitted($batchContribution->batch_id)) {
            $contributionsAlreadySubmited[] = $contributionID;
          }
          else {
            $contributionsToRemove[] = $contributionID;
          }
        }
        else {
          $contributionsToRemove[] = $contributionID;
        }
      }
      else {
        $contributionsNotInBatch[] = $contributionID;
      }
    }

    return [
      count($contributionIDs),
      $contributionsToRemove,
      $contributionsNotInBatch,
      $contributionsAlreadySubmited
    ];
  }

  /**
   * This function check contribution is valid for giftaid or not:
   * 1 - if contribution_id already inserted in batch_contribution
   * 2 - if contributions are not valid for gift aid
   *
   * @param array $contributionIDs
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public static function validateContributionToBatch($contributionIDs) {
    $contributionsAdded = [];
    $contributionsAlreadyAdded = [];
    $contributionsNotValid = [];

    foreach ($contributionIDs as $contributionID) {
      $batchContribution = new CRM_Batch_DAO_EntityBatch();
      $batchContribution->entity_table = 'civicrm_contribution';
      $batchContribution->entity_id = $contributionID;

      // check if the selected contribution id already in a batch
      // if not, increment $numContributionsAdded else keep the count of contributions that are already added
      if (!$batchContribution->find(TRUE)) {
        // get contact_id, & contribution receive date from Contribution using contribution id
        $params = ['id' => $contributionID];
        CRM_Contribute_BAO_Contribution::retrieve($params, $defaults, $ids);

        // check if contribution is not valid for gift aid, increment $numContributionsNotValid
        if (CRM_Civigiftaid_Utils_GiftAid::isEligibleForGiftAid(
            $defaults['contact_id'],
            $defaults['receive_date'], $contributionID
          ) AND $defaults['contribution_status_id'] == 1
        ) {
          $contributionsAdded[] = $contributionID;
        }
        else {
          $contributionsNotValid[] = $contributionID;
        }
      }
      else {
        $contributionsAlreadyAdded[] = $contributionID;
      }
    }

    return [
      count($contributionIDs),
      $contributionsAdded,
      $contributionsAlreadyAdded,
      $contributionsNotValid
    ];
  }

  /**
   * Returns the array of batchID & title
   *
   * @param string $orderBy
   *
   * @return array
   */
  public static function getBatchIdTitle($orderBy = 'id') {
    $query = "SELECT * FROM civicrm_batch ORDER BY " . $orderBy;
    $dao = CRM_Core_DAO::executeQuery($query);

    $result = [];
    while ($dao->fetch()) {
      $result[$dao->id] = $dao->id . " - " . $dao->title;
    }
    return $result;
  }

  /*
   * Returns the array of contributions
   *
   * @param array $contributionIds
   *
   * @return array
   */
  public static function getContributionDetails($contributionIds) {
    $result = [];

    if (empty($contributionIds)) {
      return $result;
    }

    $contributionIdStr = implode(',', $contributionIds);

    self::addContributionDetails($contributionIdStr, $result);

    if (count($result)) {
      self::addLineItemDetails($contributionIdStr, $result);
    }

    return $result;
  }

  /**
   * this function is to check if the batch is already submitted to HMRC using GiftAidOnline Module
   *
   * @param int $pBatchId a batchId
   *
   * @return true if already submitted and if not
   */
  public static function isBatchAlreadySubmitted($pBatchId) {
    if (!self::isOnlineSubmissionExtensionInstalled()) {
      return FALSE;
    }

    $onlineSubmission = new CRM_Giftaidonline_Page_OnlineSubmission();
    $bIsSubmitted = $onlineSubmission->is_submitted($pBatchId);
    return $bIsSubmitted;
  }

  /**
   * @param string $entityTable Entity table name
   *
   * @return string
   */
  public static function getLineItemName($entityTable) {
    switch ($entityTable) {
      case 'civicrm_participant':
        return 'Event';

      case 'civicrm_membership':
        return 'Membership';

      case 'civicrm_contribution':
        return 'Donation';

      case 'civicrm_participation':
        return 'Participation';

      default:
        return $entityTable;
    }
  }

  /////////////////////
  // Private Methods //
  /////////////////////

  /**
   * @param string $contributionIdStr
   * @param array $result
   *
   * @throws \CRM_Core_Exception
   */
  private static function addContributionDetails($contributionIdStr, &$result) {
    $query = "
      SELECT  contribution.id, contact.id contact_id, contact.display_name, contribution.total_amount, contribution.currency,
              financial_type.name, contribution.source, contribution.receive_date, batch.title, batch.id as batch_id
      FROM civicrm_contribution contribution
      LEFT JOIN civicrm_contact contact ON ( contribution.contact_id = contact.id )
      LEFT JOIN civicrm_financial_type financial_type ON ( financial_type.id = contribution.financial_type_id  )
      LEFT JOIN civicrm_entity_batch entity_batch ON ( entity_batch.entity_id = contribution.id )
      LEFT JOIN civicrm_batch batch ON ( batch.id = entity_batch.batch_id )
      WHERE contribution.id IN ({$contributionIdStr})";

    $dao = CRM_Core_DAO::executeQuery($query);

    while ($dao->fetch()) {
      $result[$dao->id]['contact_id'] = $dao->contact_id;
      $result[$dao->id]['contribution_id'] = $dao->id;
      $result[$dao->id]['display_name'] = $dao->display_name;
      $result[$dao->id]['gift_aidable_amount'] = CRM_Utils_Money::format(
        static::getGiftAidableContribAmt($dao->total_amount, $dao->id),
        $dao->currency
      );
      $result[$dao->id]['total_amount'] = CRM_Utils_Money::format(
        $dao->total_amount, $dao->currency
      );
      $result[$dao->id]['financial_account'] = $dao->name;
      $result[$dao->id]['source'] = $dao->source;
      $result[$dao->id]['receive_date'] = $dao->receive_date;
      $result[$dao->id]['batch'] = $dao->title;
      $result[$dao->id]['batch_id'] = $dao->batch_id;
    }
  }

  /**
   * @param string $contributionIdStr
   * @param array $result
   *
   * @throws \CRM_Core_Exception
   */
  private static function addLineItemDetails($contributionIdStr, &$result) {
    $query = "
      SELECT c.id, i.entity_table, i.label, i.line_total, i.qty, c.currency, t.name
      FROM civicrm_contribution c
      LEFT JOIN civicrm_line_item i
      ON c.id = i.contribution_id
      LEFT JOIN civicrm_financial_type t
      ON i.financial_type_id = t.id
      WHERE c.id IN ($contributionIdStr)";

    if (!(bool) CRM_Civigiftaid_Settings::getValue('globally_enabled')) {
      $enabledTypes = (array) CRM_Civigiftaid_Settings::getValue('financial_types_enabled');
      $enabledTypesStr = implode(', ', $enabledTypes);

      // if no financial types are selected, don't return anything from query
      $query .= $enabledTypesStr
        ? " AND i.financial_type_id IN ({$enabledTypesStr})"
        : " AND 0";
    }

    $dao = CRM_Core_DAO::executeQuery($query);
    while ($dao->fetch()) {
      if (isset($result[$dao->id])) {
        $item = static::getLineItemName($dao->entity_table);

        $lineItem = [
          'item'           => $item,
          'description'    => $dao->label,
          'financial_type' => $dao->name,
          'amount'         => CRM_Utils_Money::format(
            $dao->line_total, $dao->currency
          ),
          'qty'            => (int) $dao->qty,
        ];
        $result[$dao->id]['line_items'][] = $lineItem;
      }
    }
  }

  /**
   * @param float|int $contributionAmt
   * @param int $contributionID
   *
   * @return float|int
   */
  private static function getGiftAidableContribAmt($contributionAmt, $contributionID) {
    if ((bool) CRM_Civigiftaid_Settings::getValue('globally_enabled')) {
      return $contributionAmt;
    }
    return self::getContribAmtForEnabledFinanceTypes($contributionID);
  }

}
