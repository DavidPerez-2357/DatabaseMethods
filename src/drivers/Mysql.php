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
    /**
     * MySQL and MariaDB do not support FULL (OUTER) JOIN.
     * Only INNER JOIN, LEFT JOIN, and RIGHT JOIN are available.
     *
     * @var array
     */
    protected $supportedJoins = array(
        'INNER' => 'INNER JOIN',
        'LEFT'  => 'LEFT JOIN',
        'RIGHT' => 'RIGHT JOIN',
    );
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
        $codification = $this->getConfigValue($ppt, ['codification'], 'utf8mb4');

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
