# Database Setup Guide for Safer.ng

This guide will walk you through setting up the MySQL database for the Public Incident List system.

## Prerequisites
- XAMPP installed and running
- phpMyAdmin access (usually at http://localhost/phpmyadmin)

## Step 1: Start XAMPP Services

1. Open XAMPP Control Panel
2. Start **Apache** service
3. Start **MySQL** service
4. Ensure both services show green "Running" status

## Step 2: Create the Database

### Option A: Using phpMyAdmin (Recommended)

1. Open your browser and go to: `http://localhost/phpmyadmin`
2. Click on the **"New"** button in the left sidebar
3. Enter database name: `safer_ng`
4. Select collation: `utf8mb4_unicode_ci`
5. Click **"Create"** button

### Option B: Using MySQL Command Line

```sql
CREATE DATABASE safer_ng CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## Step 3: Create the Incidents Table

### Option A: Using phpMyAdmin

1. Select the `safer_ng` database from the left sidebar
2. Click on the **"SQL"** tab at the top
3. Copy and paste the following SQL:

```sql
CREATE TABLE IF NOT EXISTS incidents (
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
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_type (type),
    INDEX idx_state (state),
    INDEX idx_lga (lga),
    INDEX idx_start_time (start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

4. Click **"Go"** button

### Option B: Using PHP Script

1. Open `config/database.php`
2. Uncomment the last line: `// createIncidentsTable();`
3. Change it to: `createIncidentsTable();`
4. Create a temporary file `setup.php` in your root directory:

```php
<?php
require_once 'config/database.php';
createIncidentsTable();
echo "Table created successfully!";
?>
```

5. Visit `http://localhost/safer/setup.php` in your browser
6. You should see "Table created successfully!"
7. Delete `setup.php` for security
8. Re-comment the line in `database.php`

## Step 4: Configure Database Connection

1. Open `config/database.php`
2. Update the database credentials if needed:

```php
$host = 'localhost';        // Usually 'localhost' for XAMPP
$dbname = 'safer_ng';      // Your database name
$username = 'root';         // Default XAMPP username
$password = '';             // Default XAMPP password (empty)
```

**Note:** If you've set a MySQL root password, update the `$password` variable.

## Step 5: Insert Sample Data (Optional)

To test the system, you can insert sample incidents:

### Using phpMyAdmin:

1. Select `safer_ng` database
2. Click on `incidents` table
3. Click **"Insert"** tab
4. Add sample data or use SQL:

```sql
INSERT INTO incidents (type, category, state, lga, latitude, longitude, status, start_time, victims, casualties, description) VALUES
('Kidnapping', 'Violent Crime', 'Kaduna', 'Kaduna North', 10.5167, 7.4333, 'open', '2024-01-15 14:30:00', 5, 0, 'Armed men kidnapped 5 passengers on Kaduna-Abuja highway'),
('Robbery', 'Property Crime', 'Lagos', 'Ikeja', 6.5244, 3.3792, 'closed', '2024-01-14 20:15:00', '2024-01-14 20:45:00', 2, 0, 'Armed robbery at shopping mall'),
('Terror', 'Terrorism', 'Borno', 'Maiduguri', 11.8333, 13.1500, 'open', '2024-01-16 08:00:00', 12, 3, 'Terrorist attack on village'),
('Communal Clash', 'Conflict', 'Plateau', 'Jos North', 9.9167, 8.9000, 'pending', '2024-01-13 16:20:00', 8, 2, 'Communal violence outbreak'),
('Road Attack', 'Violent Crime', 'Katsina', 'Katsina', 12.9886, 7.6000, 'open', '2024-01-15 10:00:00', 3, 1, 'Bandits attack on highway');
```

### Using SQL File:

1. Create a file `sample_data.sql` in your root directory
2. Copy the INSERT statements above
3. In phpMyAdmin:
   - Select `safer_ng` database
   - Click **"Import"** tab
   - Choose file: `sample_data.sql`
   - Click **"Go"**

## Step 6: Verify Setup

1. Open `http://localhost/safer/incidents.html` in your browser
2. You should see:
   - Analytics dashboard with data
   - Incident table populated
   - Charts displaying information
   - Map showing incident locations

## Step 7: Test API Endpoints

Test the API endpoints directly:

1. **Summary Stats:**
   ```
   http://localhost/safer/api/incidents/stats/summary.php
   ```

2. **Incident List:**
   ```
   http://localhost/safer/api/incidents/list.php
   ```

3. **Type Stats:**
   ```
   http://localhost/safer/api/incidents/stats/types.php
   ```

4. **Timeline:**
   ```
   http://localhost/safer/api/incidents/stats/timeline.php
   ```

5. **Victims:**
   ```
   http://localhost/safer/api/incidents/stats/victims.php
   ```

6. **AI Summary:**
   ```
   http://localhost/safer/api/ai/summary/incidents.php
   ```

All should return JSON data.

## Troubleshooting

### Error: "Database connection failed"

**Solutions:**
1. Check if MySQL service is running in XAMPP
2. Verify database credentials in `config/database.php`
3. Ensure database `safer_ng` exists
4. Check if MySQL root password is set (update `$password` if needed)

### Error: "Table 'safer_ng.incidents' doesn't exist"

**Solution:**
- Run Step 3 again to create the table

### Error: "Access denied for user 'root'@'localhost'"

**Solutions:**
1. Check MySQL password in `config/database.php`
2. Reset MySQL root password in XAMPP if needed
3. Or create a new MySQL user with proper permissions

### Charts/Map not showing

**Solutions:**
1. Check browser console for JavaScript errors (F12)
2. Verify API endpoints are accessible
3. Check if sample data exists in database
4. Ensure Chart.js and Leaflet.js are loading (check Network tab)

### API returns empty data

**Solutions:**
1. Verify sample data was inserted correctly
2. Check database connection in `config/database.php`
3. Test API endpoints directly in browser
4. Check PHP error logs in XAMPP

## Database Schema Details

### Incidents Table Structure

| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key, auto-increment |
| type | VARCHAR(100) | Incident type (Kidnapping, Robbery, etc.) |
| category | VARCHAR(100) | Category classification |
| state | VARCHAR(100) | Nigerian state |
| lga | VARCHAR(100) | Local Government Area |
| latitude | DECIMAL(10,8) | GPS latitude |
| longitude | DECIMAL(11,8) | GPS longitude |
| status | ENUM | 'open', 'closed', 'pending' |
| start_time | DATETIME | When incident started |
| end_time | DATETIME | When incident ended (nullable) |
| closed_at | DATETIME | When status changed to closed |
| victims | INT | Number of victims |
| casualties | INT | Number of casualties |
| description | TEXT | Incident description |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Last update time |

### Indexes

The table includes indexes on:
- `status` - For filtering by status
- `type` - For filtering by type
- `state` - For filtering by state
- `lga` - For filtering by LGA
- `start_time` - For sorting and date filtering

## Security Notes

1. **Never commit `config/database.php` with real credentials to public repositories**
2. **Use environment variables for production**
3. **Set proper MySQL user permissions (not root)**
4. **Enable prepared statements (already implemented)**
5. **Sanitize all user inputs (already implemented)**

## Next Steps

After setup:
1. ✅ Database is created and configured
2. ✅ Table structure is in place
3. ✅ Sample data is inserted (optional)
4. ✅ API endpoints are tested
5. ✅ Frontend is displaying data

You're ready to start using the Public Incident List system!

