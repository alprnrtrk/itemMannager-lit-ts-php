<?php
// public/app/Database/Database.php

namespace App\Database;

use PDO;
use PDOException;
use App\Config\Config; // Import the Config class to get paths

class Database
{
    // Static property to hold the single PDO connection instance (Singleton pattern)
    private static ?PDO $pdo = null;

    /**
     * Get the PDO database connection instance.
     * If the connection does not exist, it creates it and also ensures the 'items' table is created.
     * @param string $doc_root The document root (e.g., __DIR__ from public/index.php)
     * @return PDO The PDO database connection object.
     */
    public static function getConnection(string $doc_root): PDO
    {
        // If PDO instance is not already created, create it
        if (self::$pdo === null) {
            // Get the absolute path to the database file from Config
            $db_path = Config::getDbFilePath($doc_root);

            // Ensure the directory for the database file exists
            $data_dir = dirname($db_path);
            if (!is_dir($data_dir)) {
                mkdir($data_dir, 0777, true); // Create directory with full permissions if it doesn't exist
            }

            try {
                // Create a new PDO connection to the SQLite database file
                self::$pdo = new PDO("sqlite:" . $db_path);
                // Set PDO error mode to throw exceptions, which is best for debugging and error handling
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                // Set default fetch mode to return results as associative arrays
                self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

                // Execute SQL to create the 'items' table if it doesn't already exist
                self::$pdo->exec("CREATE TABLE IF NOT EXISTS items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    description TEXT,
                    price REAL NOT NULL,
                    imageUrl TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
            } catch (PDOException $e) {
                // If database connection or table creation fails, log the error
                error_log("Database connection failed: " . $e->getMessage());
                // Send a 500 Internal Server Error response to the client
                http_response_code(500);
                echo json_encode(['error' => 'Server error: Database connection failed. Please check server logs.']);
                exit; // Terminate script execution
            }
        }
        return self::$pdo; // Return the established PDO connection
    }
}