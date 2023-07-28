<?php
error_reporting(E_ALL & ~E_WARNING);
error_reporting(E_ALL & ~E_NOTICE);

// Define the command to find all wp-config.php files
$command = "find /var/www/vhosts -name 'wp-config.php'";

// Execute the command and capture the output
$output = shell_exec($command);

// Explode the output into an array of paths
$paths = explode("\n", trim($output));

// Remove empty elements from the array
$paths = array_filter($paths);

echo "\033[0;31mChanging passwords for:\n";

// Loop through each WordPress site directory
foreach ($paths as $site_directory) {
    // Extract the database details from the WordPress configuration file
    $configContent = file_get_contents($site_directory);

    preg_match("/define\(\s*'DB_NAME',\s*'([^']+)'/", $configContent, $db_name_match);
    $db_name = $db_name_match[1];

    preg_match("/define\(\s*'DB_USER',\s*'([^']+)'/", $configContent, $db_user_match);
    $db_user = $db_user_match[1];

    preg_match("/define\(\s*'DB_PASSWORD',\s*'([^']+)'/", $configContent, $db_password_match);
    $db_password = $db_password_match[1];

    preg_match("/\\\$table_prefix\s+=\s+'([^']+)'/", $configContent, $table_prefix_match);
    $table_prefix = $table_prefix_match[1];

    $password_suffix = $table_prefix_match[1];

    // Define the new password
    $password_prefix = base64_encode(random_bytes(12));
    $new_password = $password_prefix . $password_suffix;

    // Update the WordPress site's password in the database
    $mysqli = @new mysqli('localhost', $db_user, $db_password, $db_name);

    if ($mysqli->connect_errno) {
        echo "\033[0;31mFailed to connect to MySQL: " . $mysqli->connect_error . "\n";
        echo "\033[0;31mSkipping to the next site...\n";
        continue;
    }

    // Set a timeout for the MySQL connection attempt
    $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5); // 5 seconds timeout

    // change password
    $update_query = "UPDATE `{$table_prefix}users` SET `user_pass` = MD5('{$new_password}') WHERE `ID` IN (SELECT `user_id` FROM `{$table_prefix}usermeta` WHERE `meta_key` = '{$table_prefix}capabilities' AND `meta_value` LIKE '%administrator%')";
    $mysqli->query($update_query);

    // get site url
    $site_result = $mysqli->query("SELECT option_value FROM `{$table_prefix}options` WHERE option_name = 'siteurl'");
    $site_row = $site_result->fetch_assoc();
    $site = $site_row['option_value'];
    $site_result->close();

    // close connection
    $mysqli->close();


    echo "\033[0;33m" . str_replace("option_value", "", $site) . "\n";
}

$totalSites = count($paths);
echo "\n\033[0;32mTotal WordPress sites found: {$totalSites}\n\n";

echo "\n\033[0;32mAll passwords updated successfully!\033[0m\n";
