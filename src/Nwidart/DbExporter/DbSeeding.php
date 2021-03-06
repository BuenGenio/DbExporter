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

        $filename = studly_case($this->database) . "TableSeeder";

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
        foreach ($tables as $key => $value) 
        {
            // Do not export the ignored tables
            if (in_array($value['table_name'], self::$ignore)) 
            {
                continue;
            }
            $tableDescribes = $this->getTableDescribes($value['table_name']);

            $columnInfo = [];

            foreach($tableDescribes as $tableDescribe)
            {
                $columnInfo[$tableDescribe->Field] = $tableDescribe;
            }

            $tableName = $value['table_name'];
            $tableData = $this->getTableData($value['table_name']);
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
        $template = str_replace('{{className}}', studly_case($this->database) . "TableSeeder", $template);
        $template = str_replace('{{run}}', $this->seedingStub, $template);

        return $template;
    }

    private function insertPropertyAndValue($prop, $value,$columnInfo)
    {
        $prop = addslashes($prop);
        $value = str_replace('$','\$',addslashes($value));
        //$value = addslashes($value);
        if (is_numeric($value)) {
            return "                '{$prop}' => {$value},\n";
        } elseif($value == '') {
            if($columnInfo->Null == 'NO')
                return "                '{$prop}' => '',\n";
            else
                return "                '{$prop}' => NULL,\n";
        } else {
            return "                '{$prop}' => \"{$value}\",\n";
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
