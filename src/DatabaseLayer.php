<?php
namespace Thru\ActiveRecord;

use Doctrine\Common\Cache\CacheProvider;
use Monolog\Logger;
use Thru\ActiveRecord\DatabaseLayer\ConfigurationException;
use Predis\Client as RedisCache;

class DatabaseLayer
{

    const DSN_REGEX = '/^(?P<user>\w+)(:(?P<password>\w+))?@(?P<host>[.\w]+)(:(?P<port>\d+))?\\\\(?P<database>\w+)$/im';

    private static $instance;
    private $options;
    private $cache;
    private $logger;

    /**
     * @throws ConfigurationException
     * @return DatabaseLayer
     */
    public static function getInstance()
    {
        if (!DatabaseLayer::$instance) {
            throw new ConfigurationException("DatabaseLayer has not been configured");
        }
        return DatabaseLayer::$instance;
    }

    /**
     * @param DatabaseLayer $instance
     */
    public static function setInstance(DatabaseLayer $instance)
    {
        self::$instance = $instance;
    }

    /**
     * Destroy current instance
     * @return bool
     */
    public static function destroyInstance()
    {
        self::$instance = null;
        return true;
    }

    /**
     * @param CacheProvider $cache
     * @return $this
     */
    public function setCache(CacheProvider $cache)
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * @return CacheProvider
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * Decide if we're going to use a cache or not.
     * @return bool
     */
    public function useCache()
    {
        return $this->cache instanceof CacheProvider ? true : false;
    }

    /**
     * @param Logger $logger
     * @return $this
     */
    public function setLogger(Logger $logger = null)
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * @returns Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param array|null $options
     */
    public function __construct($options = null)
    {
        $this->options = $options;
        if (!isset($this->options['db_dsn'])) {
            $this->options['db_dsn'] = $this->__getDsn();
        }
        self::$instance = $this;
    }

    /**
     * @param $table_name
     * @return DatabaseLayer\LockController
     */
    public function lockController($table_name, $table_alias = null)
    {
        return new DatabaseLayer\LockController($table_name, $table_alias);
    }

    /**
     * @param $table_name
     * @param null       $table_alias
     * @return DatabaseLayer\Select
     */
    public function select($table_name, $table_alias = null)
    {
        return new DatabaseLayer\Select($table_name, $table_alias);
    }

    /**
     * @param string $table_name
     * @param string $table_alias
     * @return DatabaseLayer\Update
     */
    public function update($table_name, $table_alias = null)
    {
        return new DatabaseLayer\Update($table_name, $table_alias);
    }
    /**
     * @param string $table_name
     * @param string $table_alias
     * @return DatabaseLayer\Delete
     */
    public function delete($table_name, $table_alias = null)
    {
        return new DatabaseLayer\Delete($table_name, $table_alias);
    }

    /**
     * @param string $table_name
     * @param string $table_alias
     * @return DatabaseLayer\Insert
     */
    public function insert($table_name, $table_alias = null)
    {
        return new DatabaseLayer\Insert($table_name, $table_alias);
    }

    /**
     * @param $sql
     * @return DatabaseLayer\Passthru
     */
    public function passthru($sql = null)
    {
        return new DatabaseLayer\Passthru($sql);
    }

    public function getTableIndexes($table_name)
    {
        $util = new DatabaseLayer\Util();
          $indexes = $util->getIndexes($table_name);
          return $indexes;
    }

    /**
     * @param string $name
     *
     * @return string|null
     */
    public function getOption($name)
    {
        if (isset($this->options[$name])) {
            return $this->options[$name];
        }
        return false;
    }

    /**
     * @return string|false
     * @throws ConfigurationException
     */
    private function __getDsn()
    {
        switch ($this->options['db_type']) {
            case 'Mysql':
                $dsn = "mysql:host={$this->options['db_hostname']};port={$this->options['db_port']};dbname={$this->options['db_database']};user={$this->options['db_username']};pass={$this->options['db_password']}";
                break;
            case 'Sqlite':
                $dsn = "sqlite:{$this->options['db_file']}";
                break;
            case 'Postgres':
                $dsn = "pgsql:host={$this->options['db_hostname']};port={$this->options['db_port']};dbname={$this->options['db_database']}";
                break;
            default:
                throw new ConfigurationException("DB TYPE not supported: {$this->options['db_type']}");
        }
        return $dsn;
    }
}
