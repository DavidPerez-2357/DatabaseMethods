# Database methods
File with useful methods to work with SQL/MySQL database made in PHP.

## Creating a database object
The classes **sql** and **mysql** extends of the parent class **database** who own the methods that the two child classes have in commun.

To create a SQL/MySQL object is needed to specify some properties:
* **serverName**: The name or IP address of the server.
* **userName**: The userName that is going to be used for the operations.
* **password**: The password of the username.
* **DB**: The database that you are going to use.
* **codification**: The codification you want to use.

```php
$properties = [
    "serverName" => "localhost",
    "username" => "root",
    "password" => "",
    "DB" => "your_database",
    "codification" => "utf8"
];

$mysql_object = new mysql($properties);
$sql_object = new sql($properties);
```
## Executing a query
If you dont want the especific methods that are below, you can execute a query with this method, which has this parameters:
* **query**: Is a string.
* **variables**: There are the variables of the query in a asociative array, this field is not requiered. **In the query a variable need to go behind ":"**.

```php
$result_query = $database_object->execute_query("SELECT * from people where name = :nameVar and surname = :surnameVar", ["nameVar"=> "David", "surnameVar"=> "Perez"]);
```

*If you put @lastInsertId in the value of a variable it will be replaced with the last insert ID at the moment of the execution of the query*.

## Select statement

* **Normal select**:
    * **query**: The query of the select. Is a string.
    * **variables**: There are the variables of the query in a asociative array, this field is not requiered.
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
* **Select all**: Return all the records of a table, conditions can be added.
    * **table**: The selected table.
    * **where**: The conditions of the query, this field is not required. Is a string.
    * **variables**: There are the variables of the *where* variable in a asociative array, this field is not requiered.
    * **json_encode**: Is a boolean, if you mark false, this method it will return the asociative array of the result. If you dont specify this variable, it will be true.
```php
$result_select = $database_object->select_all("people", "name = :nameVar", ["nameVar"=> "David"], false)
```
* **Simple select**: Select all the fields and if there are any foreign keys it will be replaced with the first field of the referenced table which is not a key. (PK or FK)
    * **table**: The selected table.
    * **json_encode**: Is a boolean, if you mark false, this method it will return the asociative array of the result.  If you dont specify this variable, it will be true.
```php
$result_select = $database_object->simple_select("people", false);
```

## Insert statement
This method will insert all the columns of the table except the primary key. **The values will be validated before executing the query**. It has these parameters:
* **table**: The selected table which is going to be inserted the records.
* **variables**: The values which are going to be inserted in the table, it need to be order on the same order of the table.

```php
// The order of the variables is important.
$database_object->insert_stmt("people", ["David", "Perez", "3284873T", "Spain", "Man", "2005-4-24"]);
```

## Update statement
This method will update all the columns of the table except the primary key. **The values will be validated before executing the query**. It has these parameters:
* **table**: The selected table which the records are going to be updated.
* **variables**: The values which are going to be updated, it need to be order on the same order of the table.
* **ID**: The ID of the record you want to update.

*If you put @lastInsertId in the value of a variable it will be replaced with the last insert ID at the moment of the execution of the query*.

```php
// The order of the variables is important.
$database_object->update_stmt("people", ["David", "Perez", "3284873T", "Spain", "Man", "2005-4-24"], 37);
```

## Delete statement
This method will delete the record that you specify.
* **table**: The table which is going to be deleted the record.
* **ID**: The ID of the record that you want to delete.
```php
$database_object->delete_stmt("people", 37);
```


## Transactions
Transactions are a chain of queries that are going to be executed. If one of them fails in the process, all those executed before return to the original state as if they had never been executed. It has these parameters:
* **queries**: Is an array of queries.
* **variables**: Is an array of variables of the queries.

*If you put @lastInsertId in the value of a variable it will be replaced with the last insert ID at the moment of the execution of the query*.

```php
$queries = [
    "INSERT INTO people (name, surname) values (:name, :surname)",
    "UPDATE people set name = :name where id = :id_person",
    "INSERT INTO clothe_person (id_clothe, id_person) values (:id_clothe, :id_person)"
];

$variables = [
    "name" => "David",
    "surname" => "Perez",
    "id_clothe" => 2,
    "id_person" => "@lastInsertID"
];

if ($database_object->executeTransaction($queries, $variables)) {
    echo "The transaction executed successfully";
}else {
    echo "The transaction failed";
}
```

## Other methods

* **Generate pagination**: It will generate the limit part with this parameters:
    * **pageNumber**: The number of the page you want to obtain.
    * **stepNumber**: The number of records which are in one page.
```php
// This will return "limit 50 offset 0" because is the first page it will return the first 50 records.
$limit = $database_object->generatePagination(1, 50);
```

* **Count from table**: It will count the records of a table, you can specify conditions. It has these parameters:
    * **table**: Is the table which is going to be counted the records.
    * **conditions**: Is a atring with the conditions, its not obligatory. **You dont have to write the "where"**.
```php
$numberOfRecords = $database_object->countFromTable("people", "name like '%Da%' and surname = 'Perez'");
```

* **Get last insert ID**: It will return the last insert ID.
```php
$lastID = $database_object->getLastInsertId();
```




