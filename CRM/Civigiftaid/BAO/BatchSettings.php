<?php
/**
 * https://civicrm.org/license
 */

class CRM_Civigiftaid_BAO_BatchSettings extends CRM_Civigiftaid_DAO_BatchSettings {

  /**
   * Create a new BatchSettings based on array-data
   *
   * @param array $params key-value pairs
   *
   * @return \CRM_Civigiftaid_BAO_BatchSettings
   * @throws \CRM_Extension_Exception
   * @throws \CiviCRM_API3_Exception
   */
  public static function create($params) {
    static::addDefaults($params);
    static::preProcessParams($params);

    $entityName = 'BatchSettings';
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, $entityName, CRM_Utils_Array::value('id', $params), $params);

    $instance = new static;
    $instance->copyValues($params);
    $instance->save();

    CRM_Utils_Hook::post($hook, $entityName, $instance->id, $instance);

    return $instance;
  }

  public static function findByBatchId($id) {
    $dao = static::executeQuery("SELECT * FROM " . static::getTableName() . " WHERE batch_id = {$id}");

    if ($dao->fetch()) {
      return $dao;
    }

    return FALSE;
  }

  /**
   * Add default value of certain params, if not provided.
   *
   * @param array $params
   *
   * @throws \CRM_Extension_Exception
   * @throws \CiviCRM_API3_Exception
   */
  private static function addDefaults(&$params) {
    if (!isset($params['financial_types_enabled'])) {
      $params['financial_types_enabled'] = (array) CRM_Civigiftaid_Settings::getValue('financial_types_enabled');
    }
    if (!isset($params['globally_enabled'])) {
      $params['globally_enabled'] = (bool) CRM_Civigiftaid_Settings::getValue('globally_enabled');
    }
    if (!isset($params['basic_rate_tax'])) {
      $params['basic_rate_tax'] = CRM_Civigiftaid_Utils_Contribution::getBasicRateTax();
    }
  }

  /**
   * @param array $params
   */
  private static function preProcessParams(&$params) {
    if (is_array($params['financial_types_enabled'])) {
      $params['financial_types_enabled'] = serialize($params['financial_types_enabled']);
    }
  }

}
