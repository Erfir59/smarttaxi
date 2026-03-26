<?php
/**
 * db.php - Création et configuration base SmartTaxi
 */

$host   = $_ENV['DB_HOST']     ?? 'db';
$dbname = $_ENV['DB_NAME']     ?? 'smarttaxi';
$users   = $_ENV['DB_USERS']     ?? 'root';
$pass   = $_ENV['DB_PASSWORD'] ?? 'password123';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $users, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $users, $pass);

    $sqlFile = __DIR__ . '/init.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("Fichier SQL introuvable : $sqlFile");
    }
    $pdo->exec(file_get_contents($sqlFile));

    // ✅ Comptes de démo — mot de passe en clair
    $pdo->exec("INSERT IGNORE INTO `users` (email, password, nom, prenom, role, adresse) VALUES 
        ('admin@taxi.fr',  'admin123',  'Admin',  'Smart', 'admin',    ''),
        ('client@test.fr', 'client123', 'Dupont', 'Jean',  'client',   '1 rue de la Paix'),
        ('chauffeur@test.fr', 'chauffeur123', 'Martin', 'Pierre', 'chauffeur', '')
    ");

    echo "✅ Base SmartTaxi créée !<br>";
    echo "👤 Comptes de démo :<br>";
    echo "- Admin    : admin@taxi.fr / admin123<br>";
    echo "- Client   : client@test.fr / client123<br>";
    echo "- Chauffeur: chauffeur@test.fr / chauffeur123<br>";
    echo "<a href='login.php'>→ Connexion</a>";

} catch (PDOException $e) {
    die("❌ Erreur DB: " . $e->getMessage());
}
?>