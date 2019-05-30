<?php

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Civigiftaid_Form_Admin extends CRM_Core_Form {
  /**
   * Maintains a cache of settings in order to avoid hitting the database on
   * subsequent requests.
   *
   * @var null|array
   */
  private static $settings;

  ////////////////////
  // Public Methods //
  ////////////////////

  /**
   * {@inheritdoc}
   */
  public function buildQuickForm() {
    CRM_Utils_System::setTitle(ts('Gift Aid - Settings'));

    $this->add('checkbox', 'globally_enabled', 'Globally Enabled?');

    $this->add(
      'select',
      'financial_types_enabled',
      'Enabled Financial Types',
      $this->getFinancialTypes(),
      FALSE,
      ['multiple' => TRUE]
    );

    $this->addButtons([
      ['type' => 'submit', 'name' => ts('Save'), 'isDefault' => TRUE],
      ['type' => 'cancel', 'name' => ts('Cancel')],
    ]);

    $this->assign('elementNames', $this->getRenderableElementNames());

    parent::buildQuickForm();
  }

  /**
   * {@inheritdoc}
   */
  public function postProcess() {
    $values = $this->exportValues();

    $settings = new stdClass();
    $settings->globally_enabled = empty($values['globally_enabled']) ? 0 : 1;
    $settings->financial_types_enabled = $values['financial_types_enabled'];

    CRM_Core_BAO_Setting::setItem(
      $settings,
      'Extension',
      'uk.co.compucorp.civicrm.giftaid:settings'
    );

    CRM_Core_Session::setStatus(ts('Settings saved'), '', 'success');

    parent::postProcess();
  }

  /**
   * Initialise settings
   *
   * {@inheritdoc}
   */
  public function setDefaultValues() {
    return static::getSettings();
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = [];
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }

    return $elementNames;
  }

  ///////////////////////////
  // Public Static Methods //
  ///////////////////////////

  /**
   * @return array
   */
  public static function getSettings() {
    if (is_null(static::$settings)) {
      static::$settings = (array) CRM_Core_BAO_Setting::getItem(
        'Extension',
        'uk.co.compucorp.civicrm.giftaid:settings'
      );
    }

    return static::$settings;
  }

  /**
   * @return bool
   */
  public static function isGloballyEnabled() {
    $settings = static::getSettings();

    return (bool) $settings['globally_enabled'];
  }

  /**
   * @return array
   */
  public static function getFinancialTypesEnabled() {
    $settings = static::getSettings();

    return (array) $settings['financial_types_enabled'];
  }

  /////////////////////
  // Private Methods //
  /////////////////////

  /**
   * Get an array of financial types
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private function getFinancialTypes() {
    $result = civicrm_api3('FinancialType', 'get', 
      [
        'sequential' => 1,
        'is_active' => 1,
        'options' => ['limit' => 0],
      ]
    );

    $types = [];
    foreach ($result['values'] as $type) {
      $types[$type['id']] = $type['name'];
    }

    return $types;
  }
}
