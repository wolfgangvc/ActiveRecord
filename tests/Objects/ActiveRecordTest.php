<?php
/**
 * Created by PhpStorm.
 * User: Baggett
 * Date: 09/02/2015
 * Time: 15:33
 */

use \Thru\ActiveRecord\Test\TestModel;
use \Thru\ActiveRecord\Test\TestModelWithNameLabel;
use \Thru\ActiveRecord\Test\TestModelSortable;
use Thru\ActiveRecord\Test\TestModelSearchOnly;
use \Faker;

class ActiveRecordTest extends PHPUnit_Framework_TestCase {

  /** @var $faker \Faker\Generator */
  private $faker;

  public function setUp() {
    $this->faker = Faker\Factory::create();
    $this->faker->addProvider(new Faker\Provider\Company($this->faker));
    $this->faker->addProvider(new Faker\Provider\Lorem($this->faker));
    $this->faker->addProvider(new Faker\Provider\DateTime($this->faker));
  }

  public function tearDown(){
    TestModel::delete_table();
    TestModelWithNameLabel::delete_table();
    TestModelSortable::delete_table();
  }

  public function testConstruct(){
    $test_model = new TestModel();
    $this->assertEquals("Thru\\ActiveRecord\\Test\\TestModel", get_class($test_model));
    $this->assertTrue(in_array("Thru\\ActiveRecord\\ActiveRecord", class_parents($test_model)));
  }

  public function testSearchEmptyResult(){
    $this->assertEquals(0, TestModel::search()->count());
  }

  public function testCreate(){
    /* @var $test_model TestModel */
    /* @var $result_object TestModel */
    $test_model = TestModel::factory();
    $test_model->integer_field = $this->faker->numberBetween(0, 9999999);
    $test_model->text_field = $this->faker->paragraph(5);
    $test_model->date_field = $this->faker->date("Y-m-d H:i:s");
    $result_object = $test_model->save();
    $this->assertEquals("Thru\\ActiveRecord\\Test\\TestModel", get_class($result_object));

    $this->assertEquals($test_model->integer_field, $result_object->integer_field);
    $this->assertEquals($test_model->text_field, $result_object->text_field);
    $this->assertEquals($test_model->date_field, $result_object->date_field);

    $this->assertGreaterThan(0, $test_model->test_model_id, "Verify updated old object id");
    $this->assertGreaterThan(0, $result_object->test_model_id, "Verify new object id");
    $this->assertEquals($test_model->test_model_id, $result_object->test_model_id, "Verify new and old are same");
  }

  public function testSearchOneResult(){
    /* @var $test_model TestModel */
    /* @var $result_object TestModel */
    $test_model = TestModel::factory();
    $test_model->integer_field = $this->faker->numberBetween(0, 9999999);
    $test_model->text_field = $this->faker->paragraph(5);
    $test_model->date_field = $this->faker->date("Y-m-d H:i:s");
    $test_model->save();

    $this->assertEquals(1, TestModel::search()->count());

    $result_object = TestModel::search()->where('test_model_id', $test_model->test_model_id)->execOne();
    $this->assertEquals("Thru\\ActiveRecord\\Test\\TestModel", get_class($result_object));
    $this->assertEquals($test_model->integer_field, $result_object->integer_field);
    $this->assertEquals($test_model->text_field, $result_object->text_field);
    $this->assertEquals($test_model->date_field, $result_object->date_field);
    $this->assertEquals(1, $result_object->test_model_id);

    return $test_model;
  }

  public function testSearchInvalid(){
    $this->assertFalse(TestModel::search()->where('test_model_id', -1)->execOne());
  }

  /**
   * @depends testSearchOneResult
   * @param \Thru\ActiveRecord\Test\TestModel $test_model
   */
  public function testLabels(TestModel $test_model){
    $this->assertEquals("No label for Thru\\ActiveRecord\\Test\\TestModel ID 1", $test_model->get_label());

    $with_name_label = new \Thru\ActiveRecord\Test\TestModelWithNameLabel();
    $with_name_label->name = "Label name here";
    $with_name_label->save();

    $this->assertEquals($with_name_label->name, $with_name_label->get_label(), "Name label works");
  }

  public function testUpdate(){
    $insert = new TestModel();
    $insert->text_field = "Before";
    $insert->integer_field = 0;
    $insert->date_field = date("Y-m-d H:i:s");
    $insert->save();

    $reload = TestModel::search()->where('test_model_id', $insert->test_model_id)->execOne();

    $this->assertEquals("Before", $reload->text_field);

    $reload->text_field = "After";
    $reload->save();

    $reload_again = TestModel::search()->where('test_model_id', $insert->test_model_id)->execOne();

    $this->assertEquals("After", $reload_again->text_field);

    return $reload_again;
  }

  /**
   * @depends testUpdate
   */
  public function testReturnedTypes(TestModel $result){
    $this->assertTrue(is_integer($result->test_model_id));
    $this->assertTrue(is_integer($result->integer_field));
    $this->assertTrue(is_string($result->text_field));
    $this->assertNotFalse(strtotime($result->date_field));
  }

  /**
   * @depends testUpdate
   */
  public function testDelete(TestModel $deletable){
    $this->assertTrue($deletable->delete(), "Delete function returned true");

    return $deletable->test_model_id;
  }

  /**
   * @depends testDelete
   */
  public function testDeleteVerify($test_model_id){
    $reload = TestModel::search()->where('test_model_id', $test_model_id)->execOne();
    $this->assertFalse($reload , "Delete verified");
  }

  /**
   * @depends testUpdate
   */
  public function testGetClass(TestModel $testModel){
    $this->assertEquals("Thru\\ActiveRecord\\Test\\TestModel", $testModel->get_class(false));
    $this->assertEquals("TestModel", $testModel->get_class(true));
  }

  public function testCreateTableOnSearch(){
    $this->assertEquals(0, \Thru\ActiveRecord\Test\TestModelSearchOnly::search()->count());

    TestModelSearchOnly::delete_table();
  }
}