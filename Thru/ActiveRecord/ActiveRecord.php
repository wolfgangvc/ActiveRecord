<?php

namespace Thru\ActiveRecord;

use Thru\ActiveRecord\DatabaseLayer\TableBuilder;

class ActiveRecord
{
    static public $MYSQL_FORMAT = "Y-m-d H:i:s";
    protected $_label_column = 'name';
    protected $_columns_to_save_down;

    /**
     * get_all - Get all items.
     *
     * @param integer $limit Limit number of results
     * @param string $order Column to sort by
     * @param string $order_direction Order to sort by
     *
     * @throws exception
     * @return Array of items
     */
    static public function get_all($limit = null, $order = null, $order_direction = "ASC")
    {
        $name = get_called_class();
        $query = $name::search();
        if ($query instanceof Search) {
            if ($limit) {
                $query->limit($limit);
            }
            if ($order) {
                $query->order($order, $order_direction);
            }
            $result = $query->exec();
            return $result;
        } else {
            throw new exception("Failed to instantiate an object of type ActiveRecord with name {$name}");
        }
    }

    /**
     * GetAll - Get all items.
     * Legacy Support - Deprecated
     *
     * @param integer $limit Limit number of results
     * @param string $order Column to sort by
     * @param string $order_direction Order to sort by
     * @return Array of items
     */
    static public function getAll($limit = null, $order = null, $order_direction = "ASC")
    {
        //TODO: Old drupaly leftover needs cleanup.
        watchdog("ActiveRecord", "ActiveRecord::getAll() is deprecated, please use get_all()");
        return self::get_all($limit, $order, $order_direction);
    }

    /**
     * Start a Search on this type of active record
     * @return Search
     */
    static public function search()
    {
        $class = get_called_class();
        return new Search(new $class);
    }

    /**
     * Generic Factory constructor
     * @return ActiveRecord
     */
    public static function factory()
    {
        $name = get_called_class();
        return new $name();
    }

    /**
     * Override-able __construct call
     */
    public function __construct()
    {
      $tableBuilder = new TableBuilder();
      $tableBuilder->build($this);
    }

    /**
     * Override-able calls
     */
    public function __post_construct()
    {
    }

    public function __pre_save()
    {
    }

    public function __post_save()
    {
    }

    public function __requires_recast()
    {
        return false;
    }

    /**
     * Find an item by the Primary Key ID. This does not use the search() functionality
     * @param integer $id
     * @return ActiveRecord
     */
    public function get_by_id($id)
    {
        $db = DatabaseLayer::get_instance();
        $select = $db->select($this->get_table_name(), $this->get_table_alias());
        $select->fields($this->get_table_alias());
        $select->condition($this->get_table_primary_key(), $id);
        $results = $select->execute(get_called_class());
        $result = end($results);
        return $result;
    }

    /**
     * STATICALLY find an item by the Primary Key ID. This does not use the search() functionality
     * @param $id
     * @return ActiveRecord
     * @throws exception
     */
    static public function getById($id){
        $class = get_called_class();
        $o_active_record = new $class();
        if($o_active_record instanceof ActiveRecord){
            return $o_active_record->get_by_id($id);
        }else{
            throw new exception ("{$class} does not extend ActiveRecord!");
        }
    }

    /**
     * Get the short alias name of a table.
     *
     * @param string $table_name Optional table name
     * @return string Table alias
     */
    public function get_table_alias($table_name = null)
    {
        if (!$table_name) {
            $table_name = $this->get_table_name();
        }
        $bits = explode("_", $table_name);
        $alias = '';
        foreach ($bits as $bit) {
            $alias .= strtolower(substr($bit, 0, 1));
        }
        return $alias;
    }

    /**
     * Get the table name
     *
     * @return string Table Name
     */
    public function get_table_name()
    {
        return $this->_table;
    }

    /**
     * Get table primary key column name
     *
     * @return string|false
     */
    public function get_table_primary_key()
    {
        $db = DatabaseLayer::get_instance();
        $keys = $db->get_table_indexes($this->_table);
        if(!isset($keys[0])){
          return false;
        }
        $primary_key = $keys[0]->Column_name;
        return $primary_key;
    }

    /**
     * Get a unique key to use as an index
     *
     * @return string
     */
    public function get_primary_key_index()
    {
        $db = DatabaseLayer::get_instance();

        $keys = $db->get_table_indexes($this->_table);
        $columns = array();
        foreach ($keys as $key) {
            $columns[$key->Column_name] = $key->Column_name;
        }
        $keys = array();
        foreach ($columns as $column) {
            $keys[] = $this->$column;
        }
        return implode("-", $keys);
    }

    /**
     * Get object ID
     * @return integer
     */
    public function get_id()
    {
        $col = $this->get_table_primary_key();
        if (property_exists($this, $col)) {
            $id = $this->$col;
            if ($id > 0) {
                return $id;
            }
        }
        return false;
    }

    /**
     * Get a label for the object. Perhaps a Name or Description field.
     * @return string
     */
    public function get_label()
    {
        if (property_exists($this, '_label_column')) {
            if (property_exists($this, $this->_label_column)) {
                $lable_column = $this->_label_column;
                return $this->$lable_column;
            }
        }
        if (property_exists($this, 'name')) {
            return $this->name;
        }
        if (property_exists($this, 'description')) {
            return $this->description;
        }
        return "No label for " . get_called_class() . " ID " . $this->get_id();
    }

    /**
     * Work out which columns should be saved down.
     */
    public function _calculate_save_down_rows()
    {
        if (!$this->_columns_to_save_down) {
            foreach (get_object_vars($this) as $potential_column => $discard) {
                switch ($potential_column) {
                    case 'table':
                    case substr($potential_column, 0, 1) == "_":
                        // Not a valid column
                        break;
                    default:
                        $this->_columns_to_save_down[] = $potential_column;
                        break;
                }
            }
        }
        return $this->_columns_to_save_down;
    }

    /**
     * Load an object from data fed to us as an array (or similar.)
     *
     * @param array $row
     *
     * @return ActiveRecord
     */
    public function loadFromRow($row)
    {
        // Loop over the columns, sanitise and store it into the new properties of this object.
        foreach ($row as $column => &$value) {
            // Only save columns beginning with a normal letter.
            if (preg_match('/^[a-z]/i', $column)) {
                $this->$column = & $value;
            }
        }
        $this->__post_construct();
        return $this;
    }

    /**
     * Save the selected record.
     * This will do an INSERT or UPDATE as appropriate
     *
     * @param boolean $automatic_reload Whether or not to automatically reload
     *
     * @return ActiveRecord
     */
    public function save($automatic_reload = true)
    {
        $this->__pre_save();
        // Calculate row to save_down
        $this->_calculate_save_down_rows();
        $primary_key_column = $this->get_table_primary_key();

        // Make an array out of the objects columns.
        $data = array();
        foreach ($this->_columns_to_save_down as $column) {
            // Never update the primary key. Bad bad bad.
            if ($column != $primary_key_column) {
                $data["`{$column}`"] = $this->$column;
            }
        }

        // If we already have an ID, this is an update.
        $db = DatabaseLayer::get_instance();
        if ($this->get_id()) {
            $operation = $db->update($this->get_table_name(), $this->get_table_alias());
        } else { // Else, we're an insert.
            $operation = $db->insert($this->get_table_name(), $this->get_table_alias());
        }

        $operation->setData($data);

        if ($this->get_id()) {
            $operation->condition($primary_key_column, $this->$primary_key_column);
            $operation->execute();
        } else { // Else, we're an insert.
            $new_id = $operation->execute();
            $this->$primary_key_column = $new_id;
        }

        // Expire any existing copy of this object.
        SearchIndex::get_instance()->expire($this->get_table_name(), $this->get_id());

        if ($automatic_reload) {
            $this->reload();
        }
        $this->__post_save();
        return $this;
    }

    /**
     * Reload the selected record
     * @return ActiveRecord
     */
    public function reload()
    {
      $item = $this->get_by_id($this->get_id());
      if($item !== false){
        $this->loadFromRow($item);
        return $this;
      }else{
        return false;
      }
    }
    /**
     * Delete the selected record
     * @return boolean
     */
    public function delete()
    {
      $db = DatabaseLayer::get_instance();
      $delete = $db->delete($this->get_table_name(), $this->get_table_alias());
      $delete->condition($this->get_table_primary_key(), $this->get_id());
      $delete->execute();
      return true;
    }

    /**
     * Pull a database record by the slug we're given.
     *
     * @param $slug string Slug
     *
     * @return mixed
     */
    static public function get_by_slug($slug)
    {
        $slug_parts = explode("-", $slug, 2);
        $class = get_called_class();
        $temp_this = new $class();
        $primary_key = $temp_this->get_table_primary_key();
        return self::search()->where($primary_key, $slug_parts[0])->execOne();
    }

    /**
     * Recast an object from a parent class to an extending class, if ActiveRecord_class is present
     *
     * @return ActiveRecord
     * @throws exception
     */
    public function __recast()
    {
        // If the object has a property called ActiveRecord_class, it can potentially be recast at runtime. There are some dependencies though
        if (property_exists($this, '__active_record_class')) {
            if ($this->__active_record_class !== get_called_class() && $this->__active_record_class != null) {
                if (!class_exists($this->__active_record_class)) {
                    throw new Exception("Active Record Class: {$this->__active_record_class} does not exist.");
                }
                if (!is_subclass_of($this->__active_record_class, get_called_class())) {
                    throw new Exception("Active Record Class: " . $this->__active_record_class . " must extend " . get_called_class());
                }
                $recast_class = $this->__active_record_class;
                $new_this = new $recast_class();
                $new_this->loadFromRow((array)$this);
                return $new_this;
            }
        }
        return $this;
    }

    /**
     * Generate a suitable magic_form for this object, if magic_form library installed
     *
     * @return magic_form
     * @throws exception
     */
    static public function magic_form()
    {
        if (function_exists('magic_forms_init')) {
            $form = self::factory()->_get_magic_form();
            return $form;
        } else {
            throw new exception("Magic forms is not installed, cannot call ActiveRecord::magic_form()");
        }
    }

    /**
     * Process the magic form...
     *
     * @return magic_form
     * @throws exception
     */
    public function _get_magic_form()
    {
        if (module_exists('magic_forms')) {
            $form = new magic_form();
            $columns = $this->_interogate_db_for_columns();
            foreach ($columns as $column) {
                // Default type is text.
                $type = 'magic_form_field_text';

                // primary key column is always omitted
                if ($column['Field'] == $this->get_table_primary_key()) {
                    continue;
                }

                // Ignore Auto_Increment primary keys
                if ($column['Extra'] == 'auto_increment') {
                    continue;
                }

                // Ignore logical deletion column
                if ($column['Field'] == 'deleted') {
                    continue;
                }

                // uid column is always invisible
                if ($column['Field'] == 'uid') {
                    continue;
                }

                // Remote key
                if (isset($column['Constraint'])) {
                    $type = 'magic_form_field_select';
                }


                // Set the value, if set.
                if (property_exists($this, $column['Field'])) {
                    $value = $this->$column['Field'];
                    if (is_array($value) || is_object($value)) {
                        $value = pretty_print_json(json_encode($value));
                    }
                } else {
                    $value = null;
                }

                // Do something useful with default values.
                if (isset($column['Default'])) {
                    $default_value = $column['Default'];
                } else {
                    $default_value = null;
                }

                // If the value is long, and the field is a text field, make it a textarea
                if (strlen($value) > 100 || strpos($value, "\n") !== FALSE) {
                    $type = 'magic_form_field_textarea';
                }

                // Create the new field and add it to the form.
                /* @var $new_field magic_form_field */
                $new_field = new $type(strtolower($column['Field']), $column['Field']);

                // Remote key options
                if (isset($column['Constraint'])) {
                    $contraint_options = db_select($column['Constraint']['Table'], 'a')
                        ->fields('a', array('name', $column['Constraint']['Column']))
                        ->execute()
                        ->fetchAll();
                    foreach ($contraint_options as $contraint_option) {
                        $contraint_option = (array)$contraint_option;
                        $new_field->add_option(reset($contraint_option), end($contraint_option));
                    }
                }

                // Set the value & default
                $new_field->set_value($value);
                $new_field->set_default_value($default_value);

                // Add to the form
                $form->add_field($new_field);
            }

            // Add save button
            $save = new magic_form_field_submit('save', 'Save', 'Save');
            $form->add_field($save);

            // Sort out passing variables
            $that = $this;
            global $user;

            // Create a simple handler
            $form->submit(function (magic_form $form) use ($that, $user) {
                $object_type = get_class($that);
                $object = new $object_type;
                /* @var $object ActiveRecord */

                // Attempt to load by the ID given to us
                $field = $form->get_field($object->get_table_primary_key());
                if ($field instanceof magic_form_field) {
                    $value = $field->get_value();
                    $object->loadById($value);
                }

                // Attempt to read in all the variables
                foreach ($object->get_table_headings() as $heading) {
                    $field = $form->get_field($heading);
                    if ($field instanceof magic_form_field) {
                        echo $heading;
                        krumo($field);
                        $object->$heading = $field->get_value();
                    }
                    if ($heading == 'uid') {
                        $object->uid = $user->uid;
                    }
                }

                // Save object.
                $object->save();

                // If Submit Destination is set, redirect to it.
                if ($form->get_submit_destination()) {
                    header("Location: {$form->get_submit_destination()}");
                    exit;
                }
            });

            // Return the form
            return $form;
        } else {
            throw new exception("Magic forms is not installed, cannot call ActiveRecord::magic_form()");
        }
    }

    /**
     * @return array
     */
    private function _interogate_db_for_columns()
    {
        $table = $this->get_table_name();
        $sql = "SHOW COLUMNS FROM `$table`";
        $fields = array();
        $result = db_query($sql);

        foreach ($result->fetchAll() as $row) {
            $fields[] = (array)$row;
        }

        foreach ($fields as &$field) {
            // TODO: Refactor out this raw SQL.
            $constraint_query_sql = "
                select
                    TABLE_NAME,
                    COLUMN_NAME,
                    CONSTRAINT_NAME,
                    REFERENCED_TABLE_NAME,
                    REFERENCED_COLUMN_NAME
                from INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                where TABLE_NAME = '{$table}'
                  and COLUMN_NAME = '{$field['Field']}'
            ";
            $constraint_query = db_query($constraint_query_sql);

            foreach ($constraint_query->fetchAll() as $constraint_query_row) {
                if ($constraint_query_row->REFERENCED_TABLE_NAME !== null && $constraint_query_row->REFERENCED_COLUMN_NAME !== null) {
                    $field['Constraint'] = array(
                        'Table' => $constraint_query_row->REFERENCED_TABLE_NAME,
                        'Column' => $constraint_query_row->REFERENCED_COLUMN_NAME,
                    );
                }
            }
        }
        return $fields;
    }

    /**
     * Get URL slug.
     *
     * @return string
     */
    public function get_slug()
    {
        return $this->get_id() . "-" . Util::slugify($this->get_label());
    }

    public function get_table_headings()
    {
        return $this->_calculate_save_down_rows();
    }

    public function get_table_rows($anticipated_rows = null)
    {
        $rows = array();
        foreach (self::get_all() as $item) {
            /* @var $item ActiveRecord */
            $rows[] = $item->__toArray($anticipated_rows);
        }
        return $rows;
    }

    public function __toArray($anticipated_rows = null)
    {
        $array = array();
        foreach (get_object_vars($this) as $k => $v) {
            if ($anticipated_rows === null || in_array($k, $anticipated_rows)) {
                $array[$k] = $v;
            }
        }
        return $array;
    }

    public function __toJson($anticipated_rows = null){
        $array = $this->__toArray($anticipated_rows);
        return json_encode($array);
    }

    public function get_class($without_namespace = false){
        if($without_namespace){
          $bits = explode("\\", get_called_class());
          return end($bits);
        }else{
          return get_called_class();
        }
    }

    public function get_schema(){

    }
}