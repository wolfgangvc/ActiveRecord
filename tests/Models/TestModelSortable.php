<?php

namespace Thru\ActiveRecord\Test\Models;

use Thru\ActiveRecord\ActiveRecord;

/**
 * Class TestModelSortable
 * @var $test_model_id integer
 * @var $integer_field integer
 * @var $text_field text
 * @var $date_field date
 */
class TestModelSortable extends ActiveRecord
{

    protected $_table = "test_models_sortable";

    public $test_model_id;
    public $integer_field;
    public $text_field;
    public $date_field;
}
