<?php
/**
 * Postgres.php
 *
 * PostgreSQL driver — extends the Database base class and establishes
 * a PDO connection to a PostgreSQL server.
 *
 * @author DavidPerez-2357
 * @link https://github.com/DavidPerez-2357/DatabaseMethods
 */

/**
 * Postgres driver class.
 *
 * @package DatabaseMethods
 */
class Postgres extends Database
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
        $codification = isset($ppt["codification"]) ? $ppt["codification"] : 'utf8';

        $dsn = "pgsql:host=$servername";
        if (!empty($db)) {
            $dsn .= ";dbname=$db";
        }
        if (!empty($codification)) {
            $dsn .= ";options='--client_encoding=$codification'";
        }

        try {
            $conn = new PDO($dsn, $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            parent::setConnection($conn);
        } catch (PDOException $e) {
            throw new Exception("Connection failed: " . $e->getMessage());
        }
    }
}
