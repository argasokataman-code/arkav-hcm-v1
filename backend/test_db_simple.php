<?php
$host = '127.0.0.1';
$db = 'db_arkav_hcm';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "Koneksi database berhasil!\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM hcm_payroll_periods");
    $result = $stmt->fetch();
    echo "Jumlah record di hcm_payroll_periods: " . $result['count'] . "\n";
} catch (\PDOException $e) {
    echo "Koneksi database gagal: " . $e->getMessage() . "\n";
}
?>