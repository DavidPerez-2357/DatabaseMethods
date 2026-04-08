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
     * RIGHT JOIN and FULL OUTER JOIN require SQLite 3.39.0 (2022-07-21) or later.
     * Older versions only support INNER JOIN and LEFT JOIN.
     *
     * @var array
     */
    protected $supportedJoins = array(
        'INNER' => 'INNER JOIN',
        'LEFT'  => 'LEFT JOIN',
        'RIGHT' => 'RIGHT JOIN',
        'FULL'  => 'FULL JOIN',
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
