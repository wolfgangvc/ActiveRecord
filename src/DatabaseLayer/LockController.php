<?php
namespace Thru\ActiveRecord\DatabaseLayer;

use Thru\ActiveRecord\Exception;

class LockController extends VirtualQuery
{

    public function __construct($table, $alias = null)
    {
        $this->tables[$alias] = new Table($table);
    }

    public function lock(){

    }

    public function unlock(){

    }
}