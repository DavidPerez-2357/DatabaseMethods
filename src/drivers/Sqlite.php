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
    /**
     * SQLite added RIGHT JOIN and FULL OUTER JOIN support in version 3.39.0 (2022-07-21).
     * This driver conservatively declares only INNER JOIN and LEFT JOIN as supported
     * to ensure compatibility with SQLite versions prior to 3.39.0. If your environment
     * is guaranteed to run SQLite 3.39.0 or later, you may extend this list.
     *
     * @var array
     */
    protected $supportedJoins = array(
        'INNER' => 'INNER JOIN',
        'LEFT'  => 'LEFT JOIN',
    );
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
