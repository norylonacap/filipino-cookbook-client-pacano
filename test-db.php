<?php
$configFile = __DIR__ . '/config.php';
$config = file_exists($configFile)
    ? require $configFile
    : require __DIR__ . '/config.example.php';

try {
    $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset={$config['db_charset']}";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
    echo "Database connection successful\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM foods");
    $result = $stmt->fetch();
    echo "Foods table accessible. Total foods: " . $result['count'] . "\n";
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
