<?php
/**
 * This function returns only a list with the key $key
 *
 * @param array $fetch must be bidimensional
 * @param string $key The key that you want to extract
 * @return array Array with the values ​​of the $key in the array
 */
function extract_from_array($fetch, $key) {
    $result = [];

    foreach ($fetch as $element) {
        $result[] = $element[$key];
    }

    return $result;
}


/**
 * This function returns a list of all the elements whose key $field matches what is passed in $goal
 *
 * @param array $array A bidimensional array
 * @param string $goal It is the value we want to find
 * @param string $field It is the field of the array by which we want to search
 * @return array Array with the found elements
 */
function search_in_array ($array, $goal, $field) {
    $result = [];

    foreach ($array as $key => $element) {
        if ($element[$field] == $goal) {
            $element["array_index"] = $key;
            $result[] = $element;
        }
    }

    return $result;
}

/**
 * This function needs an array and the name of a key that is in that array and its objective
 * is to replace the current key of the two-dimensional array with the value of the selected key
 *
 * @param array $array must contain other arrays inside that contain a field with the key named before
 * @param string $key The key you want to become the primary key
 * @return array Array already formatted
 */
function reindex_array_by_key($array, $key) {
    $result = [];

    foreach ($array as $item) {
        if (isset($item[$key])) {
            $result[$item[$key]] = $item;
            unset($result[$item[$key]][$key]);
        }
    }

    return $result;
}


/**
 * Expects a string like 'varchar(500)' or 'date' and returns a two-dimensional array separating the name from the length
 *
 * @param string $string like 'varchar(500)'
 * @return array Two-dimensional array separating the name from the length. with the keys 'type' and 'length'
 */
function extract_type_and_length($string) {
    $result = [];

    $pos = strpos($string, '(');

    if ($pos !== false) {
        $end = strpos($string, ')', $pos + 1);

        if ($end !== false) {
            $result['length'] = substr($string, $pos + 1, $end - $pos - 1); // Content inside the parentheses
            $result['type'] = substr($string, 0, $pos); // Content outside the parentheses before the first parenthesis
        } else {
            $result['type'] = $string; // Closing parenthesis not found, full string is 'type'
            $result['length'] = '';
        }
    } else {
        $result['type'] = $string; // Parenthesis not found, full string is 'type'
        $result['length'] = '';
    }

    return $result;
}


/**
 * Checks if the passed string matches the passed mask
 *
 * @param string $string to be checked
 * @param string $mask It can be YYYY, MM, DD, hh, mm, ss
 * @return boolean If it complies with the mask
 */
function check_with_mask($string, $mask) {
    // Define regular expression patterns for different masks
    $patterns = [
        'YYYY' => '(19|20)\d\d',
        'MM' => '(0[1-9]|1[0-2])',
        'DD' => '(0[1-9]|[12][0-9]|3[01])',
        'hh' => '([01][0-9]|2[0-3])',
        'mm' => '([0-5][0-9])',
        'ss' => '([0-5][0-9])'
    ];

    $expression = preg_quote($mask, '/');
    $expression = strtr($expresion, $patterns);
    $expression = '/^' . $expression . '$/';

    // Check if the string matches the regular expression
    return preg_match($expression, $string);
}

/**
 * Function to remove a substring from all values ​​in an array. It modifies directly the array.
 *
 * @param array $array The input array.
 * @param string $substring The substring to remove from the values.
 */
function remove_substring_from_array(&$array, $substring) {
    foreach ($array as &$value) {
        $value = str_replace($substring, '', $value);
    }
}



/**
 * This function interleave 2 arrays in a string
 *
 * @param array $array1
 * @param array $array2
 * @param string $separator1 It is what separates one element of the array from another
 * @param string $separator2 It is the one that separates combinations of the arrays
 * @return string It would look like this (for each element of the array) array1[0] separator1 array2[0] separator2 array1[1] separator1 array2[1]
 */
function combine_arrays($array1, $array2, $separator1, $separator2) {
    $resultado = '';

    // Determines the number of elements to iterate, taking the minimum between the length of both arrays
    $count = min(count($array1), count($array2));

    // Iterates over arrays to combine their values
    for ($i = 0; $i < $count; $i++) {
        // Adds the value of the first array followed by separator1 and the value of the second array
        $resultado .= $array1[$i] . $separator1 . $array2[$i];

        // If it is not the last pair of values, add separator2 between the pairs
        if ($i < $count - 1) {
            $resultado .= $separator2;
        }
    }

    return $resultado;
}

/**
 * Extracts words from the string that start with the given prefix
 * @param string $query The SQL query with variables like :name
 *
 * @return array With words that contain the prefix but returns them without the prefix
 */
function extract_words_with_prefix($query) {
    // Regular expression pattern to extract variables that start with ":"
    $pattern = '/:(\w+)/';

    // Find all matches of the pattern in the query
    preg_match_all($pattern, $query, $matches);

    // Returns the names of the variables found (without the ':' prefix)
    return $matches[1];
}

/**
 * Gets the matching keys from the array of keys, throws an error if any key is missing
 *
 * @param array $keys Keys that must be present in the given array
 * @param array $array Array with key-value pairs
 * @return array Elements of the array whose keys match
 */
function keys_in_array($keys, $array) {
    $coincidencias = [];

    foreach ($keys as $clave) {
        if (array_key_exists($clave, $array)) {
            $coincidencias[$clave] = $array[$clave];
        } else {
            throw new PDOException("Error de PDO: Clave '$clave' no encontrada en el array.");
        }
    }

    return $coincidencias;
}


class database {
    private $properties; // Array with the initial properties of the class
    private $conn; // conection variable
    private $tables; // array with table name as the key and the column values as value
    private $tables_names; // An array with all tables names
    private $tables_with_column_details; // Array with extra information about tables
    private $tables_PK; // Array with the primary key of each table
    private $tables_FK; // Array with the foreigns key of each table: table_name => [column => reference_table]
    private $sql_types; // Array with database types

    function __construct ($ppt) {
        $this->properties = $ppt;
        $this->save_tables();
    }


    /**
     * Setter for sql_types, which are the database types
     *
     * @param array $types The array of types
     * @return void
     */
    function setTypes($types) {
        $this->sql_types = $types;
    }


    /**
     * Setter for conn, which is the database connection
     *
     * @param object $conn The database connection
     * @return void
     */
    function setConection($conn) {
        $this->conn = $conn;
    }


    /**
     * Returns the tables of the current database
     *
     * @return array The database tables
     */
    function get_tables () {
        $stmt = $this->conn->query("SHOW TABLES");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Returns the columns of the specified table
     *
     * @param string $table_name The name of the table whose columns you want to retrieve
     * @param boolean $ignore_primary_key Whether to exclude the primary key from the columns
     * @return array An associative array with column names as keys and their configurations (type, nullability, etc.) as values
     */
    function get_columns($table_name, $ignore_primary_key=true) {
        // Obtain column details of a table
        $stmt = $this->conn->prepare("SHOW COLUMNS FROM $table_name");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If ignore primary key is true, delete it from column array
        if ($ignore_primary_key) {
            foreach ($columns as $key => $column) {
                if ($column['Key'] == 'PRI') {
                    unset($columns[$key]);
                }
            }
        }

        return $columns;
    }

    /**
     * Returns the columns of the specified table that match the given key type (foreign or primary)
     *
     * @param string $table_name The name of the table whose columns you want to retrieve
     * @param string $key The type of key to retrieve from the table, which can be primary (PRI) or foreign (MUL)
     * @return array An associative array with column names as keys and their configurations (type, nullability, etc.) as values
     */
    function get_columns_by_key($table_name, $key) {
        if ($key == "primary") {
            $key = "PRI";
        }

        if ($key == "foreign") {
            $key = "MUL";
        }

        $stmt = $this->conn->prepare("SHOW COLUMNS FROM $table_name");
        $stmt->execute();

        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $fetch = $stmt->fetchAll();


        $filtered_columns = array_filter($fetch, function($column) use ($key) {
            return $column['Key'] == $key;
        });

        $columns = array_column($filtered_columns, 'Field');
        $result = array();

        if ($key == "MUL") {

            foreach ($columns as $column) {
                $referenced_table = $this->get_referenced_table($table_name, $column);
                $result[$column] = $referenced_table;
            }

        }else {
            $result = $columns;
        }

        return $result;
    }

    /**
     * Fill the variables with information about the database tables
     *
     * @return void
     */
    function save_tables() {
        $table_list = $this->get_tables();

        $this->tables_names = $table_list;
        $this->tables_with_column_details = array();

        foreach ($table_list as $table_element) {
            $column_details = $this->get_columns($table_element);
            $this->tables_with_column_details[$table_element] = reindex_array_by_key($column_details, "Field"); // Make column_name index
            $this->tables[$table_element] = extract_from_array($column_details, "Field");
            $this->tables_PK[$table_element] = $this->get_columns_by_key($table_element, "primary");
            $this->tables_FK[$table_element] = $this->get_columns_by_key($table_element, "foreign");
        }
    }

   /**
     * Executes the given query and returns the result
     *
     * @param string $query The query string to execute
     * @param array $variables Variables used in the query as key => value pairs, where the query uses :key as placeholders
     * @return object The result of the query execution
     */
    function execute_query($query, $variables=[]) {
        $variables_in_query = extract_words_with_prefix($query);

        remove_substring_from_array($variables_in_query, ",");
        remove_substring_from_array($variables_in_query, ")");

        $variables = keys_in_array($variables_in_query, $variables);

        if (in_array("@lastInsertId", $variables)) {
            $indiceElemento = array_search("@lastInsertId", $variables);
            $variables[$indiceElemento] = $this->getLastInsertId();
        }


        try {
            $stmt = $this->conn->prepare($query);

            if ($stmt->execute($variables)) {
                return $stmt;

            } else {
                throw new PDOException("Error en la ejecución de la consulta: " . print_r($stmt->errorInfo(), true));
            }

        } catch (PDOException $e) {
            throw new PDOException("Error de PDO en $query: " . $e->getMessage());
        }

    }



    /**
     * Executes the given query and returns the result
     *
     * @param string $query The query string to execute
     * @param array $variables Variables that the query contains as key => value pairs, where the query uses :key as placeholders
     * @param bool $return_json Whether to return the result in JSON format. Default is true
     * @return array The query result
     */
    function select_stmt($query, $variables=[], $return_json=true) {
        $result = $this->execute_query($query, $variables)->fetchAll(PDO::FETCH_ASSOC);

        if ($return_json) {

            $result =  json_encode($result);

            if ($result === false) {
                throw new PDOException("Error en json_encode: " . json_last_error_msg());
            }
        }

        return $result;
    }

    /**
     * Returns all fields from a table with conditions
     *
     * @param string $table The table you want to select the data
     * @param string $where The conditions of the select
     * @param array $variables variables that the query contains, key => value, in the query you would put :key
     * @param bool $return_json If you want it returned in json. By default it is true
     * @return array
     */
    function select_all($table, $where="", $variables=[], $return_json=true)
    {
        $query = "SELECT * FROM " . $table;
        
        if ($where !== "") {
            $query .= " WHERE " . $where;
        }
        
        return select_stmt($query, $variables, $return_json);
    }

    /**
     * Generates the limit for pagination
     *
     * @param int $pageNumber The page number to retrieve
     * @param int $step The number of records to retrieve per page
     * @return string The pagination limit
     */
    function generatePagination($pageNumber, $step) {
        // If $step is 100 and $pageNumber is 1 (Is in 1º page) then $end would be 100 and $start 0
        $end = $pageNumber * $step;
        $start = $end - $step;

        return "LIMIT $step OFFSET $start";
    }

    /**
     * This function validates the columns to ensure they meet the type, length, and nullability requirements
     *
     * @param array $variables The variables to validate, with the column name as the key and the value to be validated. Example: column => value
     * @param array $config The column configuration, which is a multidimensional array containing the column name as the key and an array with 'Type' (e.g., varchar(500) or text) and 'Null' (YES or NO) as values
     * @return array If all columns are valid, it returns true in the "validation" key; if not, the "reason" key contains the validation failure reason
     */
    function validate_column_constraints($variables, $config) {
        // This function validates the columns to ensure they meet the type, length, and nullability requirements
        // The type is labeled as "Type" and can have values like varchar(500) or text.
        // The length is extracted from the type based on the value inside the parentheses.
        // The "Null" key indicates whether the column can be null, with possible values of YES or NO.

        $everything_correct = true;
        $reason = "";

        foreach ($variables as $key => $value) {
            $type = extract_type_and_length($config[$key]["Type"])["type"];
            $length = extract_type_and_length($config[$key]["Type"])["length"];

            // Check length
            if (!is_numeric($length) and $length != "") {
                $reason = "Error, the length of the field $key in $config[$key]['Type'] must be numeric";
                $everything_correct = false;
                break;
            }else {
                $length = intval($length);
            }

            // Check null
            if (empty($value) and $value != 0 and $config[$key]["Null"] != "YES") {
                $reason =  "Error, the field $key cannot be empty";
                $everything_correct = false;
                break;

            }else if ((empty($value) or $value == null) and $value != 0 and $config[$key]["Null"] == "YES") {
                continue;
            }

            // Check max length
            if ($length != 0 and $length < strlen($value)) {
                $length = strval($length);
                $reason = "Error, the field $key exceeds the maximum length of $length characters";
                $everything_correct = false;
                break;
            }

            $types = $this->sql_types;

            // Checks per type
            if (in_array($type, $types["texto"])) {

                if (!is_string($value)) {
                    $reason = "The field $key was expected to be text";
                    $everything_correct = false;
                    break;
                }

            }elseif (in_array($types, $tipos["numero"])) {

                if (!is_numeric($value)) {
                    $reason = "The field $key was expected to be a number";
                    $everything_correct = false;
                    break;
                }

                if ($type == "int" && strpos($value, '.')) {
                    $reason = "The field $key cannot have decimals";
                    $everything_correct = false;
                    break;
                }

            }elseif (in_array($type, $tipos["tiempo"])) {
                $result = true;

                if ($type == "date") {
                    $result = check_with_mask($value, "YYYY-MM-DD");

                }elseif ($type == "time" and !(check_with_mask($value, "hh:mm") || check_with_mask($value, "hh:mm:ss"))) {
                    $result = false;

                }elseif ($type == "datetime" || $type == "timestamp") {
                    $result = check_with_mask($value, "YYYY-MM-DD hh:mm:ss");
                }

                if (!$result) {
                    $reason = "The field $key does not meet the $type format";
                    $everything_correct = false;
                    break;
                }
            }

        }

        return ["validation"=> $everything_correct, "reason"=> $reason];
    }


    /**
     * Inserts the record you specify after validating its fields
     *
     * @param string $table The selected table
     * @param array $variables The array of variables that will be used in the query in the same order as they appear in the database (no need to include column names)
     * @return void
     */
    function insert_stmt($table, $variables) {
        // This function inserts all columns except primary key
        $columns_variables = ":" . implode(", :" ,$this->tables[$table]);

        $column_names = implode(', ' ,$this->tables[$table]);

        if (!in_array($table, $this->tables_names)) {
            throw new PDOException("The table $table doesnt exist");
        }

        if (count($this->tables[$table]) != count($variables)) {
            throw new PDOException("Error, se le deben pasar todos los campos a excepcion de la PK en $table");
        }

        $values = array_combine($this->tables[$table], $variables); // This variable has columns names as key and $variables values

        $validation = $this->validate_column_constraints($values, $this->tables_with_column_details[$table]);

        if ($validation["validation"]) {
            $query = "INSERT INTO $table ($column_names) VALUES ($columns_variables)";
            $this->execute_query($query, $values);
        }else {
            throw new PDOException($validation["reason"]);
        }
    }

   /**
     * Edits the record you specify after validating its fields
     *
     * @param string $table The selected table
     * @param array $variables The array of variables that will be used in the query in the same order as they appear in the database (no need to include column names)
     * @param int $ID The ID of the record you want to edit
     * @return void
     */
    function update_stmt($table, $variables, $ID) {
        // This function is used to update all fields except the primary key, which will be passed via $ID (the value)
        $columns_variables = ":" . implode(", :", $this->tables[$table]);

        if (!in_array($table, $this->tables_names)) {
            throw new PDOException("Table $table does not exist");
        }

        if (!is_numeric($ID)) {
            throw new PDOException("The ID $ID must be numeric");
        }

        if (count($this->select_one($table, $ID)) != 1) {
            throw new PDOException("Error, no record found in $table");
        }

        if (count($this->tables[$table]) != count($variables)) {
            throw new PDOException("Error, all fields must be provided except the PK in $table");
        }

        $columns_variables = explode(", ", $columns_variables);

        $content = combine_arrays($this->tables[$table], $columns_variables, " = ", ", ");
        $values = array_combine($this->tables[$table], $variables); // This makes the variable have the column names as keys and variables as values

        $validation = $this->validate_column_constraints($values, $this->tables_with_column_details[$table]);

        if ($validation["validation"]) {

            $query = "UPDATE $table set $content where {$this->tables_PK[$table][0]} = $ID";
            $this->execute_query($query, $values);
        } else {
            throw new PDOException($validation["reason"]);
        }
    }

   /**
     * Executes an SQL transaction
     *
     * @param array $queries List of queries to execute
     * @param array $variables List of query parameters (one-dimensional array)
     * @return bool Whether the transaction was executed successfully
     */
    public function executeTransaction($queries, $variables=null) {
        if (empty($queries)) {
            return false;
        }

        try {
            $this->conn->setAttribute(PDO::ATTR_AUTOCOMMIT, 0);
            $this->conn->beginTransaction();

            foreach ($queries as $key => $query) {
                $this->execute_query($query, $variables);
            }

            $this->conn->commit();
            $this->conn->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);

            return true;

        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Returns the number of records in a table
     *
     * @param string $table The selected table
     * @param string $conditions The conditions for the count, it's not mandatory to provide it
     * @return int The number of records
     */
    public function countFromTable($table, $conditions=1) {
        if ($conditions == 1) {
            $stmt = "SELECT COUNT(*) as cuenta FROM " . $table;
        }else {
            $stmt = "SELECT COUNT(*) as cuenta FROM " . $table . " WHERE ". $conditions;
        }

        $result = $this->select_stmt($stmt, [], false);
        return $result[0]["cuenta"];
    }

    /**
     * Returns the last inserted ID
     *
     * @return int The last inserted ID
     */
    function getLastInsertId() {
        return $this->conn->lastInsertId();
    }


    /**
     * Deletes the specified record
     *
     * @param string $table The selected table
     * @param string $id The ID (primary key) of the record you want to delete
     * @return void
     */
    function delete_stmt($table, $id) {
        if (!in_array($table, $this->tables_names)) {
            throw new PDOException("No existe la tabla $table");
        }

        if (!is_numeric($id)) {
            throw new PDOException("El id $id tiene que ser numerico");
        }

        $sql = "DELETE FROM $table where {$this->tables_PK[$table][0]} = :id";
        $variables = [
            "id" => $id
        ];

        $this->execute_query($sql, $variables);
    }


    /**
     * Performs a SELECT query to retrieve data from a table, including columns that are not primary or foreign keys.
     * If a foreign key is found, it performs a LEFT JOIN with the referenced table and selects the first field that is not a primary or foreign key.
     *
     * @param string $table_name The name of the table from which data will be retrieved.
     * @param bool $return_json Whether to return the result in JSON format. Default is true.
     * @return array The result of the SELECT query.
     */
    function simple_select($table_name, $return_json=true) {
        $columns = $this->tables_with_column_details[$table_name];
        $selected_columns = [];
        $join_tables = $this->tables_FK[$table_name];

        if (!in_array($table_name, $this->tables_names)) {
            throw new PDOException("No existe la tabla $table_name");
        }

        foreach ($columns as $key => $column) {
            if ($column['Key'] != 'PRI' && $column['Key'] != 'MUL') {
                $selected_columns[] = $key;

            } elseif ($column['Key'] == 'MUL') {
                // If a foreign key is found, it adds a LEFT JOIN with the referenced table and selects the first field that is neither a primary key (PK) nor a foreign key (FK)
                $referenced_table = $this->tables_FK[$table_name][$key];
                $selected_columns[] = "$referenced_table." . $this->get_first_non_key_column($referenced_table);
            }
        }


        if (empty($selected_columns)) {
            foreach ($columns as $key => $column) {
                $selected_columns[] = $key;
            }
        }

        $selected_columns_str = implode(', ', $selected_columns);
        $query = "SELECT $selected_columns_str FROM $table_name";

        foreach ($join_tables as $key => $join_table) {
            $id_foreign = $this->tables_PK[$join_table][0];

            $query .= " LEFT JOIN $join_table ON $table_name.$key = $join_table.$id_foreign";
        }

        if ($return_json) {
            return $this->select_stmt($query, []);
        }else {
            return $this->select_stmt($query, [], false);
        }
    }


    /**
     * Searches and returns the name of the first column that is neither a primary key nor a foreign key in the specified table.
     *
     * @param string $table_name The name of the table in which to search for the first non-key column.
     * @return string|null The name of the first non-key column found, or null if none is found.
     */
    function get_first_non_key_column($table_name) {
        $columns = $this->tables_with_column_details[$table_name];

        foreach ($columns as $key => $column) {
            if ($column['Key'] != 'PRI' && $column['Key'] != 'MUL') {
                return $key;
            }
        }
        return null; // Not found any PK or FK in table
    }


    /**
     * Retrieves the name of the table that a foreign key column references in a given table.
     *
     * @param string $table_name The name of the table containing the foreign key column.
     * @param string $column_name The name of the foreign key column.
     * @return string|null The name of the table referenced by the foreign key column, or null if no referenced table is found.
     */
    function get_referenced_table($table_name, $column_name) {
        $stmt = $this->conn->prepare("SHOW CREATE TABLE $table_name");
        $stmt->execute();
        $table_info = $stmt->fetch(PDO::FETCH_ASSOC);

        // Obtain table definition
        $table_definition = $table_info['Create Table'];

        // Search column definition
        preg_match_all('/CONSTRAINT `[^`]+` FOREIGN KEY \(`' . $column_name . '`\) REFERENCES `([^`]+)` \(`[^`]+`\)/', $table_definition, $matches);

        // If matches are found, the referenced table is the first captured element in the regular expression
        if (isset($matches[1][0])) {
            return $matches[1][0];
        } else {
            // If no reference is found, return null or a value indicating no referenced table
            return null;
        }
    }

    /**
     * Retrieves 1 record from the selected table based on the passed id
     *
     * @param string $table Name of the table
     * @param string|int $id Id of the record to be retrieved
     * @return array The resulting record
     */
    function select_one($table, $id, $json_encode=false) {
        if (!in_array($table, $this->tables_names)) {
            throw new PDOException("No existe la tabla $table");
        }

        if (!is_numeric($id)) {
            throw new PDOException("El id $id tiene que ser numerico");
        }

        $PK = $this->tables_PK[$table][0];
        $query = "SELECT * FROM $table where $PK = :id limit 1";

        return $this->select_stmt($query, ["id"=> $id], $json_encode);
    }
}

class mysql extends database
{
    public function __construct($ppt)
    {
        parent::setTypes(array(
            'texto' => array('char', 'varchar', 'text', 'tinytext', 'mediumtext', 'longtext'),
            'numero' => array('int', 'float', 'decimal', 'double'),
            'tiempo' => array('date', 'datetime', 'timestamp', 'time')
        ));

        $servername = $ppt["serverName"];
        $username = $ppt["username"];
        $password = $ppt["password"];
        $DB = $ppt["DB"];
        $codification = $ppt["codification"];

        try {
            $conn = new PDO("mysql:host=$servername;dbname=$DB;charset=$codification", $username, $password);
            // set the PDO error mode to exception
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            parent::setConection($conn);
            parent::__construct($ppt);

        } catch(PDOException $e) {
            throw new Exception("Connection failed: " . $e->getMessage());
        }
    }

}

class sql extends database
{
    public function __construct($ppt)
    {
        parent::setTypes(array(
            'texto' => array('char', 'varchar', 'text', 'tinytext', 'mediumtext', 'longtext'),
            'numero' => array('int', 'float', 'decimal', 'double'),
            'tiempo' => array('date', 'datetime', 'timestamp', 'time')
        ));

        $servername = $ppt["serverName"];
        $username = $ppt["username"];
        $password = $ppt["password"];
        $DB = $ppt["DB"];
        $codification = $ppt["codification"];

        try {
            $conn = new PDO("sqlsrv:Server=$servername;Database=$DB;charset=$codification", $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            parent::setConection($conn);
            parent::__construct($ppt);

        } catch (PDOException $e) {
            throw new Exception("Connection failed: " . $e->getMessage());
        }
    }
}



$properties = [
    "serverName" => "localhost",
    "username" => "root",
    "password" => "",
    "DB" => "your_database",
    "codification" => "utf-8"
];


$database_object = new mysql($properties);

?>
