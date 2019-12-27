<?php
/**
 * https://civicrm.org/licensing
 */

/**
 * Class CRM_Civigiftaid_Utils_GiftAid
 */
class CRM_Civigiftaid_Utils_GiftAid {

  // Giftaid Declaration Options.
  const DECLARATION_IS_YES = 1;
  const DECLARATION_IS_NO = 0;
  const DECLARATION_IS_PAST_4_YEARS = 3;

  /**
   * Get Gift Aid declaration record for Individual.
   *
   * @param int    $contactID - the Individual for whom we retrieve declaration
   * @param date   $date      - date for which we retrieve declaration (in ISO date format)
   *       - e.g. the date for which you would like to check if the contact has a valid declaration
   *
   * @return array            - declaration record as associative array, else empty array.
   */
  public static function getDeclaration($contactID, $date = NULL, $charity = NULL) {
    static $charityColumnExists = NULL;

    if (is_null($date)) {
      $date = date('YmdHis');
    }

    if ($charityColumnExists === NULL) {
      $charityColumnExists = CRM_Core_BAO_SchemaHandler::checkIfFieldExists('civicrm_value_gift_aid_declaration', 'charity');
    }
    $charityClause = '';
    if ($charityColumnExists) {
      $charityClause = $charity ? " AND charity='{$charity}'" : " AND (charity IS NULL OR charity = '')";
    }

    // Get current declaration: start_date in past, end_date in future or NULL
    // - if > 1, pick latest end_date
    $currentDeclaration = [];
    $sql = "
        SELECT id, entity_id, eligible_for_gift_aid, start_date, end_date, reason_ended, source, notes
        FROM   civicrm_value_gift_aid_declaration
        WHERE  entity_id = %1 AND start_date <= %2 AND (end_date > %2 OR end_date IS NULL) {$charityClause}
        ORDER BY end_date DESC";
    $sqlParams = [
      1 => [$contactID, 'Integer'],
      2 => [$date, 'Timestamp'],
    ];
    // allow query to be modified via hook
    CRM_Civigiftaid_Utils_Hook::alterDeclarationQuery($sql, $sqlParams);
    $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
    if ($dao->fetch()) {
      $currentDeclaration['id'] = (int) $dao->id;
      $currentDeclaration['entity_id'] = (int) $dao->entity_id;
      $currentDeclaration['eligible_for_gift_aid'] = (int) $dao->eligible_for_gift_aid;
      $currentDeclaration['start_date'] = $dao->start_date;
      $currentDeclaration['end_date'] = $dao->end_date;
      $currentDeclaration['reason_ended'] = $dao->reason_ended;
      $currentDeclaration['source'] = $dao->source;
      $currentDeclaration['notes'] = $dao->notes;
    }
    return $currentDeclaration;
  }

  /**
   * @param array $contribution
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public static function isEligibleForGiftAid($contribution) {
    $isContributionEligible = self::isContributionEligible($contribution);

    // hook can alter the eligibility if needed
    CRM_Civigiftaid_Utils_Hook::giftAidEligible($isContributionEligible, $contribution['contact_id'], $contribution['receive_date'], $contribution['id']);
    return $isContributionEligible;
  }

  /**
   * Create / update Gift Aid declaration records on Individual when
   * "Eligible for Gift Aid" field on Contribution is updated.
   *
   * @param array  $params    - fields to store in declaration:
   *               - entity_id:  the Individual for whom we will create/update declaration
   *               - eligible_for_gift_aid: 1 for positive declaration, 0 for negative
   *               - start_date: start date of declaration (in ISO date format)
   *               - end_date:   end date of declaration (in ISO date format)
   *
   * @return array   TODO
   */
  public static function setDeclaration($params) {
    static $charityColumnExists = NULL;

    if (!CRM_Utils_Array::value('entity_id', $params)) {
      return([
        'is_error' => 1,
        'error_message' => 'entity_id is required',
      ]);
    }
    $charity = CRM_Utils_Array::value('charity', $params);

    // Retrieve existing declarations for this user.
    $currentDeclaration = CRM_Civigiftaid_Utils_GiftAid::getDeclaration($params['entity_id'], $params['start_date'], $charity);
    $partialDeclarationID = CRM_Civigiftaid_Utils_GiftAid::getPartialDeclaration($params['entity_id']);
    if ($currentDeclaration && $partialDeclarationID) {
      // We've got partial declarations (no post_code, no start_date) and a current (valid) declaration so delete the partials
      CRM_Civigiftaid_Utils_GiftAid::deletePartialDeclaration($params['entity_id']);
    }
    elseif ($partialDeclarationID) {
      $params['id'] = $partialDeclarationID;
    }

    $charityClause = '';
    if ($charityColumnExists === NULL) {
      $charityColumnExists = CRM_Core_BAO_SchemaHandler::checkIfFieldExists('civicrm_value_gift_aid_declaration', 'charity');
    }
    if ($charityColumnExists) {
      $charityClause = $charity ? " AND charity='{$charity}'" : " AND (charity IS NULL OR charity = '')";
    }

    // Get future declarations: start_date in future, end_date in future or NULL
    // - if > 1, pick earliest start_date
    $futureDeclaration = [];
    $sql = "
        SELECT id, eligible_for_gift_aid, start_date, end_date
        FROM   civicrm_value_gift_aid_declaration
        WHERE  entity_id = %1 AND start_date > %2 AND (end_date > %2 OR end_date IS NULL) {$charityClause}
        ORDER BY start_date";
    $dao = CRM_Core_DAO::executeQuery($sql, [
      1 => [$params['entity_id'], 'Integer'],
      2 => [$params['start_date'], 'Timestamp'],
    ]);
    if ($dao->fetch()) {
      $futureDeclaration['id'] = (int) $dao->id;
      $futureDeclaration['eligible_for_gift_aid'] = (int) $dao->eligible_for_gift_aid;
      $futureDeclaration['start_date'] = $dao->start_date;
      $futureDeclaration['end_date'] = $dao->end_date;
    }

    $specifiedEndTimestamp = NULL;
    if (CRM_Utils_Array::value('end_date', $params)) {
      $specifiedEndTimestamp = strtotime(CRM_Utils_Array::value('end_date', $params));
    }

    // Calculate new_end_date for negative declaration
    // - new_end_date =
    //   if end_date specified then (specified end_date)
    //   else (start_date of first future declaration if any, else NULL)
    $futureTimestamp = NULL;
    if (CRM_Utils_Array::value('start_date', $futureDeclaration)) {
      $futureTimestamp = strtotime(CRM_Utils_Array::value('start_date', $futureDeclaration));
    }

    if ($specifiedEndTimestamp) {
      $endTimestamp = $specifiedEndTimestamp;
    } else if ($futureTimestamp) {
      $endTimestamp = $futureTimestamp;
    } else {
      $endTimestamp = NULL;
    }

    switch ($params['eligible_for_gift_aid']) {
      case self::DECLARATION_IS_YES:
        if (!$currentDeclaration) {
          // There is no current declaration so create new.
          CRM_Civigiftaid_Utils_GiftAid::insertDeclaration($params);
        }
        elseif ($currentDeclaration['eligible_for_gift_aid'] === self::DECLARATION_IS_YES && $endTimestamp) {
          //   - if current positive, extend its end_date to new_end_date.
          $updateParams = [
            'id' => $currentDeclaration['id'],
            'end_date' => date('YmdHis', $endTimestamp),
          ];
          CRM_Civigiftaid_Utils_GiftAid::updateDeclaration($updateParams);

        }
        elseif ($currentDeclaration['eligible_for_gift_aid'] === self::DECLARATION_IS_NO || $currentDeclaration['eligible_for_gift_aid'] === self::DECLARATION_IS_PAST_4_YEARS) {
          //   - if current negative, set its end_date to now and create new ending new_end_date.
          $params['id'] = $currentDeclaration['id'];
          $params['end_date'] = $params['start_date'];
          CRM_Civigiftaid_Utils_GiftAid::insertDeclaration($params);
        }
        break;

      case self::DECLARATION_IS_PAST_4_YEARS:
        if (!$currentDeclaration) {
          // There is no current declaration so create new.
          CRM_Civigiftaid_Utils_GiftAid::insertDeclaration($params);
        }
        elseif ($currentDeclaration['eligible_for_gift_aid'] === self::DECLARATION_IS_PAST_4_YEARS && $endTimestamp) {
          //   - if current positive, extend its end_date to new_end_date.
          $updateParams = [
            'id' => $currentDeclaration['id'],
            'end_date' => date('YmdHis', $endTimestamp),
          ];
          CRM_Civigiftaid_Utils_GiftAid::updateDeclaration($updateParams);

        }
        elseif ($currentDeclaration['eligible_for_gift_aid'] === self::DECLARATION_IS_NO || $currentDeclaration['eligible_for_gift_aid'] === self::DECLARATION_IS_YES) {
          //   - if current negative, set its end_date to now and create new ending new_end_date.
          $params['id'] = $currentDeclaration['id'];
          $params['end_date'] = $params['start_date'];
          CRM_Civigiftaid_Utils_GiftAid::insertDeclaration($params);
        }
        break;

      case self::DECLARATION_IS_NO:
        if (!$currentDeclaration) {
          // There is no current declaration so create new.
          CRM_Civigiftaid_Utils_GiftAid::insertDeclaration($params);
        }
        elseif ($currentDeclaration['eligible_for_gift_aid'] === self::DECLARATION_IS_YES || $currentDeclaration['eligible_for_gift_aid'] === self::DECLARATION_IS_PAST_4_YEARS) {
          // If current declaration is "Yes", set its end_date to now and create new ending new_end_date.
          $params['id'] = $currentDeclaration['id'];
          $params['end_date'] = $params['start_date'];
          CRM_Civigiftaid_Utils_GiftAid::insertDeclaration($params);
        }
        break;

        // If current negative, leave as is.
    }

    return [
      'is_error' => 0,
    ];
  }

  /**
   * Private helper function for setDeclaration
   * - update a declaration record.
   *
   * @param array $params
   */
  private static function updateDeclaration($params) {
    // Update (currently we only need to update end_date but can make generic)
    // $params['end_date'] should by in date('YmdHis') format
    $sql = "
        UPDATE civicrm_value_gift_aid_declaration
        SET    end_date = %1
        WHERE  id = %2";
    CRM_Core_DAO::executeQuery($sql, [
      1 => [$params['end_date'], 'Timestamp'],
      2 => [$params['id'], 'Integer'],
    ]);
  }

  /**
   * Private helper function for setDeclaration
   * - insert a declaration record.
   *
   * @param array $params
   * @param string $endTimestamp
   */
  private static function insertDeclaration($params) {
    static $charityColumnExists = NULL;
    if ($charityColumnExists === NULL) {
      $charityColumnExists = CRM_Core_BAO_SchemaHandler::checkIfFieldExists('civicrm_value_gift_aid_declaration', 'charity');
    }
    if (!CRM_Utils_Array::value('charity', $params)) {
      $charityColumnExists = FALSE;
    }

    $cols = [
      'entity_id' => 'Integer',
      'eligible_for_gift_aid' => 'Integer',
      'address' => 'String',
      'post_code' => 'String',
      'start_date' => 'Timestamp',
      'end_date' => 'Timestamp',
      'reason_ended' => 'String',
      'source' => 'String',
      'notes' => 'String',
    ];
    if ($charityColumnExists) {
      $cols['charity'] = 'String';
    }

    if (CRM_Utils_Array::value('id', $params)) {
      $keyVals = [];
      foreach ($cols as $colName => $colType) {
        if (isset($params[$colName])) {
          $keyVals[] = "{$colName}='{$params[$colName]}'";
        }
      }
      $keyValsString = implode(',', $keyVals);
      $queryParams = [];
      $cols['id'] = 'Integer';

      $query = "UPDATE civicrm_value_gift_aid_declaration SET {$keyValsString} WHERE id={$params['id']}";
    }
    else {
      $count = 1;
      foreach ($cols as $colName => $colType) {
        $insertVals[$colName] = CRM_Utils_Array::value($colName, $params, '');
        $values[] = "%{$count}";
        $queryParams[$count] = [
          CRM_Utils_Array::value($colName, $params, ''),
          $colType
        ];
        $count++;
      }

      $query = "INSERT INTO civicrm_value_gift_aid_declaration (" . implode(',', array_keys($insertVals)) . ") VALUES (" . implode(',', $values) . ")";
    }

    // Insert
    CRM_Core_DAO::executeQuery($query, $queryParams);
  }

  /**
   * Get all contacts that have a giftaid declaration
   *
   * @return array
   */
  public static function getContactsWithDeclarations() {
    $contactsWithDeclarations = [];
    $sql = "
        SELECT id, eligible_for_gift_aid, entity_id
        FROM   civicrm_value_gift_aid_declaration
        GROUP BY entity_id";

    $dao = CRM_Core_DAO::executeQuery($sql);
    foreach($dao->fetchAll() as $row){
      $contactsWithDeclarations[] = $row['entity_id'];
    }

    return $contactsWithDeclarations;
  }

  /**
   * @param array $contacts
   *
   * @return array
   */
  public static function getCurrentDeclarations($contacts) {
    $currentDeclarations = [];

    foreach($contacts as $contactId) {
      $currentDeclarations[] = self::getDeclaration($contactId);
    }

    return $currentDeclarations;
  }

  /**
   * @param array $params
   */
  public static function setSubmission($params) {
    $sql = "SELECT * FROM civicrm_value_gift_aid_submission where entity_id = %1";
    $sqlParams = [1 => [$params['entity_id'], 'Integer']];
    $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
    $count = count($dao->fetchAll());
    if ($count == 0) {
      // Insert
      $sql = "
        INSERT INTO civicrm_value_gift_aid_submission (entity_id, eligible_for_gift_aid, amount, gift_aid_amount, batch_name)
        VALUES (%1, %2, NULL, NULL, NULL)";
    }
    else {
      // Update
      $sql = "
        UPDATE civicrm_value_gift_aid_submission
        SET eligible_for_gift_aid = %2
        WHERE entity_id = %1";
    }
    $queryParams = [
      1 => [$params['entity_id'], 'Integer'],
      2 => [$params['eligible_for_gift_aid'], 'Integer'],
    ];
    CRM_Core_DAO::executeQuery($sql, $queryParams);
  }

  /**
   * @param array $declarations
   * @param int $limit
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public static function getContributionsByDeclarations($declarations = [], $limit = 100) {
    $contributionsToSubmit = [];

    foreach($declarations as $declaration) {
      $dateRange = [];

      $contactId = $declaration['entity_id'];
      $startDate = $declaration['start_date'];
      $dateRange[0] = self::dateFourYearsAgo($startDate);
      $dateRange[1] = $startDate;
      $contributions = self::getContributionsByDateRange($contactId, $dateRange);
      $contributionsToSubmit = array_merge($contributions, $contributionsToSubmit);

      if(count($contributionsToSubmit) >= $limit) {
        $contributionsToSubmit = array_slice($contributionsToSubmit, 0, $limit);
        break;
      }
    }
    return $contributionsToSubmit;
  }

  /**
   * @param string $startDate
   *
   * @return string
   * @throws \Exception
   */
  public static function dateFourYearsAgo($startDate) {
    $date = new DateTime($startDate);
    $dateFourYearsAgo = $date->modify('-4 year')->format('Y-m-d H:i:s');
    return $dateFourYearsAgo;
  }

  /**
   * @param int $contactId
   * @param string $dateRange
   *
   * @return mixed
   * @throws \CiviCRM_API3_Exception
   */
  public static function getContributionsByDateRange($contactId, $dateRange) {
    if((bool) CRM_Civigiftaid_Settings::getValue('globally_enabled')) {
      $result = civicrm_api3('Contribution', 'get', [
        'sequential' => 1,
        'return' => "financial_type_id,id",
        'contact_id' => $contactId,
        'id' => ['NOT IN' => self::submittedContributions()],
        'receive_date' => ['BETWEEN' => $dateRange],
      ]);
    }else if($financialTypes = (array) CRM_Civigiftaid_Settings::getValue('financial_types_enabled')) {
      $result = civicrm_api3('Contribution', 'get', [
        'sequential' => 1,
        'return' => "financial_type_id,id",
        'contact_id' => $contactId,
        'financial_type_id' => $financialTypes,
        'id' => ['NOT IN' => self::submittedContributions()],
        'receive_date' => ['BETWEEN' => $dateRange],
      ]);
    }
    return $result['values'];
  }

  /**
   * @return array
   */
  public static function submittedContributions() {
    $submittedContributions = [];
    $sql = "
        SELECT entity_id
        FROM   civicrm_value_gift_aid_submission";

    $dao = CRM_Core_DAO::executeQuery($sql);
    foreach($dao->fetchAll() as $row){
      $submittedContributions[] = $row['entity_id'];
    }

    return $submittedContributions;
  }

  /**
   * Get all gift aid declarations made by a contact.
   *
   * @param int $contactID
   * @return bool|array
   */
  public static function getAllDeclarations($contactID) {
    if (!isset(Civi::$statics[__CLASS__][$contactID]['declarations'])) {
      $sql = "SELECT id, entity_id, eligible_for_gift_aid, start_date, end_date, reason_ended, source, notes
              FROM civicrm_value_gift_aid_declaration
              WHERE  entity_id = %1";
      $sqlParams[1] = [$contactID, 'Integer'];

      $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
      Civi::$statics[__CLASS__][$contactID]['declarations'] = $dao->fetchAll();
    }

    return Civi::$statics[__CLASS__][$contactID]['declarations'];
  }

  /**
   * Get the (table) ID of the latest gift aid declaration for a contact
   * When submitting a profile with a (Individual) eligible_for_gift_aid field we get a partial declaration created
   * but we want to create a full declaration
   *
   * @param int $contactID
   *
   * @return bool
   */
  public static function getPartialDeclaration($contactID) {
    $sql = "SELECT id as id, start_date
              FROM civicrm_value_gift_aid_declaration
              WHERE  entity_id = %1 ORDER BY id DESC LIMIT 1";
    $sqlParams = [
      1 => [$contactID, 'Integer']
    ];

    $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
    $dao->fetch();
    if (!empty($dao->id) && empty($dao->start_date)) {
      return $dao->id;
    }

    return FALSE;
  }

  public static function deletePartialDeclaration($contactID) {
    $sql = "DELETE FROM civicrm_value_gift_aid_declaration
              WHERE entity_id = %1 AND post_code IS NULL AND start_date IS NULL";
    $sqlParams = [
      1 => [$contactID, 'Integer'],
    ];

    CRM_Core_DAO::executeQuery($sql, $sqlParams);
  }

  /**
   * Check if Eligibility criteria for Contribution is met.
   *
   * @param array $contribution
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public static function isContributionEligible($contribution) {
    $declarations = self::getAllDeclarations($contribution['contact_id']);
    if (empty($declarations)) {
      return FALSE;
    }

    $groupID = civicrm_api3('CustomGroup', 'getvalue', [
      'return' => "id",
      'name' => "gift_aid",
    ]);

    $contributionEligible = CRM_Utils_Array::value(CRM_Civigiftaid_Utils::getCustomByName('Eligible_for_Gift_Aid', $groupID), $contribution);
    // If it is not set ('') it's not the same as DECLARATION_IS_NO
    if (!empty($contributionEligible) && ($contributionEligible == self::DECLARATION_IS_NO)) {
      return FALSE;
    }

    foreach ($declarations as $declaration) {
      if($declaration['eligible_for_gift_aid'] == self::DECLARATION_IS_PAST_4_YEARS) {
        $declaration['start_date'] = self::dateFourYearsAgo($declaration['start_date']);
      }

      // Convert dates to timestamps.
      $startDateTS = strtotime($declaration['start_date']);
      $endDateTS = !empty($declaration['end_date']) ? strtotime($declaration['end_date']) : NULL;
      $contributionDateTS = strtotime($contribution['receive_date']);

      /**
       * Check between which date the contribution's receive date falls.
       */
      if(!empty($endDateTS)) {
        $contributionDeclarationDateMatchFound =
          ($contributionDateTS >= $startDateTS) && ($contributionDateTS < $endDateTS);
      }
      else {
        $contributionDeclarationDateMatchFound = ($contributionDateTS >= $startDateTS);
      }

      if($contributionDeclarationDateMatchFound == TRUE) {
        return ((bool) $declaration['eligible_for_gift_aid']);
      }
    }
    return FALSE;
  }

}
