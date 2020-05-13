<?php

use CRM_Civigiftaid_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Tests for CRM/Civigiftaid/Utils/Contribution.php class
 *
 * NOTE: these are NOT implementing TransactionalInterface - so we must do all our own cleanup.
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class CRM_Civigiftaid_DeclarationTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface {


  /** @var array */
  protected $contacts = [];
  /** @var array */
  protected $contributions = [];
  /** @var array */
  protected $declarations = [];

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://docs.civicrm.org/dev/en/latest/testing/phpunit/#civitest

    // Set to TRUE to force a reset - but your tests will take forever.
    $forceResetDatabase = FALSE;

    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply($forceResetDatabase);
  }

  public function setUp() {
    parent::setUp();
  }

  public function tearDown() {
    if (!$this->contacts) {
      return;
    }
    $contactIDs = array_keys($this->contacts);

    $contributions = \Civi\Api4\Contribution::get()
      ->addSelect('id', 'contact_id')
      ->addWhere('contact_id', 'IN', $contactIDs)
      ->setCheckPermissions(FALSE)
      ->execute();

    // Delete contributions
    if ($contributions) {
      \Civi\Api4\Contribution::delete()
        ->addWhere('contact_id', 'IN', $contactIDs)
        ->setCheckPermissions(FALSE)
        ->execute();
    }

    // Delete Contacts
    \Civi\Api4\Contact::delete()
      ->addWhere('id', 'IN', array_keys($this->contacts))
      ->setCheckPermissions(FALSE)
      ->execute();

    // Financial records? There's probably other stuff left here.

    parent::tearDown();
  }

  /**
   * CRM_Civigiftaid_Declaration::update is called by the hook_post callback
   * for contributions, and then it calls setDeclaration which makes several
   * other calls to update.
   *
   * The purpose is to update the declaration records for a contact, after
   * having saved a Contribution record, but especially - or primarily - after
   * a contribution page has been submitted.
   *
   * This currently relies on two session variables, which are set as part of
   * the Form code.
   *
   * @todo this should be moved out of the form layer.
   *
   */
  public function testDeclarationUpdateWhenNotUk() {

    // First, where there is no declaration.
    $this->setupFixture1();

    $session = CRM_Core_Session::singleton();
    // Set not uk
    $session->set('uktaxpayer', 0, E::LONG_NAME);
    $session->set('postProcessTitle', 'testDeclarationUpdate', E::LONG_NAME);
    // Create the contribution which should trigger storing a declaration.

    // Create (eligible) contribution
    $contributionID = \Civi\Api4\Contribution::create()
      ->setCheckPermissions(FALSE)
      ->addValue('contact_id', $this->contacts[0]['id'])
      ->addValue('financial_type_id', 1)
      ->addValue('total_amount', 100)
      ->execute()[0]['id'] ?? 0;
    $this->assertGreaterThan(0, $contributionID);

    // Check for declarations.
    $declarations = CRM_Civigiftaid_Declaration::getAllDeclarations($this->contacts[0]['id']);
    $this->assertEquals([], $declarations);

    /*
   * @param array  $newParams    - fields to store in declaration:
   *               - entity_id:  the Individual for whom we will create/update declaration
   *               - eligible_for_gift_aid: 3=Yes+past 4 years,1=Yes,0=No
   *               - start_date: start date of declaration (in ISO date format)
   *               - end_date:   end date of declaration (in ISO date format)
   *               */

  }
  /**
   */
  public function testDeclarationUpdateFirstDeclarationEver() {

    // First, where there is no declaration.
    $this->setupFixture1();

    $session = CRM_Core_Session::singleton();
    // Set not uk
    $session->set('uktaxpayer', 1, E::LONG_NAME);
    $session->set('postProcessTitle', 'testDeclarationUpdate', E::LONG_NAME);
    // Create the contribution which should trigger storing a declaration.

    // Create (eligible) contribution
    $contributionID = \Civi\Api4\Contribution::create()
      ->setCheckPermissions(FALSE)
      ->addValue('contact_id', $this->contacts[0]['id'])
      ->addValue('financial_type_id', 1)
      ->addValue('total_amount', 100)
      ->execute()[0]['id'] ?? 0;
    $this->assertGreaterThan(0, $contributionID);

    // Check for declarations.
    $declarations = CRM_Civigiftaid_Declaration::getAllDeclarations($this->contacts[0]['id']);
    $this->assertEquals([], $declarations);

    /*
   * @param array  $newParams    - fields to store in declaration:
   *               - entity_id:  the Individual for whom we will create/update declaration
   *               - eligible_for_gift_aid: 3=Yes+past 4 years,1=Yes,0=No
   *               - start_date: start date of declaration (in ISO date format)
   *               - end_date:   end date of declaration (in ISO date format)
   *               */

  }
  protected function dump() {

    $customFieldID = CRM_Core_BAO_CustomField::getCustomFieldID('Eligible_for_Gift_Aid', 'Gift_Aid');
    $customContribTableName = CRM_Core_BAO_CustomField::getTableColumnGroup($customFieldID)[0];

    $dao = CRM_Core_DAO::executeQuery("SELECT id, contact_type, display_name FROM civicrm_contact ORDER BY id");
    echo "\nContacts:\n";
    while ($dao->fetch()) {
      print "  $dao->id: $dao->contact_type $dao->display_name\n";
    }
    print "\n";
    $sql = "
      SELECT c.id, c.contact_id, c.receive_date, c.total_amount,
          ga.id ga_id, ga.eligible_for_gift_aid, ga.amount ga_amount, ga.gift_aid_amount, ga.batch_name
      FROM civicrm_contribution c
      LEFT JOIN $customContribTableName ga ON ga.entity_id = c.id
      ORDER BY c.id";
    $dao = CRM_Core_DAO::executeQuery($sql);
    echo "\nContributions:\n";
    while ($dao->fetch()) {
      $_ = $dao->toArray();
      foreach ($_ as $k => $v) {
        print "  $k: " . json_encode($v) ."\n";
      }
      print "\n";
    }
    print "\n";


    $sql = "
      SELECT *
      FROM $customContribTableName
      ORDER BY id";
    $dao = CRM_Core_DAO::executeQuery($sql);
    echo "\n$customContribTableName :\n";
    while ($dao->fetch()) {
      $_ = $dao->toArray();
      foreach ($_ as $k => $v) {
        print "  $k: " . json_encode($v) ."\n";
      }
      print "\n";
    }
    print "\n";

  }
  /**
   */
  protected function setupFixture1() {

    $r = civicrm_api3('FinancialType', 'get', []);
    $this->assertEquals('Donation', $r['values'][1]['name'], "Test assumes fin type 1 is donation but it is not.");
    $this->assertEquals('Event Fee', $r['values'][4]['name'], "Test assumes fin type 4 is event fee but it is not.");

    // Mark Donation as an eligible type, (and event fee as not), and globally eligible for now.
    //$financialTypesAvailable = (Array) CRM_Civigiftaid_Settings::get('financial_types_enabled');
    CRM_Civigiftaid_Settings::save([
      'globally_enabled' => 1,
      'financial_types_enabled' => '1', // Just donations.
    ]);

    // Create a contact.
    $result = \Civi\Api4\Contact::create()
      ->setCheckPermissions(FALSE)
      ->addValue('contact_type', 'Individual')
      ->addValue('display_name', 'Test 123')
      ->execute()[0];
    $this->contacts[] = $result;
    $contactID = $this->contacts[0]['id'];
    return;

    /*
    // Create a declaration for this contact.
    // Nb. as of CiviCRM 5.25 this API doesn't return anything useful like the ID of the created declaration.
    \Civi\Api4\CustomValue::create('Gift_Aid_Declaration')
      ->addValue('entity_id', $contactID)
      ->addValue('Eligible_for_Gift_Aid', 1)
      ->addValue('Address', 'somewhere')
      ->addValue('Post_Code', 'SW1A 0AA')
      ->addValue('Start_Date', '2020-01-01')
      ->addValue('Source', 'test 1')
      ->execute();

     */
  }

}
