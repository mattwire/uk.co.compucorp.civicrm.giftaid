<?php

/**
 * https://civicrm.org/license
 */

abstract class CRM_Civigiftaid_Utils_Hook extends CRM_Utils_Hook {

  /**
   * This hook allows filtering contributions for gift-aid
   * @param bool    $isEligible eligibilty already detected if getDeclaration() method.
   * @param integer $contactID  contact being checked
   * @param date    $date  date gift-aid declaration was made on
   * @param $contributionID  contribution id if any being referred
   *
   * @return mixed
   */
  public static function giftAidEligible(&$isEligible, $contactID, $date = NULL, $contributionID = NULL) {
    return self::singleton()->invoke(4, $isEligible, $contactID, $date, $contributionID, self::$_nullObject, self::$_nullObject, 'civicrm_giftAidEligible');
  }

  /**
   * This hook allows doing any extra processing for contributions that are added to a batch.
   *
   * @access public
   */

  /**
   * This hook allows doing any extra processing for contributions that are added to a batch.
   *
   * @param       $batchID
   * @param array $contributionsAdded Contribution ids that have been batched
   *
   * @return mixed
   */
  public static function batchContributions( $batchID, $contributionsAdded ) {
    return self::singleton()->invoke(2, $batchID, $contributionsAdded, self::$_nullObject, self::$_nullObject, self::$_nullObject, self::$_nullObject, 'civicrm_batchContributions');
  }

  /**
   * This hook allows altering getDeclaration() query
   * @param string $query  declaration query
   * @param array  $queryParams  params required by query
   *
   * @return mixed
   *
   */
  public static function alterDeclarationQuery( &$query, &$queryParams ) {
    return self::singleton()->invoke(2, $query, $queryParams, self::$_nullObject, self::$_nullObject, self::$_nullObject, self::$_nullObject, 'civicrm_alterDeclarationQuery');
  }

}
