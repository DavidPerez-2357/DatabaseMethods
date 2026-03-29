<?php
/**
 * Sqlite.php
 *
 * SQLite driver — extends the Database base class and establishes
 * a PDO connection to a SQLite database file.
 *
 * @author DavidPerez-2357
 * @link https://github.com/DavidPerez-2357/DatabaseMethods
 */

/**
 * Sqlite driver class.
 *
 * @package DatabaseMethods
 */
class Sqlite extends Database
{
    public function __construct($ppt)
    {
        parent::__construct($ppt);
        $this->connect($ppt);
    }

    protected function connect($ppt)
    {
        $dbFile = $this->getConfigValue($ppt, ['DB', 'dbname']);

        if (empty($dbFile)) {
            throw new InvalidArgumentException("Database file is required for SQLite.");
        }

        $dsn = "sqlite:{$dbFile}";

        try {
            $conn = new PDO($dsn);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            parent::setConnection($conn);
        } catch (PDOException $e) {
            throw new Exception("Connection failed: " . $e->getMessage());
        }
    }
}
