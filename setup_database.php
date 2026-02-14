<?php
// setup_database.php - One-click setup
require_once 'includes/config.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Setup Hotel Management System</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f7fa; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; margin-bottom: 30px; }
        .log { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; font-family: monospace; font-size: 14px; max-height: 400px; overflow-y: auto; }
        .success { color: #27ae60; }
        .error { color: #e74c3c; }
        .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .btn:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Hotel Management System Setup</h1>
        <div class='log'>";

// Check if database exists and is properly configured
try {
    // Test connection
    $test = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($test->connect_error) {
        throw new Exception("Database connection failed: " . $test->connect_error);
    }
    
    echo "<p class='success'>✓ Database connection successful</p>";
    
    // Check if tables exist
    $tables = ['users', 'rooms', 'room_categories', 'bookings', 'payments', 'settings'];
    $missing_tables = [];
    
    foreach ($tables as $table) {
        $result = $test->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows == 0) {
            $missing_tables[] = $table;
        }
    }
    
    if (empty($missing_tables)) {
        echo "<p class='success'>✓ All database tables exist</p>";
        
        // Check for admin user
        $admin_check = $test->query("SELECT * FROM users WHERE username = 'admin'");
        if ($admin_check->num_rows > 0) {
            echo "<p class='success'>✓ Admin user exists</p>";
            echo "<p><strong>Login credentials:</strong><br>";
            echo "Username: <code>admin</code><br>";
            echo "Password: <code>password</code></p>";
        } else {
            // Create admin user
            $hashed_password = password_hash('password', PASSWORD_BCRYPT);
            $test->query("INSERT INTO users (username, password, email, full_name, role) VALUES 
                ('admin', '$hashed_password', 'admin@hotel.com', 'Administrator', 'admin')");
            echo "<p class='success'>✓ Admin user created</p>";
        }
        
        echo "<p class='success'>✓ Setup completed successfully!</p>";
        echo "<div style='margin-top: 30px;'>";
        echo "<a href='login.php' class='btn'>Go to Login</a>";
        echo "<a href='admin/' class='btn'>Go to Admin Panel</a>";
        echo "<a href='index.php' class='btn'>Go to Homepage</a>";
        echo "</div>";
        
    } else {
        echo "<p class='error'>✗ Missing tables: " . implode(', ', $missing_tables) . "</p>";
        echo "<p>Please run the initial setup by visiting: <a href='includes/config.php'>config.php</a></p>";
    }
    
    $test->close();
    
} catch (Exception $e) {
    echo "<p class='error'>✗ " . $e->getMessage() . "</p>";
    echo "<p>Please check your database configuration in includes/config.php</p>";
}

echo "</div></div></body></html>";
?>