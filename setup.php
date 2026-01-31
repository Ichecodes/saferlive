<?php
/**
 * Database Setup Script
 * Run this once to create the database table
 * 
 * Instructions:
 * 1. Ensure XAMPP MySQL is running
 * 2. Create database 'safer_ng' in phpMyAdmin first
 * 3. Visit: http://localhost/safer/setup.php
 * 4. Delete this file after successful setup
 */

require_once 'config/database.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Safer.ng</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #00120a;
            color: #ffffff;
        }
        .container {
            background: rgba(255, 255, 255, 0.05);
            padding: 30px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        h1 { color: #069c56; }
        .success { color: #069c56; padding: 10px; background: rgba(6, 156, 86, 0.1); border-radius: 6px; margin: 10px 0; }
        .error { color: #d3212c; padding: 10px; background: rgba(211, 33, 44, 0.1); border-radius: 6px; margin: 10px 0; }
        .info { color: #9bb5a7; padding: 10px; background: rgba(155, 181, 167, 0.1); border-radius: 6px; margin: 10px 0; }
        code { background: rgba(0, 0, 0, 0.3); padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Database Setup</h1>
        
        <?php
        try {
            // Test database connection
            $db = getDatabaseConnection();
            echo '<div class="success">✅ Database connection successful!</div>';
            
            // Create table
            createIncidentsTable();
            echo '<div class="success">✅ Incidents table created successfully!</div>';
            
            // Check if table exists
            $stmt = $db->query("SHOW TABLES LIKE 'incidents'");
            if ($stmt->rowCount() > 0) {
                echo '<div class="success">✅ Table verification: incidents table exists</div>';
                
                // Check table structure
                $stmt = $db->query("DESCRIBE incidents");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo '<div class="info">📊 Table has ' . count($columns) . ' columns</div>';
                
                // Check for existing data
                $count = $db->query("SELECT COUNT(*) FROM incidents")->fetchColumn();
                if ($count > 0) {
                    echo '<div class="info">📝 Table contains ' . $count . ' incident records</div>';
                } else {
                    echo '<div class="info">💡 Table is empty. You can import sample data from <code>sample_data.sql</code></div>';
                }
            }
            
            echo '<div class="success"><strong>Setup Complete!</strong></div>';
            echo '<div class="info">⚠️ <strong>Security:</strong> Delete this file (<code>setup.php</code>) after setup is complete.</div>';
            echo '<div class="info">📖 Next steps: Import sample data from <code>sample_data.sql</code> in phpMyAdmin</div>';
            
        } catch (Exception $e) {
            echo '<div class="error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            echo '<div class="info">💡 Troubleshooting:</div>';
            echo '<ul>';
            echo '<li>Ensure MySQL service is running in XAMPP</li>';
            echo '<li>Create database <code>safer_ng</code> in phpMyAdmin first</li>';
            echo '<li>Check database credentials in <code>config/database.php</code></li>';
            echo '</ul>';
        }
        ?>
    </div>
</body>
</html>

