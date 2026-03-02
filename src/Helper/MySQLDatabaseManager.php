<?php

namespace DorsetDigital\Caddy\Helper;

use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;

/**
 * MySQL Database Manager Helper
 * Manages creation, deletion, and configuration of MySQL databases and users
 * Designed for MariaDB instances on AWS RDS
 */
class MySQLDatabaseManager
{
    use Injectable;

    /**
     * @var string
     */
    private $host;

    /**
     * @var string
     */
    private $adminUsername;

    /**
     * @var string
     */
    private $adminPassword;

    /**
     * @var int
     */
    private $port = 3306;

    /**
     * @var PDO|null
     */
    private $connection = null;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = Injector::inst()->get(LoggerInterface::class);
    }

    /**
     * Set the database instance connection details
     *
     * @param string $host Database host URL
     * @param string $username Admin username
     * @param string $password Admin password
     * @param int $port Database port (default: 3306)
     * @return $this
     */
    public function setConnectionDetails($host, $username, $password, $port = 3306)
    {
        $this->host = $host;
        $this->adminUsername = $username;
        $this->adminPassword = $password;
        $this->port = $port;

        // Close existing connection if any
        $this->disconnect();

        return $this;
    }

    /**
     * Disconnect from MySQL server
     */
    public function disconnect()
    {
        $this->connection = null;
    }

    /**
     * Reset a user's password
     *
     * @param string $username
     * @param string $newPassword
     * @param string $host Host pattern (default: '%')
     * @return array ['success' => bool, 'message' => string]
     */
    public function resetUserPassword($username, $newPassword, $host = '%')
    {
        if (!$this->connect()) {
            return ['success' => false, 'message' => 'Failed to connect to database server'];
        }

        try {
            $sql = sprintf(
                "ALTER USER '%s'@'%s' IDENTIFIED BY '%s'",
                $username,
                $host,
                $newPassword
            );

            $this->connection->exec($sql);
            $this->connection->exec('FLUSH PRIVILEGES');

            $this->logger->info('User password reset successfully', [
                'username' => $username,
                'host' => $host
            ]);

            return [
                'success' => true,
                'message' => 'Password reset successfully'
            ];
        } catch (PDOException $e) {
            $this->logger->error('Failed to reset user password', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to reset password: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Establish connection to MySQL server
     *
     * @return bool
     */
    private function connect()
    {
        if ($this->connection !== null) {
            return true;
        }

        try {
            $dsn = sprintf('mysql:host=%s;port=%d', $this->host, $this->port);
            $this->connection = new PDO(
                $dsn,
                $this->adminUsername,
                $this->adminPassword,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            $this->logger->info('Successfully connected to MySQL server', ['host' => $this->host]);
            return true;
        } catch (PDOException $e) {
            $this->logger->error('Failed to connect to MySQL server', [
                'host' => $this->host,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Create a complete database setup: database, user, and grant privileges
     * This is a convenience method that combines multiple operations
     *
     * @param string|null $prefix Optional prefix for database and username
     * @param string $host Host pattern for user (default: '%')
     * @return array ['success' => bool, 'message' => string, 'credentials' => array|null]
     */
    public function createDatabaseWithUser($prefix = null, $host = '%')
    {
        // Generate names and password
        $databaseName = $this->generateDatabaseName($prefix);
        $username = $this->generateUsername($prefix);
        $password = $this->generatePassword();

        $this->logger->info('Starting complete database setup', [
            'database' => $databaseName,
            'username' => $username
        ]);

        // Create database
        $dbResult = $this->createDatabase($databaseName);
        if (!$dbResult['success']) {
            return [
                'success' => false,
                'message' => 'Failed to create database: ' . $dbResult['message'],
                'credentials' => null
            ];
        }

        // Create user
        $userResult = $this->createUser($username, $password, $host);
        if (!$userResult['success']) {
            // Rollback: delete the database
            $this->deleteDatabase($databaseName);
            return [
                'success' => false,
                'message' => 'Failed to create user: ' . $userResult['message'],
                'credentials' => null
            ];
        }

        // Grant privileges
        $grantResult = $this->grantPrivileges($username, $databaseName, $host);
        if (!$grantResult['success']) {
            // Rollback: delete user and database
            $this->deleteUser($username, $host);
            $this->deleteDatabase($databaseName);
            return [
                'success' => false,
                'message' => 'Failed to grant privileges: ' . $grantResult['message'],
                'credentials' => null
            ];
        }

        $credentials = [
            'host' => $this->host,
            'port' => $this->port,
            'database' => $databaseName,
            'username' => $username,
            'password' => $password
        ];

        $this->logger->info('Database setup completed successfully', [
            'database' => $databaseName,
            'username' => $username
        ]);

        return [
            'success' => true,
            'message' => 'Database and user created successfully',
            'credentials' => $credentials
        ];
    }

    /**
     * Generate a unique database name
     *
     * @param string|null $prefix Optional prefix
     * @return string
     */
    private function generateDatabaseName($prefix = null)
    {
        $suffix = 'db_' . bin2hex(random_bytes(6));
        return $prefix ? $prefix . '_' . $suffix : $suffix;
    }

    /**
     * Generate a unique username
     *
     * @param string|null $prefix Optional prefix
     * @return string
     */
    private function generateUsername($prefix = null)
    {
        $suffix = 'usr_' . bin2hex(random_bytes(5));
        $username = $prefix ? $prefix . '_' . $suffix : $suffix;

        // MySQL usernames are limited to 32 characters
        return substr($username, 0, 32);
    }

    /**
     * Generate a random secure password
     *
     * @param int $length Password length
     * @return string
     */
    private function generatePassword($length = 24)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        $maxIndex = strlen($chars) - 1;

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $maxIndex)];
        }

        return $password;
    }

    /**
     * Create a new database
     *
     * @param string $databaseName
     * @return array ['success' => bool, 'message' => string]
     */
    public function createDatabase($databaseName)
    {
        if (!$this->connect()) {
            return ['success' => false, 'message' => 'Failed to connect to database server'];
        }

        try {
            // Sanitize database name (only allow alphanumeric and underscores)
            $safeName = preg_replace('/[^a-zA-Z0-9_]/', '', $databaseName);

            $sql = sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $safeName);
            $this->connection->exec($sql);

            $this->logger->info('Database created successfully', ['database' => $safeName]);

            return [
                'success' => true,
                'message' => 'Database created successfully',
                'database' => $safeName
            ];
        } catch (PDOException $e) {
            $this->logger->error('Failed to create database', [
                'database' => $databaseName,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create database: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create a new MySQL user
     *
     * @param string $username
     * @param string $password
     * @param string $host Host pattern (default: '%' for all hosts)
     * @return array ['success' => bool, 'message' => string]
     */
    public function createUser($username, $password, $host = '%')
    {
        if (!$this->connect()) {
            return ['success' => false, 'message' => 'Failed to connect to database server'];
        }

        try {
            $sql = sprintf(
                "CREATE USER '%s'@'%s' IDENTIFIED BY '%s'",
                $this->connection->quote($username, PDO::PARAM_STR),
                $this->connection->quote($host, PDO::PARAM_STR),
                $this->connection->quote($password, PDO::PARAM_STR)
            );

            // Remove quotes added by PDO::quote for the SQL statement structure
            $sql = str_replace("''", "'", $sql);

            $this->connection->exec($sql);

            $this->logger->info('User created successfully', [
                'username' => $username,
                'host' => $host
            ]);

            return [
                'success' => true,
                'message' => 'User created successfully',
                'username' => $username
            ];
        } catch (PDOException $e) {
            $this->logger->error('Failed to create user', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create user: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete a database
     *
     * @param string $databaseName
     * @return array ['success' => bool, 'message' => string]
     */
    public function deleteDatabase($databaseName)
    {
        if (!$this->connect()) {
            return ['success' => false, 'message' => 'Failed to connect to database server'];
        }

        try {
            $safeName = preg_replace('/[^a-zA-Z0-9_]/', '', $databaseName);
            $sql = sprintf('DROP DATABASE IF EXISTS `%s`', $safeName);
            $this->connection->exec($sql);

            $this->logger->info('Database deleted successfully', ['database' => $safeName]);

            return [
                'success' => true,
                'message' => 'Database deleted successfully'
            ];
        } catch (PDOException $e) {
            $this->logger->error('Failed to delete database', [
                'database' => $databaseName,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to delete database: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Grant privileges to a user for a specific database
     *
     * @param string $username
     * @param string $databaseName
     * @param string $host Host pattern (default: '%')
     * @return array ['success' => bool, 'message' => string]
     */
    public function grantPrivileges($username, $databaseName, $host = '%')
    {
        if (!$this->connect()) {
            return ['success' => false, 'message' => 'Failed to connect to database server'];
        }

        try {
            // Grant comprehensive privileges for full database management
            $privileges = [
                'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'DROP',
                'INDEX', 'ALTER', 'CREATE TEMPORARY TABLES', 'LOCK TABLES',
                'EXECUTE', 'CREATE VIEW', 'SHOW VIEW', 'CREATE ROUTINE',
                'ALTER ROUTINE', 'TRIGGER', 'REFERENCES'
            ];

            $sql = sprintf(
                "GRANT %s ON `%s`.* TO '%s'@'%s'",
                implode(', ', $privileges),
                $databaseName,
                $username,
                $host
            );

            $this->connection->exec($sql);
            $this->connection->exec('FLUSH PRIVILEGES');

            $this->logger->info('Privileges granted successfully', [
                'username' => $username,
                'database' => $databaseName,
                'host' => $host
            ]);

            return [
                'success' => true,
                'message' => 'Privileges granted successfully'
            ];
        } catch (PDOException $e) {
            $this->logger->error('Failed to grant privileges', [
                'username' => $username,
                'database' => $databaseName,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to grant privileges: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete a MySQL user
     *
     * @param string $username
     * @param string $host Host pattern (default: '%')
     * @return array ['success' => bool, 'message' => string]
     */
    public function deleteUser($username, $host = '%')
    {
        if (!$this->connect()) {
            return ['success' => false, 'message' => 'Failed to connect to database server'];
        }

        try {
            $sql = sprintf("DROP USER IF EXISTS '%s'@'%s'", $username, $host);
            $this->connection->exec($sql);
            $this->connection->exec('FLUSH PRIVILEGES');

            $this->logger->info('User deleted successfully', [
                'username' => $username,
                'host' => $host
            ]);

            return [
                'success' => true,
                'message' => 'User deleted successfully'
            ];
        } catch (PDOException $e) {
            $this->logger->error('Failed to delete user', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to delete user: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check if a database exists
     *
     * @param string $databaseName
     * @return bool
     */
    public function databaseExists($databaseName)
    {
        if (!$this->connect()) {
            return false;
        }

        try {
            $stmt = $this->connection->prepare(
                "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?"
            );
            $stmt->execute([$databaseName]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            $this->logger->error('Failed to check database existence', [
                'database' => $databaseName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if a user exists
     *
     * @param string $username
     * @param string $host
     * @return bool
     */
    public function userExists($username, $host = '%')
    {
        if (!$this->connect()) {
            return false;
        }

        try {
            $stmt = $this->connection->prepare(
                "SELECT User FROM mysql.user WHERE User = ? AND Host = ?"
            );
            $stmt->execute([$username, $host]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            $this->logger->error('Failed to check user existence', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}