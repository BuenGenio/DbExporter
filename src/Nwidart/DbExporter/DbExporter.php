<?php namespace Nwidart\DbExporter;

use DB;

abstract class DbExporter
{
    /**
     * Contains the ignore tables
     * @var array $ignore
     */
    public static $ignore = array('migrations','sqlite_sequence');
    public static $remote;

    
    /**
     * Get laravel db driver name
     * @return string
     */
    protected function getDriver() {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        return $driver;
    }

    /**
     * Get all the tables
     * @return mixed
     */
    protected function getTables()
    {
        $pdo = DB::connection()->getPdo();
        switch($this->getDriver()) {
            case 'sqlite':
                return $pdo->query('SELECT name FROM sqlite_master WHERE type=\'table\'')->fetchAll(\PDO::FETCH_COLUMN);
            case 'mysql':
            default:
        }
        return $pdo->query('SELECT table_name FROM information_schema.tables WHERE table_schema="' . $this->database . '"')->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getTableIndexes($table)
    {
        $pdo = DB::connection()->getPdo();
        return $pdo->query('SHOW INDEX FROM ' . $table . ' WHERE Key_name != "PRIMARY"');
    }

    /**
     * Get all the columns for a given table
     * @param $table
     * @return mixed
     */
    protected function getTableDescribes($table)
    {
        $pdo = DB::connection()->getPdo();
        switch($this->getDriver()) {
            case 'sqlite':
                return $this->getTableDescribesSQLite($table);
            case 'mysql':
            default:
        }
        return DB::table('information_schema.columns')
            ->where('table_schema', '=', $this->database)
            ->where('table_name', '=', $table)
            ->get($this->selects);
    }

    protected function getTableDescribesSQLite($table) {
        $pdo = DB::connection()->getPdo();
        $sqliteInfo = $pdo->query('PRAGMA table_info('.$pdo->quote($table).')')->fetchAll(\PDO::FETCH_ASSOC);

        return collect($sqliteInfo)->map(function ($sqliteFieldInfo) {
            //Returns mysql format. Some more difficult translations have been excluded for now.
            return (object)[
                "Field" => $sqliteFieldInfo['name'],
                // "Type" => $sqliteFieldInfo[''], // eg "int(10) unsigned"
                "Null" => $sqliteFieldInfo['notnull'] == '0' ? 'YES' : 'NO',
                "Key" => $sqliteFieldInfo['pk']=='1' ? "PRI" : "",
                "Default" => $sqliteFieldInfo['dflt_value'],
                // "Extra" => $sqliteFieldInfo[''], // eg: "auto_increment"
                // "Data_Type" => $sqliteFieldInfo[''],  // mappings : ['integer' => 'int', ... ]
            ];
        });
    }

    /**
     * Grab all the table data
     * @param $table
     * @return mixed
     */
    protected function getTableData($table)
    {
        return DB::table($table)->get();
    }

    /**
     * Write the file
     * @return mixed
     */
    abstract public function write();

    /**
     * Convert the database to a usefull format
     * @param null $database
     * @return mixed
     */
    abstract public function convert($database = null);

    /**
     * Put the converted stub into a template
     * @return mixed
     */
    abstract protected function compile();
}