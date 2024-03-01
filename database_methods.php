<?php
/**
 * Esta funcion devuelve solo una lista con la key $key
 *
 * @param array $fetch Debe ser bidimensional
 * @param string $key La clave que se quiere sacar
 * @return array Array con los valores de la $key en la array
 */
function extract_from_array($fetch, $key) {
    // La lista pasada debe ser bidimensional y la $key el nombre del campo
    // Esta funcion devuelve solo una lista con la key $key

    $result = [];

    foreach ($fetch as $element) {
        $result[] = $element[$key];
    }

    return $result;
}


/**
 * Esta funcion devuelve una lista con todos los elementos que la key $field coincida con lo pasado en $goal
 *
 * @param array $array Una array bidimensional
 * @param string $goal Es el valor que queremos buscar
 * @param string $field Es el campo de la array por el que queremos buscar
 * @return array Array con los elementos encontrados
 */
function search_in_array ($array, $goal, $field) {
    // La array debe ser bidimensional
    // Esta funcion devuelve una lista con todos los elementos que la key $field coincida con lo pasado en $goal

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
 * Esta funcion necesita una array y un nombre de una key que este en esa array y
 * tiene como objetivo sustituir la key actual de la array bidimensional por el valor de la key seleccionada
 *
 * @param array $array debera contener otros arrays adentro que contengan un campo con la key nombrada antes
 * @param string $key La key que quieres que se convierta en la key principal
 * @return array Array ya formateada
 */
function reindex_array_by_key($array, $key) {
    // Esta funcion necesita una array y un nombre de una key que este en esa array
    // La array debera contener otras array adentro que contengan un campo con la key nombrada antes
    // Esta funcion tiene como objetivo sustituir la key actual de la array bidimensional por el valor de la key seleccionada

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
 * Espera string como varchar(500) o date y devuelve un array bidimesional separando el nombre de la longitud
 *
 * @param string $string string como varchar(500)
 * @return array array bidimesional separando el nombre de la longitud. con las keys type y length
 */
function extract_type_and_length($string) {
    // Espera string como varchar(500) o date y devuelve un array bidimesional separando el nombre de la longitud

    $result = [];

    $pos = strpos($string, '(');

    if ($pos !== false) {
        $end = strpos($string, ')', $pos + 1);

        if ($end !== false) {
            $result['length'] = substr($string, $pos + 1, $end - $pos - 1); // Contenido dentro de los paréntesis
            $result['type'] = substr($string, 0, $pos); // Contenido fuera de los paréntesis antes del primer paréntesis
        } else {
            $result['type'] = $string; // No se encontró el paréntesis de cierre, el string completo es 'type'
            $result['length'] = '';
        }
    } else {
        $result['type'] = $string; // No hay paréntesis, el string completo es 'type'
        $result['length'] = '';
    }

    return $result;
}


/**
 * Comprueba si la string pasada cumple con la masacra pasada
 *
 * @param string $cadena Cdena que se va a comprobar
 * @param string $mascara Mascara, se le puede pasar YYYY, MM, DD, hh, mm, ss
 * @return boolean Si cumple con la mascara
 */
function check_with_mask($cadena, $mascara) {
    // Definimos patrones de expresiones regulares para diferentes máscaras
    $patterns = [
        'YYYY' => '(19|20)\d\d',
        'MM' => '(0[1-9]|1[0-2])',
        'DD' => '(0[1-9]|[12][0-9]|3[01])',
        'hh' => '([01][0-9]|2[0-3])',
        'mm' => '([0-5][0-9])',
        'ss' => '([0-5][0-9])'
    ];

    // Escapamos los caracteres especiales de la máscara para usarlos en una expresión regular
    $expresion = preg_quote($mascara, '/');

    // Reemplazamos los caracteres de la máscara por expresiones regulares que coincidan
    $expresion = strtr($expresion, $patterns);

    // Agregamos marcadores de inicio y fin para asegurarnos de que la cadena coincida completamente
    $expresion = '/^' . $expresion . '$/';

    // Verificamos si la cadena coincide con la expresión regular
    return preg_match($expresion, $cadena);
}


/**
 * Esta funcion es para intercalar 2 arrays en una string
 *
 * @param array $array1
 * @param array $array2
 * @param string $separador1 es el que sepaar un elemento de la array de otro
 * @param string $separador2 es el que separa combinaciones de los arrays
 * @return string quedaria asi (por cada elemento del array) array1[0] separador1 array2[0] separador2 array1[1] separador1 array2[1]
 */
function combine_arrays($array1, $array2, $separador1, $separador2) {
    // Esta funcion es para intercalar 2 arrays en una string
    // El separador 1 es el que sepaar un elemento de la array de otro
    // El separador 2 es el que separa combinaciones de los arrays
    // El resultado quedaria asi (por cada elemento del array) array1[0] separador1 array2[0] separador2 array1[1] separador1 array2[1]

    $resultado = '';

    // Determina la cantidad de elementos a iterar, tomando el mínimo entre la longitud de ambos arrays
    $count = min(count($array1), count($array2));

    // Itera sobre los arrays para combinar sus valores
    for ($i = 0; $i < $count; $i++) {
        // Agrega el valor del primer array seguido del separador1 y el valor del segundo array
        $resultado .= $array1[$i] . $separador1 . $array2[$i];

        // Si no es el último par de valores, agrega el separador2 entre los pares
        if ($i < $count - 1) {
            $resultado .= $separador2;
        }
    }

    // Devuelve la cadena combinada
    return $resultado;
}

/**
 * Exctrae las palabras de la cadena que empiezen por el prefijo dado
 *
 * @param string $cadena Cadena de la que se quiere extraer las palabras
 * @param string $prefijo Prefijo por el que tienen que empezar las palabras para que se extraigan
 * @return array Con las palabras que contenian el prefijo pero te las devuelve sin el prefijo
 */
function extract_words_with_prefix($cadena, $prefijo) {
    // Dividir la cadena en palabras
    $palabras = explode(" ", $cadena);
    $palabrasConPrefijo = [];

    // Iterar sobre las palabras
    foreach ($palabras as $palabra) {
        // Comprobar si la palabra comienza con el prefijo dado
        if (strpos($palabra, $prefijo) === 0) {
            // Agregar la palabra al arreglo de palabras con prefijo
            $palabrasConPrefijo[] = substr($palabra, strlen($prefijo));
        }
    }

    // Retornar el arreglo de palabras con prefijo
    return $palabrasConPrefijo;
}


/**
 * Obtiene las coincidencias de claves con la array de claves, sino encuentra una da error
 *
 * @param array $keys Claves que quieres que esten presentes en el array pasado
 * @param array $array Array con clave, valor
 * @return array elementos del array cuyas claves coincidan
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
    private $properties; // Array con las propiedades iniciales de la clase
    private $conn; // variable de conexion
    private $tables; // Array con la key el nombre de la tabla y valores los nombres de las columnas
    private $tables_names; // Solo una array con los nombres de las tablas
    private $tables_with_column_details; // Array igual que $tables pero con  mas informacion de sus columnas
    private $tables_PK; // Las tablas con su primary key
    private $tables_FK; // Las tablas con sus foreigns keys con la tabla a la que hacen referencia como value. columna => tabla_referencia
    private $sql_types; // Tipos de datos de mysql para la validacion

    function __construct ($ppt) {
        $this->properties = $ppt;
        $this->save_tables();
    }


    /**
     * Setter de sql_types, que son los tipos de BBDD
     *
     * @param array $types Es la array de los tipos
     * @return void
     */
    function setTypes($types)
    {
        $this->sql_types = $types;
    }


    /**
     * Setter de conn, que es la conexion a la BBDD
     *
     * @param object $conn Es la conexion a la BBDD
     * @return void
     */
    function setConection($conn)
    {
        $this->conn = $conn;
    }


    /**
     * Devuelve las tablas de la base de datos actual
     *
     * @return array Las tablas de la BBDD
     */
    function get_tables () {
        $stmt = $this->conn->query("SHOW TABLES");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Devuelve las columnas de la tabla que le pases
     *
     * @param string $table_name es el nombre de la tabla de la que se quiere sacar las columnas
     * @param boolean $ignore_primary_key Si quieres sacar todas las columnas menos la primary key
     * @return array tiene la key como el nombre de la columna y como valores la configuracion de ellas (tipo, null...)
     */
    function get_columns($table_name, $ignore_primary_key=true)
    {
        // Obtener los detalles de las columnas de la tabla
        $stmt = $this->conn->prepare("SHOW COLUMNS FROM $table_name");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Si se debe ignorar la clave primaria y existe, eliminarla de la lista
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
     * Devuelve las columnas de la tabla que le pases y cuya key coincida con la pasada (foreign o primary)
     *
     * @param string $table_name es el nombre de la tabla de la que se quiere sacar las columnas
     * @param string $key Es el tipo de key que se quiere sacar de la tabla, que puede ser primary (PRI) o foreign (MUL)
     * @return array tiene la key como el nombre de la columna y como valores la configuracion de ellas (tipo, null...)
     */
    function get_columns_by_key($table_name, $key)
    {
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
     * Mete la informacion sobre las tablas de la base de datos en las variables
     *
     * @return void
     */
    function save_tables() {
        $table_list = $this->get_tables();

        $this->tables_names = $table_list;
        $this->tables_with_column_details = array();

        foreach ($table_list as $table_element) {
            $column_details = $this->get_columns($table_element);
            $this->tables_with_column_details[$table_element] = reindex_array_by_key($column_details, "Field"); // Paso a hacer que el nombre de la columna sea el nuevo indice
            $this->tables[$table_element] = extract_from_array($column_details, "Field");
            $this->tables_PK[$table_element] = $this->get_columns_by_key($table_element, "primary");
            $this->tables_FK[$table_element] = $this->get_columns_by_key($table_element, "foreign");
        }
    }

    /**
     * Ejecuta la consulta que le pase y te devuelve el resultado
     *
     * @param string $query Numero de pagina que se quiere obtener
     * @param array $variables variables que contiene la consulta, key => value, en la consulta se pondria :key
     * @return object
     */
    function execute_query($query, $variables=[])
    {
        // El formato de $variables debe ser ['lastname' => 'Perez', 'name' => 'Jose']
        // El formato de $query debe ser "SELECT * FROM MyGuests WHERE lastname = :lastname and name = :name"
        $variables_in_query = extract_words_with_prefix($query, ":");
        $variables = keys_in_array($variables_in_query, $variables);

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
     * Ejecuta la consulta que le pase y te devuelve el resultado
     *
     * @param string $query Numero de pagina que se quiere obtener
     * @param array $variables variables que contiene la consulta, key => value, en la consulta se pondria :key
     * @param bool $return_json Si quieres que te lo devuelva en json. Por defecto es true
     * @return array
     */
    function select_stmt($query, $variables=[], $return_json=true)
    {
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
     * Genera el limit de la paginacion
     *
     * @param int $pageNumber Numero de pagina que se quiere obtener
     * @param int $step Numero de registros que se obtienen por pagina
     * @return String
     */
    function generatePagination($pageNumber, $step)
    {
        // Si $step es 100 y $pageNumber es 1 (esta en la 1º pagina) entonces $end seria 100 y $start 0
        $end = $pageNumber * $step;
        $start = $end - $step;

        return "LIMIT $step OFFSET $start";
    }

    /**
     * Esta funcion valida las columnas para que cumplan con el tipo, longitud y si puede ser null
     *
     * @param array $variables Son las variables su formato es el nombre de la columna como key y el valor que queremos validar. columna => valor
     * @param array $config La configuracion de las columnas, es bidimensional y contiene como key el nombre de la columna y como valor otro array con Type (el tipo. Ej: varchar(500) o text) y Null que contiene YES o NO
     * @return array Si todas las columnas estan bien devuelve true en la key "validation" y si es false en la key reason esta el motivo
     */
    function validate_column_constraints($variables, $config)
    {
        // Esta funcion valida las columlnas para que cumplan con el tipo, longitud y si puede ser null
        // El tipo tiene de nombre Type y pueden ser valores como varchar(500) o text
        // La longitud se saca del tipo segun tenga en el parentesis
        // El NULL tiene por nombre Null en la configuracion y puede ser YES o NO

        $everything_correct = true;
        $reason = "";

        foreach ($variables as $key => $value) {
            $type = extract_type_and_length($config[$key]["Type"])["type"];
            $length = extract_type_and_length($config[$key]["Type"])["length"];

            // Establecer la longitud
            if (!is_numeric($length) and $length != "") {
                $reason = "Error, la longitud del campo $key en $config[$key]['Type'] debe ser numerica";
                $everything_correct = false;
                break;
            }else {
                $length = intval($length);
            }

            // Comprobar el Null
            if (empty($value) and $value != 0 and $config[$key]["Null"] != "YES") {
                $reason =  "Error, el campo $key no puede estar vacio";
                $everything_correct = false;
                break;

            }else if ((empty($value) or $value == null) and $value != 0 and $config[$key]["Null"] == "YES") {
                continue;
            }


            // COmprobar si supera la longitud
            if ($length != 0 and $length < strlen($value)) {
                $length = strval($length);
                $reason = "Error, el campo $key supera la longitud maxima de $length caracteres";
                $everything_correct = false;
                break;
            }


            $tipos = $this->sql_types;

            if (in_array($type, $tipos["texto"])) {

                if (!is_string($value)) {
                    $reason = "El campo $key se esperaba como texto";
                    $everything_correct = false;
                    break;
                }

            }elseif (in_array($type, $tipos["numero"])) {

                if (!is_numeric($value)) {
                    $reason = "El campo $key se esperaba como numero";
                    $everything_correct = false;
                    break;
                }

                if ($type == "int" && strpos($value, '.')) {
                    $reason = "El campo $key no puede tener decimales";
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
                    $reason = "El campo $key no cumple con el formato $type";
                    $everything_correct = false;
                    break;
                }
            }

        }

        return ["validation"=> $everything_correct, "reason"=>$reason];
    }

    /**
     * Inserta el registro que le indiques despues de validar sus campos
     *
     * @param String $table Es la tabla seleccionada
     * @param array $variables Es la array de variables que se van a poner en la consulta en el orden que esten en la BBDD (no hay que poner claves)
     * @return void
     */
    function insert_stmt($table, $variables)
    {
        // Esta funcion se usa si se quiere insertar todos los campos a excepcion de la primary key
        $columns_variables = ":" . implode(", :" ,$this->tables[$table]);

        $column_names = implode(', ' ,$this->tables[$table]);

        if (!in_array($table, $this->tables_names)) {
            throw new PDOException("No existe la tabla $table");
        }

        if (count($this->tables[$table]) != count($variables)) {
            throw new PDOException("Error, se le deben pasar todos los campos a excepcion de la PK en $table");
        }

        $values = array_combine($this->tables[$table], $variables); // Hago que esta variable tenga como keys $column_names y como valores $variables

        $validation = $this->validate_column_constraints($values, $this->tables_with_column_details[$table]);

        if ($validation["validation"]) {
            $query = "INSERT INTO $table ($column_names) VALUES ($columns_variables)";
            $this->execute_query($query, $values);
        }else {
            throw new PDOException($validation["reason"]);
        }
    }

    /**
     * Edita el registro que le indiques despues de validar sus campos
     *
     * @param String $table Es la tabla seleccionada
     * @param array $variables Es la array de variables que se van a poner en la consulta en el orden que esten en la BBDD (no hay que poner claves)
     * @param Int $ID Es el Id del registo que se quiere editar
     * @return void
     */
    function update_stmt($table, $variables, $ID)
    {
        // Esta funcion se usa si se quiere actualizar todos los campos a excepcion de la primary key, que pasaremos por $ID (el valor)
        $columns_variables = ":" . implode(", :" ,$this->tables[$table]);

        if (!in_array($table, $this->tables_names)) {
            throw new PDOException("No existe la tabla $table");
        }

        if (!is_numeric($ID)) {
            throw new PDOException("El id $ID tiene que ser numerico");
        }

        if (count($this->select_one($table, $ID)) != 1) {
            throw new PDOException("Error, no se ha encontrado ningun registro en $table");
        }

        if (count($this->tables[$table]) != count($variables)) {
            throw new PDOException("Error, se le deben pasar todos los campos a excepcion de la PK en $table");
        }

        $columns_variables = explode(", ", $columns_variables);

        $content = combine_arrays($this->tables[$table], $columns_variables, " = ", ", ");
        $values = array_combine($this->tables[$table], $variables); // Hago que esta variable tenga como keys $column_names y como valores $variables

        $validation = $this->validate_column_constraints($values, $this->tables_with_column_details[$table]);

        if ($validation["validation"]) {

            $query = "UPDATE $table set $content where {$this->tables_PK[$table][0]} = $ID";
            $this->execute_query($query, $values);
        }else {
            throw new PDOException($validation["reason"]);
        }
    }

    /**
     * Ejecuta una transaccion SQL
     *
     * @param array $queries Lista de consultas a ejecutar
     * @param array $variables Lista de parametros de las consultas (array unidimensional)
     * @return bool Si se ha ejecutado todo correctamente
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
     * Devuelve el numero de registros de una tabla
     *
     * @param String $table Es la tabla seleccionada
     * @param String $conditions Son las condiciones para la cuenta, no es obligatorio ponerla
     * @return Int
     */
    public function countFromTable($table, $conditions=1)
    {
        if ($conditions == 1) {
            $stmt = "SELECT COUNT(*) as cuenta FROM " . $table;
        }else {
            $stmt = "SELECT COUNT(*) as cuenta FROM " . $table . " WHERE ". $conditions;
        }


        $result = $this->select_stmt($stmt, [], false);

        return $result[0]["cuenta"];
    }

    /**
     * Devuelve el ultimo id insertado
     *
     * @return Int
     */
    function getLastInsertId()
    {
        return $this->conn->lastInsertId();
    }


    /**
     * Elimina el registro que le indiques
     *
     * @param String $table Es la tabla seleccionada
     * @param String $id Es el id (la primary key) del registro que quieres eliminar
     * @return void
     */
    function delete_stmt($table, $id)
    {
        if (!in_array($table, $this->tables_names)) {
            throw new PDOException("No existe la tabla $table");
        }

        if (!is_numeric($id)) {
            throw new PDOException("El id $id tiene que ser numerico");
        }

        // Funcion para eliminar por id, solo sirve si la tabla tiene primary key
        $sql = "DELETE FROM $table where {$this->tables_PK[$table][0]} = :id";
        $variables = [
            "id" => $id
        ];

        $this->execute_query($sql, $variables);
    }


    /**
     * Realiza una consulta SELECT para recuperar datos de una tabla, incluyendo las columnas que no son claves primarias ni claves externas.
     * Si encuentra una clave externa, realiza un LEFT JOIN con la tabla referenciada y selecciona el primer campo que no sea una clave primaria ni una clave externa.
     *
     * @param string $table_name Nombre de la tabla desde la cual se recuperarán los datos.
     * @param bool $return_json Si quieres que te lo devuelva en json. Por defecto es true
     * @return array Resultado de la consulta SELECT.
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
                // Si es una clave externa, agrega un LEFT JOIN y selecciona el primer campo que no sea PK ni FK
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
     * Busca y devuelve el nombre de la primera columna que no es una clave primaria ni una clave externa en la tabla especificada.
     *
     * @param string $table_name Nombre de la tabla en la que se buscará la primera columna no clave.
     * @return string|null Nombre de la primera columna no clave encontrada o null si no se encuentra ninguna.
     */
    function get_first_non_key_column($table_name) {
        $columns = $this->tables_with_column_details[$table_name];

        foreach ($columns as $key => $column) {
            if ($column['Key'] != 'PRI' && $column['Key'] != 'MUL') {
                return $key;
            }
        }
        return null; // No se encontró ninguna columna que no sea PK ni FK
    }


    /**
     * Obtiene el nombre de la tabla a la que hace referencia una columna de clave externa en una tabla dada.
     *
     * @param string $table_name Nombre de la tabla que contiene la columna de clave externa.
     * @param string $column_name Nombre de la columna de clave externa.
     * @return string|null Nombre de la tabla referenciada por la columna de clave externa o null si no se encuentra ninguna tabla referenciada.
     */
    function get_referenced_table($table_name, $column_name)
    {
        $stmt = $this->conn->prepare("SHOW CREATE TABLE $table_name");
        $stmt->execute();
        $table_info = $stmt->fetch(PDO::FETCH_ASSOC);

        // Obtener la definición de la tabla
        $table_definition = $table_info['Create Table'];

        // Buscar la definición de la columna
        preg_match_all('/CONSTRAINT `[^`]+` FOREIGN KEY \(`' . $column_name . '`\) REFERENCES `([^`]+)` \(`[^`]+`\)/', $table_definition, $matches);

        // Si se encontraron coincidencias, la tabla referenciada es el primer elemento capturado en la expresión regular
        if (isset($matches[1][0])) {
            return $matches[1][0];
        } else {
            // Si no se encuentra ninguna referencia, devuelve null o algún valor que indique que no hay una tabla referenciada
            return null;
        }
    }

    /**
     * Obtiene 1 registro de la tabla seleccionada segun el id pasado
     *
     * @param string $table Nombre de la tabla
     * @param string|int $id Id del registro que se quiere obtener
     * @return array registro resultado
     */
    function select_one($table, $id, $json_encode=false)
    {
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
        parent::setTypes( array(
            'texto' => array('char', 'varchar', 'text', 'tinytext', 'mediumtext', 'longtext'),
            'numero' => array('int', 'float', 'decimal', 'double'),
            'tiempo' => array('date', 'datetime', 'timestamp', 'time')
        ));

        $servername = $ppt["serverName"];
        $username = $ppt["username"];
        $password = $ppt["password"];
        $DB = $ppt["DB"];

        try {
            $conn = new PDO("mysql:host=$servername;dbname=$DB;charset=utf8", $username, $password);
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

        try {
            // Modifica la cadena de conexión para SQL
            $conn = new PDO("sqlsrv:Server=$servername;Database=$DB", $username, $password);
            // Establece el modo de error PDO en excepción
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            parent::setConection($conn);
            parent::__construct($ppt);

        } catch (PDOException $e) {
            throw new Exception("Connection failed: " . $e->getMessage());
        }
    }
}



$properties = [
    "serverName" => "",
    "username" => "",
    "password" => "",
    "DB" => ""
];


$database_object = new mysql($properties);

?>
