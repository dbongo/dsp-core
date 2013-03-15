<?php

/**
 * @category   DreamFactory
 * @package    DreamFactory
 * @subpackage DatabaseSvc
 * @copyright  Copyright (c) 2009 - 2012, DreamFactory (http://www.dreamfactory.com)
 * @license    http://www.dreamfactory.com/license
 */

class SchemaSvc extends CommonService implements iRestHandler
{

    // Members

    /**
     * @var
     */
    protected $tableName;

    /**
     * @var
     */
    protected $fieldName;

    /**
     * @var CDbConnection
     */
    protected $_sqlConn;

    /**
     * @var boolean
     */
    protected $_isNative = false;

    /**
     * Creates a new DatabaseSvc instance
     *
     * @param array $config
     * @throws InvalidArgumentException
     */
    public function __construct($config)
    {
        parent::__construct($config);
        $type = Utilities::getArrayValue('storage_type', $config, '');
        $credentials = Utilities::getArrayValue('credentials', $config, array());
        $dsn = Utilities::getArrayValue('dsn', $credentials, '');
        $user = Utilities::getArrayValue('user', $credentials, '');
        $pwd = Utilities::getArrayValue('pwd', $credentials, '');
        if (empty($dsn) && empty($user) && empty($pwd)) {
            $this->_sqlConn = Yii::app()->db;
            $this->_isNative = true;
            $this->_driverType = DbUtilities::getDbDriverType($this->_sqlConn);
        }
        else {
            // Validate other parameters
            if (empty($dsn)) {
                throw new InvalidArgumentException('DB connection string (DSN) can not be empty.');
            }
            if (empty($user)) {
                throw new InvalidArgumentException('DB admin name can not be empty.');
            }
            if (empty($pwd)) {
                throw new InvalidArgumentException('DB admin password can not be empty.');
            }

            $this->_isNative = false;
            // create pdo connection, activate later
            Utilities::markTimeStart('DB_TIME');
            $this->_sqlConn = new CDbConnection($dsn, $user, $pwd);
            $this->_driverType = DbUtilities::getDbDriverType($this->_sqlConn);
            switch ($this->_driverType) {
            case DbUtilities::DRV_MYSQL:
                $this->_sqlConn->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
                $this->_sqlConn->setAttribute('charset', 'utf8');
                break;
            case DbUtilities::DRV_SQLSRV:
                $this->_sqlConn->setAttribute(constant('PDO::SQLSRV_ATTR_DIRECT_QUERY'), true);
                $this->_sqlConn->setAttribute("MultipleActiveResultSets", false);
                $this->_sqlConn->setAttribute("ReturnDatesAsStrings", true);
                $this->_sqlConn->setAttribute("CharacterSet", "UTF-8");
                break;
            }
            Utilities::markTimeStop('DB_TIME');
        }

        $attributes = Utilities::getArrayValue('parameters', $config, array());
        if (!empty($attributes) && is_array($attributes)) {
            foreach ($attributes as $key=>$value) {
                $this->_sqlConn->setAttribute($key, $value);
            }
        }
    }

    /**
     * Object destructor
     */
    public function __destruct()
    {
        parent::__destruct();
        if (!$this->_isNative) {
            unset($this->_sqlConn);
        }
    }

    /**
     * @param string $service
     * @param string $description
     * @return array
     */
    public static function swaggerPerSchema($service, $description='')
    {
        $swagger = array(
            array('path' => '/'.$service,
                  'description' => $description,
                  'operations' => array(
                      array("httpMethod"=> "GET",
                            "summary"=> "List tables available to the schema service",
                            "notes"=> "Use the table names in available schema operations.",
                            "responseClass"=> "array",
                            "nickname"=> "getTables",
                            "parameters"=> SwaggerUtilities::swaggerParameters(array()),
                            "errorResponses"=> array()
                      ),
                      array("httpMethod"=> "POST",
                            "summary"=> "Create one or more tables",
                            "notes"=> "Post data should be a single table definition or an array of table definitions",
                            "responseClass"=> "array",
                            "nickname"=> "createTables",
                            "parameters"=> SwaggerUtilities::swaggerParameters(array()),
                            "errorResponses"=> array()
                      ),
                      array("httpMethod"=> "PUT",
                            "summary"=> "Update one or more tables",
                            "notes"=> "Post data should be a single table definition or an array of table definitions",
                            "responseClass"=> "array",
                            "nickname"=> "updateTables",
                            "parameters"=> SwaggerUtilities::swaggerParameters(array()),
                            "errorResponses"=> array()
                      ),
                  )
            ),
            array('path' => '/'.$service.'/{table_name}',
                  'description' => 'Operations for per table administration.',
                  'operations' => array(
                      array("httpMethod"=> "GET",
                            "summary"=> "Retrieve table definition for the given table",
                            "notes"=> "This describes the table, its fields and relations to other tables.",
                            "responseClass"=> "array",
                            "nickname"=> "describeTable",
                            "parameters"=> SwaggerUtilities::swaggerParameters(array('table_name')),
                            "errorResponses"=> array()
                      ),
                      array("httpMethod"=> "POST",
                            "summary"=> "Create one or more fields in the given table",
                            "notes"=> "Post data should be an array of field properties for a single record or an array of fields",
                            "responseClass"=> "array",
                            "nickname"=> "createFields",
                            "parameters"=> SwaggerUtilities::swaggerParameters(array('table_name')),
                            "errorResponses"=> array()
                      ),
                      array("httpMethod"=> "PUT",
                            "summary"=> "Update one or more fields in the given table",
                            "notes"=> "Post data should be an array of field properties for a single record or an array of fields",
                            "responseClass"=> "array",
                            "nickname"=> "updateFields",
                            "parameters"=> SwaggerUtilities::swaggerParameters(array('table_name')),
                            "errorResponses"=> array()
                      ),
                      array("httpMethod"=> "DELETE",
                            "summary"=> "Delete (aka drop) the given table",
                            "notes"=> "Careful, this drops the database table and all of its contents.",
                            "responseClass"=> "array",
                            "nickname"=> "deleteTable",
                            "parameters"=> SwaggerUtilities::swaggerParameters(array('table_name')),
                            "errorResponses"=> array()
                      ),
                  )
            ),
            array('path' => '/'.$service.'/{table_name}/{field_name}',
                  'description' => 'Operations for single record administration.',
                  'operations' => array(
                      array("httpMethod"=> "GET",
                            "summary"=> "Retrieve the definition of the given field for the given table",
                            "notes"=> "This describes the field and its properties.",
                            "responseClass"=> "array",
                            "nickname"=> "describeField",
                            "parameters"=> SwaggerUtilities::swaggerParameters(array('table_name','field_name')),
                            "errorResponses"=> array()
                      ),
                      array("httpMethod"=> "PUT",
                            "summary"=> "Update one record by identifier",
                            "notes"=> "Post data should be an array of field properties for the given field",
                            "responseClass"=> "array",
                            "nickname"=> "updateField",
                            "parameters"=> SwaggerUtilities::swaggerParameters(array('table_name','field_name')),
                            "errorResponses"=> array()
                      ),
                      array("httpMethod"=> "DELETE",
                            "summary"=> "Delete (aka drop) the given field from the given table",
                            "notes"=> "Careful, this drops the database table field/column and all of its contents.",
                            "responseClass"=> "array",
                            "nickname"=> "deleteField",
                            "parameters"=> SwaggerUtilities::swaggerParameters(array('table_name','field_name')),
                            "errorResponses"=> array()
                      ),
                  )
            ),
        );

        return $swagger;
    }

    // Controller based methods

    /**
     * @return array
     * @throws Exception
     */
    public function actionSwagger()
    {
        try {
            $this->detectCommonParams();

            $result = SwaggerUtilities::swaggerBaseInfo($this->_api_name);
            $resources = static::swaggerPerSchema($this->_api_name, $this->_description);
            $result['apis'] = $resources;
            return $result;
        }
        catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * @return array
     */
    public function actionGet()
    {
        $this->detectCommonParams();
        if (empty($this->tableName)) {
            $result = $this->describeDatabase();
            $result = array('resource' => $result['table']);
        }
        else {
            if (empty($this->fieldName)) {
                $result = $this->describeTable($this->tableName);
            }
            else {
                $result = $this->describeField($this->tableName, $this->fieldName);
            }
        }

        return $result;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function actionPost()
    {
        $this->detectCommonParams();
        $data = Utilities::getPostDataAsArray();
        if (empty($this->tableName)) {
            $tables = Utilities::getArrayValue('table', $data, '');
            if (empty($tables)) {
                // temporary, layer created from xml to array conversion
                $tables = (isset($data['tables']['table'])) ? $data['tables']['table'] : '';
            }
            if (empty($tables)) {
                // could be a single table definition
                return $this->createTable($data);
            }
            $result = $this->createTables($tables);
            return array('table' => $result);
        }
        else {
            if (empty($this->fieldName)) {
                // create fields in existing table
                $fields = Utilities::getArrayValue('field', $data, '');
                if (empty($fields)) {
                    // temporary, layer created from xml to array conversion
                    $fields = (isset($data['fields']['field'])) ? $data['fields']['field'] : '';
                }
                if (empty($fields)) {
                    // could be a single field definition
                    return $this->createField($this->tableName, $data);
                }
                $result = $this->createFields($this->tableName, $fields);
                return array('field' => $result);
            }
            else {
                // create new field indices?
                throw new Exception('No new field resources currently supported.');
            }
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    public function actionPut()
    {
        $this->detectCommonParams();
        $data = Utilities::getPostDataAsArray();
        if (empty($this->tableName)) {
            $tables = Utilities::getArrayValue('table', $data, '');
            if (empty($tables)) {
                // temporary, layer created from xml to array conversion
                $tables = (isset($data['tables']['table'])) ? $data['tables']['table'] : '';
            }
            if (empty($tables)) {
                // could be a single table definition
                return $this->updateTable($data);
            }
            $result = $this->updateTables($tables);
            return array('table' => $result);
        }
        else {
            if (empty($this->fieldName)) {
                // create fields in existing table
                $fields = Utilities::getArrayValue('field', $data, '');
                if (empty($fields)) {
                    // temporary, layer created from xml to array conversion
                    $fields = (isset($data['fields']['field'])) ? $data['fields']['field'] : '';
                }
                if (empty($fields)) {
                    // could be a single field definition
                    return $this->updateField($this->tableName, '', $data);
                }
                $result = $this->updateFields($this->tableName, $fields);
                return array('field' => $result);
            }
            else {
                // create new field in existing table
                if (empty($data)) {
                    throw new Exception('No data in schema create request.');
                }
                return $this->updateField($this->tableName, $this->fieldName, $data);
            }
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    public function actionMerge()
    {
        $this->detectCommonParams();
        $data = Utilities::getPostDataAsArray();
        if (empty($this->tableName)) {
            $tables = Utilities::getArrayValue('table', $data, '');
            if (empty($tables)) {
                // temporary, layer created from xml to array conversion
                $tables = (isset($data['tables']['table'])) ? $data['tables']['table'] : '';
            }
            if (empty($tables)) {
                // could be a single table definition
                return $this->updateTable($data);
            }
            $result = $this->updateTables($tables);
            return array('table' => $result);
        }
        else {
            if (empty($this->fieldName)) {
                // create fields in existing table
                $fields = Utilities::getArrayValue('field', $data, '');
                if (empty($fields)) {
                    // temporary, layer created from xml to array conversion
                    $fields = (isset($data['fields']['field'])) ? $data['fields']['field'] : '';
                }
                if (empty($fields)) {
                    // could be a single field definition
                    return $this->updateField($this->tableName, '', $data);
                }
                $result = $this->updateFields($this->tableName, $fields);
                return array('field' => $result);
            }
            else {
                // create new field in existing table
                if (empty($data)) {
                    throw new Exception('No data in schema create request.');
                }
                return $this->updateField($this->tableName, $this->fieldName, $data);
            }
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    public function actionDelete()
    {
        $this->detectCommonParams();
        if (!empty($this->tableName)) {
            if (!empty($this->fieldName)) {
                $result = $this->deleteField($this->tableName, $this->fieldName);
                return array('field' => $result);
            }
            else {
                $result = $this->deleteTable($this->tableName);
                return array('table' => $result);
            }
        }
        else {
            throw new Exception('Invalid format for DELETE Table request.');
        }
    }

    /**
     *
     */
    protected function detectCommonParams()
    {
        $resource = Utilities::getArrayValue('resource', $_GET, '');
        $resource = (!empty($resource)) ? explode('/', $resource) : array();
        $this->tableName = (isset($resource[0])) ? $resource[0] : '';
        $this->fieldName = (isset($resource[1])) ? $resource[1] : '';
    }

    /**
     * @return array
     * @throws Exception
     */
    public function describeDatabase()
    {
        $this->checkPermission('read');
        $exclude = '';
        if ($this->_isNative) {
            // check for system tables
            $exclude = SystemManager::SYSTEM_TABLE_PREFIX;
        }
        try {
            return DbUtilities::describeDatabase($this->_sqlConn, '', $exclude);
        }
        catch (Exception $ex) {
            throw new Exception("Error describing database tables.\n{$ex->getMessage()}", $ex->getCode());
        }
    }

    /**
     * @param $table_list
     * @return array|string
     * @throws Exception
     */
    public function describeTables($table_list)
    {
        $tables = array_map('trim', explode(',', trim($table_list, ',')));
        // check for system tables and deny
        $sysPrefix = SystemManager::SYSTEM_TABLE_PREFIX;
        foreach ($tables as $table) {
            if ($this->_isNative) {
                if (0 === substr_compare($table, $sysPrefix, 0, strlen($sysPrefix))) {
                    throw new Exception("Table '$table' not found.", ErrorCodes::NOT_FOUND);
                }
            }
            $this->checkPermission('read', $table);
        }
        try {
            return DbUtilities::describeTables($this->_sqlConn, $tables);
        }
        catch (Exception $ex) {
            throw new Exception("Error describing database tables '$table_list'.\n{$ex->getMessage()}", $ex->getCode());
        }
    }

    /**
     * @param $table
     * @return array
     * @throws Exception
     */
    public function describeTable($table)
    {
        if (empty($table)) {
            throw new Exception('Table name can not be empty.', ErrorCodes::BAD_REQUEST);
        }
        if ($this->_isNative) {
            // check for system tables and deny
            $sysPrefix = SystemManager::SYSTEM_TABLE_PREFIX;
            if (0 === substr_compare($table, $sysPrefix, 0, strlen($sysPrefix))) {
                throw new Exception("Table '$table' not found.", ErrorCodes::NOT_FOUND);
            }
        }
        $this->checkPermission('read', $table);
        try {
            return DbUtilities::describeTable($this->_sqlConn, $table);
        }
        catch (Exception $ex) {
            throw new Exception("Error describing database table '$table'.\n{$ex->getMessage()}", $ex->getCode());
        }
    }

    /**
     * @param $table
     * @param $field
     * @return array
     * @throws Exception
     */
    public function describeField($table, $field)
    {
        if (empty($table)) {
            throw new Exception('Table name can not be empty.', ErrorCodes::BAD_REQUEST);
        }
        if ($this->_isNative) {
            // check for system tables and deny
            $sysPrefix = SystemManager::SYSTEM_TABLE_PREFIX;
            if (0 === substr_compare($table, $sysPrefix, 0, strlen($sysPrefix))) {
                throw new Exception("Table '$table' not found.", ErrorCodes::NOT_FOUND);
            }
        }
        $this->checkPermission('read', $table);
        try {
            return DbUtilities::describeField($this->_sqlConn, $table, $field);
        }
        catch (Exception $ex) {
            throw new Exception("Error describing database table '$table' field '$field'.\n{$ex->getMessage()}", $ex->getCode());
        }
    }

    /**
     * @param $tables
     * @return array
     * @throws Exception
     */
    public function createTables($tables)
    {
        if (!isset($tables) || empty($tables)) {
            throw new Exception('There are no table sets in the request.', ErrorCodes::BAD_REQUEST);
        }
        if ($this->_isNative) {
            // check for system tables and deny
            $sysPrefix = SystemManager::SYSTEM_TABLE_PREFIX;
            if (isset($tables[0])) {
                foreach ($tables as $table) {
                    $name = Utilities::getArrayValue('name', $table, '');
                    if (0 === substr_compare($name, $sysPrefix, 0, strlen($sysPrefix))) {
                        throw new Exception("Tables can not use the prefix '$sysPrefix'. '$name' can not be created.", ErrorCodes::BAD_REQUEST);
                    }
                }
            }
            else { // single table
                $name = Utilities::getArrayValue('name', $tables, '');
                if (0 === substr_compare($name, $sysPrefix, 0, strlen($sysPrefix))) {
                    throw new Exception("Tables can not use the prefix '$sysPrefix'. '$name' can not be created.", ErrorCodes::BAD_REQUEST);
                }
            }
        }
        $this->checkPermission('create');

        return DbUtilities::createTables($this->_sqlConn, $tables);
    }

    /**
     * @param $table
     * @return array
     * @throws Exception
     */
    public function createTable($table)
    {
        $result = $this->createTables($table);
        return Utilities::getArrayValue(0, $result, array());
    }

    /**
     * @param $table
     * @param $fields
     * @throws Exception
     * @return array
     */
    public function createFields($table, $fields)
    {
        if (empty($table)) {
            throw new Exception('Table name can not be empty.', ErrorCodes::BAD_REQUEST);
        }
        if ($this->_isNative) {
            // check for system tables and deny
            $sysPrefix = SystemManager::SYSTEM_TABLE_PREFIX;
            if (0 === substr_compare($table, $sysPrefix, 0, strlen($sysPrefix))) {
                throw new Exception("Table '$table' not found.", ErrorCodes::NOT_FOUND);
            }
        }
        $this->checkPermission('create', $table);
        try {
            return DbUtilities::createFields($this->_sqlConn, $table, $fields);
        }
        catch (Exception $ex) {
            throw new Exception("Error creating database fields for table '$table'.\n{$ex->getMessage()}", $ex->getCode());
        }
    }

    /**
     * @param $table
     * @param $data
     * @throws Exception
     * @return array
     */
    public function createField($table, $data)
    {
        $result = $this->createFields($table, $data);
        return Utilities::getArrayValue(0, $result, array());
    }

    /**
     * @param $tables
     * @return mixed
     * @throws Exception
     */
    public function updateTables($tables)
    {
        if (!isset($tables) || empty($tables)) {
            throw new Exception('There are no table sets in the request.', ErrorCodes::BAD_REQUEST);
        }
        if ($this->_isNative) {
            // check for system tables and deny
            $sysPrefix = SystemManager::SYSTEM_TABLE_PREFIX;
            if (isset($tables[0])) {
                foreach ($tables as $table) {
                    $name = Utilities::getArrayValue('name', $table, '');
                    if (0 === substr_compare($name, $sysPrefix, 0, strlen($sysPrefix))) {
                        throw new Exception("Tables can not use the prefix '$sysPrefix'. '$name' can not be created.", ErrorCodes::BAD_REQUEST);
                    }
                }
            }
            else { // single table
                $name = Utilities::getArrayValue('name', $tables, '');
                if (0 === substr_compare($name, $sysPrefix, 0, strlen($sysPrefix))) {
                    throw new Exception("Tables can not use the prefix '$sysPrefix'. '$name' can not be created.", ErrorCodes::BAD_REQUEST);
                }
            }
        }
        $this->checkPermission('update');

        return DbUtilities::createTables($this->_sqlConn, $tables, true);
    }

    /**
     * @param $table
     * @return array
     * @throws Exception
     */
    public function updateTable($table)
    {
        $result = $this->updateTables($table);
        return Utilities::getArrayValue(0, $result, array());
    }

    /**
     * @param $table
     * @param $fields
     * @throws Exception
     * @return array
     */
    public function updateFields($table, $fields)
    {
        if (empty($table)) {
            throw new Exception('Table name can not be empty.', ErrorCodes::BAD_REQUEST);
        }
        if ($this->_isNative) {
            // check for system tables and deny
            $sysPrefix = SystemManager::SYSTEM_TABLE_PREFIX;
            if (0 === substr_compare($table, $sysPrefix, 0, strlen($sysPrefix))) {
                throw new Exception("Table '$table' not found.", ErrorCodes::NOT_FOUND);
            }
        }
        $this->checkPermission('update', $table);
        try {
            return DbUtilities::createFields($this->_sqlConn, $table, $fields, true);
        }
        catch (Exception $ex) {
            throw new Exception("Error updating database table '$table'.\n{$ex->getMessage()}", $ex->getCode());
        }
    }

    /**
     * @param $table
     * @param $field
     * @param $data
     * @throws Exception
     * @return array
     */
    public function updateField($table, $field, $data)
    {
        if (!empty($field)) {
            $data['name'] = $field;
        }
        $result = $this->updateFields($table, $data);
        return Utilities::getArrayValue(0, $result, array());
    }

    /**
     * @param $table
     * @return array
     * @throws Exception
     */
    public function deleteTable($table)
    {
        if (empty($table)) {
            throw new Exception('Table name can not be empty.', ErrorCodes::BAD_REQUEST);
        }
        if ($this->_isNative) {
            // check for system tables and deny
            $sysPrefix = SystemManager::SYSTEM_TABLE_PREFIX;
            if (0 === substr_compare($table, $sysPrefix, 0, strlen($sysPrefix))) {
                throw new Exception("Table '$table' not found.", ErrorCodes::NOT_FOUND);
            }
        }
        $this->checkPermission('delete', $table);

        return DbUtilities::dropTable($this->_sqlConn, $table);
    }

    /**
     * @param $table
     * @param $field
     * @throws Exception
     * @return array
     */
    public function deleteField($table, $field)
    {
        if (empty($table)) {
            throw new Exception('Table name can not be empty.', ErrorCodes::BAD_REQUEST);
        }
        if ($this->_isNative) {
            // check for system tables and deny
            $sysPrefix = SystemManager::SYSTEM_TABLE_PREFIX;
            if (0 === substr_compare($table, $sysPrefix, 0, strlen($sysPrefix))) {
                throw new Exception("Table '$table' not found.", ErrorCodes::NOT_FOUND);
            }
        }
        $this->checkPermission('delete', $table);

        return DbUtilities::dropField($this->_sqlConn, $table, $field);
    }

}
