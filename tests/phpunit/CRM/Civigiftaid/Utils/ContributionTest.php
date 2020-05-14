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
class CRM_Civigiftaid_Utils_ContributionTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface {


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
   */
  public function testCreateContribWithApi3SetsCustomData() {
    $this->setupFixture1();

    $contributionGiftAidField = CRM_Civigiftaid_Utils::getCustomByName('Eligible_For_Gift_Aid', 'Gift_Aid');
    $contactGiftAidField = CRM_Civigiftaid_Utils::getCustomByName('Eligible_For_Gift_Aid', 'Gift_Aid_Declaration');

    // Create contribution using API3
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
    $this->assertEquals(1, $contributions['Gift_Aid.Eligible_for_Gift_Aid'] ?? NULL, "Expect contribution to be eligible.");
    $this->assertEquals('', $contributions['Gift_Aid.Batch_Name'] ?? NULL, "Expected empty batch name");
    $this->assertEquals(100, $contributions['Gift_Aid.Amount'] ?? NULL, "Expected amount eligible to be calculated");
    $this->assertEquals(25, $contributions['Gift_Aid.Gift_Aid_Amount'] ?? NULL, "Expected amount claimable to be calculated");
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

  /**
   * Test contribution eligibility in various situations
   *
   * @dataProvider contributionEligibilityCalcsCases
   */
  public function testContributionEligibilityCalcs($label, $settings, $orderCreateParams, $expectations) {

    $this->setupFixture1();

    // Apply settings.
    CRM_Civigiftaid_Settings::save($settings);

    // Create contribution with order api
    // Merge in common fields:
    $orderCreateParams += [
      'contact_id'             => $this->contacts[0]['id'],
      'total_amount'           => 100,
      'contribution_status_id' => 'Pending',
    ];
    $contributionID = civicrm_api3('Order', 'create', $orderCreateParams)['id'] ?? NULL;
    /*
    // Can't use API4 at the mo as Order is not implemented.
    $contributionID = \Civi\Api4\Contribution::create()
      ->setCheckPermissions(FALSE)
      ->addValue('contact_id', $this->contacts[0]['id'])
      ->addValue('financial_type_id', $params['financial_type_id'])
      ->addValue('total_amount', 100)
      // ->addValue('Gift_Aid.Eligible_for_Gift_Aid', 1)
      ->execute()[0]['id'] ?? 0;
     */
    $this->assertGreaterThan(0, $contributionID);

    // Re-fetch the contribution details.
    $contribution = \Civi\Api4\Contribution::get()
      ->setCheckPermissions(FALSE)
      ->addSelect('Gift_Aid.Eligible_for_Gift_Aid', 'Gift_Aid.Amount', 'Gift_Aid.Gift_Aid_Amount', 'Gift_Aid.Batch_Name')
      ->addWhere('id', '=', $contributionID)
      ->execute()->first();

    $this->assertEquals($expectations['eligibility'], $contribution['Gift_Aid.Eligible_for_Gift_Aid'] ?? NULL);
    $this->assertEquals($expectations['eligible_amount'], $contribution['Gift_Aid.Amount'] ?? NULL);
    $this->assertEquals($expectations['ga_worth'], $contribution['Gift_Aid.Gift_Aid_Amount'] ?? NULL);
    $this->assertEquals('', $contribution['Gift_Aid.Batch_Name'] ?? NULL);
  }
  /**
   * Provides datasets for testContributionEligibilityCalcs
   *
   * @return array
   */
  public function contributionEligibilityCalcsCases() {
    return [
      [
        'test globally-set eligibility',
        [ 'globally_enabled' => 1, 'financial_types_enabled'  => '' ],
        [ 'financial_type_id' => 1 ],
        [ 'eligibility' => 1, 'eligible_amount' => 100, 'ga_worth' => 25 ]
      ],

      [
        'test donation (eligible)',
        [ 'globally_enabled' => 0, 'financial_types_enabled'  => '1' ],
        [ 'financial_type_id' => 1 ],
        [ 'eligibility' => 1, 'eligible_amount' => 100, 'ga_worth' => 25 ]
      ],

      [
        'test Event Fee (not eligible)',
        [ 'globally_enabled' => 0, 'financial_types_enabled'  => '1' ],
        [ 'financial_type_id' => 4 ],
        [ 'eligibility' => 0, 'eligible_amount' => NULL, 'ga_worth' => NULL ]
      ],

      [
        'test mixed Line Items Event Fee when main fin type is eligible',
        [ 'globally_enabled' => 0, 'financial_types_enabled'  => '1' ],
        [
          'financial_type_id' => 1, //xxx why do we need to set this here?
          'line_items' => [
            [
              'params' => [],
              'line_item' => [
                // The donation
                [ 'line_total' => 20, 'financial_type_id' => 1, 'price_field_id' => 1, 'qty' =>1 ],
                // The event fee
                [ 'line_total' => 80, 'financial_type_id' => 4, 'price_field_id' => 1, 'qty' =>1 ],
              ]
            ]
          ]
        ],
        [ 'eligibility' => 1, 'eligible_amount' => 20, 'ga_worth' => 5 ]
      ],

      [
        'test mixed Line Items Event Fee when main fin type is NOT eligible',
        [ 'globally_enabled' => 0, 'financial_types_enabled'  => '1' ],
        [
          'financial_type_id' => 4, //xxx why do we need to set this here?
          'line_items' => [
            [
              'params' => [],
              'line_item' => [
                // The donation
                [ 'line_total' => 20, 'financial_type_id' => 1, 'price_field_id' => 1, 'qty' =>1 ],
                // The event fee
                [ 'line_total' => 80, 'financial_type_id' => 4, 'price_field_id' => 1, 'qty' =>1 ],
              ]
            ]
          ]
        ],
        [ 'eligibility' => 1, 'eligible_amount' => 20, 'ga_worth' => 5 ]
      ],
    ];
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
      'financial_types_enabled' => [1], // Just donations.
    ]);

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
