<?php

use CRM_Civigiftaid_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Tests for CRM/Civigiftaid/Utils/Contribution.php class
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
class CRM_Civigiftaid_Utils_ContributionTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {


  /** @var array */
  protected $contacts = [];
  /** @var array */
  protected $contributions = [];
  /** @var array */
  protected $declarations = [];

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://docs.civicrm.org/dev/en/latest/testing/phpunit/#civitest
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp() {
    parent::setUp();
  }

  public function tearDown() {
    parent::tearDown();
  }

  /**
   */
  public function testValidateContribToBatch() {
    $this->setupFixture1();

    $contributionGiftAidField = CRM_Civigiftaid_Utils::getCustomByName('Eligible_For_Gift_Aid', 'Gift_Aid');
    $contactGiftAidField = CRM_Civigiftaid_Utils::getCustomByName('Eligible_For_Gift_Aid', 'Gift_Aid_Declaration');

    // Create contribution
    // This doesn't work with api4 so we use api3
    $contributionID = civicrm_api3('Contribution', 'create', [
      'contact_id' => $this->contacts[0]['id'],
      'financial_type_id' => 1,
      'total_amount' => 100,
      $contributionGiftAidField => 1,
    ])['id'];
    $this->assertGreaterThan(0, $contributionID);

    // Re-fetch the contribution details.
    $contributions = \Civi\Api4\Contribution::get()
      ->setCheckPermissions(FALSE)
      ->addSelect('Gift_Aid.Eligible_for_Gift_Aid', 'Gift_Aid.Amount', 'Gift_Aid.Gift_Aid_Amount', 'Gift_Aid.Batch_Name')
      ->addWhere('id', '=', $contributionID)
      ->execute()[0];

    //$this->dump();
    $this->assertEquals(1, $contributions['Gift_Aid.Eligible_for_Gift_Aid'] ?? NULL);
    $this->assertEquals(100, $contributions['Gift_Aid.Amount'] ?? NULL);
    $this->assertEquals(25, $contributions['Gift_Aid.Gift_Aid_Amount'] ?? NULL);
    $this->assertEquals('', $contributions['Gift_Aid.Batch_Name'] ?? NULL);

    //$this->assertNull($contributions['Gift_Aid.Amount'] ?? NULL);
    //$this->assertEquals(NULL, $contributions['Gift_Aid.Gift_Aid_Amount'] ?? NULL);
    //$this->assertEquals(NULL, $contributions['Gift_Aid.Gift_Batch_Name'] ?? NULL);

    // CRM_Civigiftaid_Utils::getCustomByName('batch_name', 'Gift_Aid'),
    // CRM_Civigiftaid_Utils::getCustomByName('Eligible_for_Gift_Aid', 'Gift_Aid'),
  }

  /**
   * When creating a contribution with API4 for some reason it
   * does not seem to save the custom fields.
   * Not sure if this is to do with this extension or api4
   */
  public function testCreateContribWithApi4SetsCustomData() {
    $this->setupFixture1();

    // Create contribution
    $contributionID = \Civi\Api4\Contribution::create()
      ->setCheckPermissions(FALSE)
      ->addValue('contact_id', $this->contacts[0]['id'])
      ->addValue('financial_type_id', 1)
      ->addValue('total_amount', 100)
      ->addValue('Gift_Aid.Eligible_for_Gift_Aid', 1)
      ->execute()[0]['id'] ?? 0;
    $this->assertGreaterThan(0, $contributionID);

    // Re-fetch the contribution details.
    $contributions = \Civi\Api4\Contribution::get()
      ->setCheckPermissions(FALSE)
      ->addSelect('Gift_Aid.Eligible_for_Gift_Aid', 'Gift_Aid.Amount', 'Gift_Aid.Gift_Aid_Amount', 'Gift_Aid.Batch_Name')
      ->addWhere('id', '=', $contributionID)
      ->execute()[0];

    $this->assertEquals(1, $contributions['Gift_Aid.Eligible_for_Gift_Aid'] ?? NULL);
    $this->assertEquals(100, $contributions['Gift_Aid.Amount'] ?? NULL);
    $this->assertEquals(25, $contributions['Gift_Aid.Gift_Aid_Amount'] ?? NULL);
    $this->assertEquals('', $contributions['Gift_Aid.Batch_Name'] ?? NULL);
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

    // Create a contact.
    $result = \Civi\Api4\Contact::create()
      ->setCheckPermissions(FALSE)
      ->addValue('contact_type', 'Individual')
      ->addValue('display_name', 'Test 123')
      ->execute()[0];
    $this->contacts[] = $result;
    $contactID = $this->contacts[0]['id'];

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

    // Set globally enabled.
    Civi::settings()->set('civigiftaid_globally_enabled', 1);
  }

}
