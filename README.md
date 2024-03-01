# database_methods
File with useful methods to work with SQL/MySQL database made in PHP. **Inside the file is explained every method in spanish**.

## Creating a database object
The classes **sql** and **mysql** extends of the parent class **database** who own the methods that the two child classes have in commun.

To create a SQL/MySQL object is needed to specify some properties:
* **serverName**: The name or IP address of the server.
* **userName**: The userName that is going to be used for the operations.
* **password**: The password of the username.
* **DB**: The database that you are going to use.

```php
$properties = [
    "serverName" => "localhost",
    "username" => "root",
    "password" => "",
    "DB" => "your_database"
];

$mysql_object = new mysql($properties);
$sql_object = new sql($properties);
```
## Executing a query
If you dont want the especific methods that are below, you can execute a query with this method, which has this parameters:
* **query**: Is a string.
* **variables**: here are the variables of the query, this field is not requiered.

```php
$result_query = $database_object->execute_query("SELECT * from people where name = :nameVar and surname = :surnameVar", ["nameVar"=> "David", "surnameVar"=> "Perez"]);
```

## Select statement

* **Normal select**:
    * **query**: The query of the select. Is a string.
    * **variables**: There are the variables of the query, this field is not requiered.
    * **json_encode**: Is a boolean, if you mark false, this method it will return the asociative array of the result. If you dont specify this variable, it will be true.
```php
$result_select = $database_object->select_stmt("SELECT * from people where name = :nameVar and surname = :surnameVar", ["nameVar"=> "David", "surnameVar"=> "Perez"], false);
```
* **Select one**: Return all the fields of the record you select by its Id and table.
    * **table**: The selected table.
    * **Id**: The Id of the record.
    * **json_encode**: Is a boolean, if you mark false, this method it will return the asociative array of the result. If you dont specify this variable, it will be true.
```php
$result_select = $database_object->select_one("people", 37, false);
```
* **Simple select**: Select all the fields and if there are any foreign keys it will be replaced with the first field of the referenced table which is not a key. (PK or FK)
    * **table**: The selected table.
    * **json_encode**: Is a boolean, if you mark false, this method it will return the asociative array of the result.  If you dont specify this variable, it will be true.
```php
$result_select = $database_object->simple_select("people", false);
```




