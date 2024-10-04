<?php

require_once __DIR__ . '/../vendor/autoload.php';

class Auth
{
    private $db;
    private $DB_PATH;

    public function __construct($DB_PATH)
    {
        $this->DB_PATH = $DB_PATH;
        try {
            if (!file_exists($this->DB_PATH)) {
                chmod($this->DB_PATH, 0666);
            }
            $this->db = new SQLite3($this->DB_PATH, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
            $this->db->enableExceptions(true);
        } catch (Exception $e) {
            if ($_ENV['DEBUG'] === 'true') {
                error_log("src\Database connection failed: " . $e->getMessage());
                error_log("src\Database path: " . $this->DB_PATH);
                error_log("File permissions: " . substr(sprintf('%o', fileperms($this->DB_PATH)), -4));
                error_log("Current user: " . exec('whoami'));
                die("src\Database connection failed: " . $e->getMessage());
            } else {
                die("src\Database connection failed. Please check the error log.");
            }
        }
    }

    public function login($username, $password)
    {
        // Input validation
        if (empty($username) || empty($password)) {
            error_log("Invalid username or password");
            return false;
        }

        try {
            // Connect to the database
            $db = new SQLite3($this->DB_PATH);

            // Prepare the SQL statement
            $stmt = $db->prepare('SELECT * FROM users WHERE username = :username AND password = :password');

            if (!$stmt) {
                throw new Exception($this->db->lastErrorMsg());
            }

            // Bind the values to the placeholders
            $stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $stmt->bindValue(':password', hash('sha256', $password), SQLITE3_TEXT);

            // Execute the statement
            $result = $stmt->execute();

            // Fetch the result
            $user = $result->fetchArray(SQLITE3_ASSOC);

            // Close the database connection
            $db->close();

            // Return true if a user was found, otherwise false
            return $user !== false;
        } catch (Exception $e) {
            // Log the error message
            error_log("Error in login function: " . $e->getMessage());

            // Return false in case of an exception
            return false;
        }
    }
}