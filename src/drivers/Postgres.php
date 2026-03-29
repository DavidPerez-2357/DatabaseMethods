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
        $servername   = $this->getConfigValue($ppt, ['serverName', 'host']);
        $username     = $this->getConfigValue($ppt, ['username', 'user'], '');
        $password     = $this->getConfigValue($ppt, ['password'], '');
        $db           = $this->getConfigValue($ppt, ['DB', 'dbname']);
        $codification = $this->getConfigValue($ppt, ['codification'], 'utf8');

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
