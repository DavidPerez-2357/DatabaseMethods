<?php
/**
 * Mysql.php
 *
 * MySQL driver — extends the Database base class and establishes
 * a PDO connection to a MySQL / MariaDB server.
 *
 * @author DavidPerez-2357
 * @link https://github.com/DavidPerez-2357/DatabaseMethods
 */

/**
 * Mysql driver class.
 *
 * @package DatabaseMethods
 */
class Mysql extends Database
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
        $codification = isset($ppt["codification"]) ? $ppt["codification"] : 'utf8mb4';

        $dsn = "mysql:host=$servername";
        if (!empty($db)) {
            $dsn .= ";dbname=$db";
        }
        $dsn .= ";charset=$codification";

        try {
            $conn = new PDO($dsn, $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            parent::setConnection($conn);
        } catch (PDOException $e) {
            throw new Exception("Connection failed: " . $e->getMessage());
        }
    }
}
