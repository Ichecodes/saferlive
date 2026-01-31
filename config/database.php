<?php
/**
 * Database Configuration
 * Update these values to match your database setup
 */

function getDatabaseConnection() {
    static $db = null;

    if ($db === null) {
        // Allow overriding via environment variables for flexibility on different hosts
        $host = getenv('DB_HOST') ?: 'localhost';
        $dbname = getenv('DB_NAME') ?: 'lyrivers_safer_ng';
        $username = getenv('DB_USER') ?: 'lyrivers_admin';
        $password = getenv('DB_PASS') ?: '987Safer.ng#';

        $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";

        try {
            $db = new PDO(
                $dsn,
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            // Log the real error but return a generic message to the client
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }

    return $db;
}

/**
 * Create incidents table if it doesn't exist
 * Run this once to set up the database schema
 */
function createIncidentsTable() {
    $db = getDatabaseConnection();
    
    $sql = "CREATE TABLE IF NOT EXISTS incidents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(100) NOT NULL,
        category VARCHAR(100),
        state VARCHAR(100),
        lga VARCHAR(100),
        latitude DECIMAL(10, 8),
        longitude DECIMAL(11, 8),
        status ENUM('open', 'closed', 'pending') DEFAULT 'open',
        start_time DATETIME NOT NULL,
        end_time DATETIME NULL,
        closed_at DATETIME NULL,
        victims INT DEFAULT 0,
        casualties INT DEFAULT 0,
        missing INT DEFAULT 0,
        injured INT DEFAULT 0,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_type (type),
        INDEX idx_state (state),
        INDEX idx_lga (lga),
        INDEX idx_start_time (start_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
}

// Uncomment to create table on first run
// createIncidentsTable();

/**
 * Create incident_media table
 * Stores media metadata for incidents (Cloudinary public_id, URL, dimensions)
 */
function createIncidentMediaTable() {
    $db = getDatabaseConnection();
    $sql = "CREATE TABLE IF NOT EXISTS incident_media (
        id INT AUTO_INCREMENT PRIMARY KEY,
        incident_id INT NOT NULL,
        public_id VARCHAR(255) DEFAULT NULL,
        secure_url VARCHAR(512) DEFAULT NULL,
        width INT DEFAULT NULL,
        height INT DEFAULT NULL,
        media_type VARCHAR(50) DEFAULT 'image',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_incident_id (incident_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
}

/**
 * Create job_requests table
 * Matches the current `job_requests` schema used by the admin dashboard and API
 */
function createJobRequestsTable() {
    $db = getDatabaseConnection();
    $sql = "CREATE TABLE IF NOT EXISTS job_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(255) NOT NULL,
        phone VARCHAR(60) NOT NULL,
        email VARCHAR(255) DEFAULT NULL,
        agent_type VARCHAR(100) DEFAULT NULL,
        job_type VARCHAR(100) DEFAULT NULL,
        state VARCHAR(100) DEFAULT NULL,
        lga VARCHAR(100) DEFAULT NULL,
        address TEXT DEFAULT NULL,
        status ENUM('pending','reviewed','assigned','cancelled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_agent_type (agent_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
}

/**
 * Create reporters table
 * Optional table to store people who submit reports separately from incidents
 */
function createReportersTable() {
    $db = getDatabaseConnection();
    $sql = "CREATE TABLE IF NOT EXISTS reporters (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(255) DEFAULT NULL,
        phone VARCHAR(60) DEFAULT NULL,
        email VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
}

/**
 * Create reports table
 * Stores incident/report records submitted via the reporting form.
 * Note: There is already an `incidents` table; `reports` is provided for projects
 * that separate reporter-submitted reports from the canonical incidents table.
 */
function createReportsTable() {
    $db = getDatabaseConnection();
    $sql = "CREATE TABLE IF NOT EXISTS reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reporter_id INT DEFAULT NULL,
        type VARCHAR(100) DEFAULT NULL,
        category VARCHAR(100) DEFAULT NULL,
        state VARCHAR(100) DEFAULT NULL,
        lga VARCHAR(100) DEFAULT NULL,
        latitude DECIMAL(10,8) DEFAULT NULL,
        longitude DECIMAL(11,8) DEFAULT NULL,
        status ENUM('open','closed','pending') DEFAULT 'open',
        start_time DATETIME DEFAULT NULL,
        end_time DATETIME DEFAULT NULL,
        victims INT DEFAULT 0,
        casualties INT DEFAULT 0,
        description TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_reporter_id (reporter_id),
        INDEX idx_state (state),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
}

/**
 * Convenience helper to create all application tables.
 * Call `createAllTables()` manually in a one-off script or bootstrap step.
 */
function createAllTables() {
    createIncidentsTable();
    createIncidentMediaTable();
    createJobRequestsTable();
    createReportersTable();
    createReportsTable();
}

// Uncomment to create all tables on first run (use with caution)
// createAllTables();
?>

