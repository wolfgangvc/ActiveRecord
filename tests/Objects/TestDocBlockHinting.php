<?php

namespace Thru\ActiveRecord\Test;
use Thru\ActiveRecord\SearchIndex;
use Thru\ActiveRecord\Test\Models\TestModel;
use Thru\ActiveRecord\Test\Models\TestModelBadHinting;

class TestDocBlockHinting extends BaseTest {

  /**
   * @expectedException PHPUnit_Framework_Error_Warning
   */
  public function testMissingDocHintWarningOnSave(){
    $o = new TestModelBadHinting();
    $o->test_model_id = rand(10000,99999);
    $o->integer_field = 1;
    $o->text_field = "Test";
    $o->date_field = date("Y-m-d H:i:s");
    $o->save();

    return $o->test_model_id;
  }

  /**
   * @expectedException PHPUnit_Framework_Error_Warning
   */
  public function testMissingDocHintWarningOnLoad(){
    $tmp = new TestModel();
    $tmp->text_field = $this->faker->paragraph;
    $tmp->integer_field = $this->faker->randomDigit;
    $tmp->date_field = $this->faker->datetime()->format(self::TIME_STORAGE_FORMAT);
    $tmp->save();

    SearchIndex::get_instance()->wipe();
    $j = TestModelBadHinting::search()->where('test_model_id', $tmp->test_model_id)->execOne();
  }
}
