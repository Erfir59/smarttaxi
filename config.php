<?php
session_start();

// 📊 Base de données
$host = $_ENV['DB_HOST'] ?? 'db';
$dbname = $_ENV['DB_NAME'] ?? 'smarttaxi';
$users = $_ENV['DB_USERS'] ?? 'root';
$pass = $_ENV['DB_PASSWORD'] ?? 'password123';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $users, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch(PDOException $e) {
    die("❌ Erreur DB: " . $e->getMessage());
}
?>
