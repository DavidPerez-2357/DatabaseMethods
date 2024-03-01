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

## Select statement

* **Normal select**:
    * **query**: The query of the select. Is a string.
    * **variables**: There are the variables of the query, if you dont have, pass an empty array.
    * **json_encode**: Is a boolean, if you mark false or not expecify this method it will return the asociative array of the result.
```php
$result_select = $database_object->select_stmt("SELECT * from people where name = :nameVar and surname = :surnameVar", ["name"=> "David", "surname"=> "Perez"], true);
```
