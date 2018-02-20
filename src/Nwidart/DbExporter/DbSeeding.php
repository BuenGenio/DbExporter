<?php namespace Nwidart\DbExporter;

use Config, DB, Str, File;

class DbSeeding extends DbExporter
{
    protected $selects = array(
        'column_name as Field',
        'column_type as Type',
        'is_nullable as Null',
        'column_key as Key',
        'column_default as Default',
        'extra as Extra',
        'data_type as Data_Type'
    );


    /**
     * @var String
     */
    protected $database;

    /**
     * @var String
     */
    protected $seedingStub;

    /**
     * @var bool
     */
    protected $customDb = false;

    /**
     * Set the database name
     * @param String $database
     */
    function __construct($database)
    {
        $this->database = $database;
    }

    /**
     * Write the seed file
     */
    public function write()
    {
        // Check if convert method was called before
        // If not, call it on default DB
        if (!$this->customDb) {
            $this->convert();
        }

        $seed = $this->compile();

        $filename = studly_case(preg_replace('/[^a-z0-9]/i','',basename($this->database))) . "TableSeeder";

        \Log::info($this->database . " => " . $filename);

        $result = file_put_contents(config('db-exporter.export_path.seeds')."{$filename}.php", $seed);

        \Log::info("put $result => " . config('db-exporter.export_path.seeds'));
    }

    /**
     * Convert the database tables to something usefull
     * @param null $database
     * @return $this
     */
    public function convert($database = null)
    {
        if (!is_null($database)) {
            $this->database = $database;
            $this->customDb = true;
        }

        // Get the tables for the database
        $tables = $this->getTables();

        $stub = "";
        // Loop over the tables
        foreach ($tables as $tableName) 
        {
            // Do not export the ignored tables
            if (in_array($tableName, self::$ignore)) 
            {
                continue;
            }
            $tableDescribes = $this->getTableDescribes($tableName);

            $columnInfo = [];

            foreach($tableDescribes as $tableDescribe)
            {
                $columnInfo[$tableDescribe->Field] = $tableDescribe;
            }

            $tableData = $this->getTableData($tableName);
            $insertStub = "";

            foreach ($tableData as $obj) 
            {

                $insertStub .= "
            array(\n";
                foreach ($obj as $prop => $value) 
                {
                    $insertStub .= $this->insertPropertyAndValue($prop, $value,$columnInfo[$prop]);
                }

                if (count($tableData) > 1) 
                {
                    $insertStub .= "            ),\n";
                } 
                else 
                {
                    $insertStub .= "            )\n";
                }
            }

            if ($this->hasTableData($tableData)) {
                $stub .= "
        DB::table('" . $tableName . "')->truncate();
        DB::table('" . $tableName . "')->insert(array(
            {$insertStub}
        ));";
            }
        }

        $this->seedingStub = $stub;

        return $this;
    }

    /**
     * Compile the current seedingStub with the seed template
     * @return mixed
     */
    protected function compile()
    {
        // Grab the template
        $template = File::get(__DIR__ . '/templates/seed.txt');

        // Replace the classname
        $template = str_replace('{{className}}', studly_case(preg_replace('/[^a-z0-9]/i','',basename($this->database))) . "TableSeeder", $template);
        $template = str_replace('{{run}}', $this->seedingStub, $template);

        return $template;
    }

    private static function escapeForPhpSingleQuote($value)
    {
        return strtr($value, ['\''=>'\\\'', '\\' => '\\\\']);
    }

    private function insertPropertyAndValue($prop, $value,$columnInfo)
    {
        $propEsc = self::escapeForPhpSingleQuote($prop);
        $valueEsc = self::escapeForPhpSingleQuote($value);

        if (is_numeric($value)) {
            return "                '{$propEsc}' => {$valueEsc},\n";
        } elseif($value === null) {
            return "                '{$propEsc}' => NULL,\n";
        } else {
            return "                '{$propEsc}' => '{$valueEsc}',\n";
        }
    }

    /**
     * @param $tableData
     * @return bool
     */
    public function hasTableData($tableData)
    {
        return count($tableData) >= 1;
    }
}
