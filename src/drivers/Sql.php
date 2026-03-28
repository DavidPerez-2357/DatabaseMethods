<?php
/**
 * Sql.php
 *
 * SQL Server driver — extends the Database base class and establishes
 * a PDO connection to a Microsoft SQL Server instance via the sqlsrv DSN.
 *
 * @author DavidPerez-2357
 * @link https://github.com/DavidPerez-2357/DatabaseMethods
 */

/**
 * Sql (SQL Server) driver class.
 *
 * @package DatabaseMethods
 */
class Sql extends Database
{
    public function __construct($ppt)
    {
        parent::__construct($ppt);
        $this->connect($ppt);
    }

    protected function connect($ppt)
    {
        $servername = isset($ppt["serverName"]) ? $ppt["serverName"] : (isset($ppt["host"]) ? $ppt["host"] : null);
        $username = isset($ppt["username"]) ? $ppt["username"] : (isset($ppt["user"]) ? $ppt["user"] : '');
        $password = isset($ppt["password"]) ? $ppt["password"] : '';
        $db = isset($ppt["DB"]) ? $ppt["DB"] : (isset($ppt["dbname"]) ? $ppt["dbname"] : null);

        if (empty($servername) || empty($username) || empty($db)) {
            throw new InvalidArgumentException("Server name, username, and database name are required for SQL Server.");
        }

        $dsn = "sqlsrv:Server={$servername};Database={$db}";

        try {
            $conn = new PDO($dsn, $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            parent::setConnection($conn);
        } catch (PDOException $e) {
            throw new Exception("Connection failed: " . $e->getMessage());
        }
    }
}
