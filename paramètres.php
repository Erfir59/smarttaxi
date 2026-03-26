<?php
// parametres.php - PARAMÈTRES SYSTÈME ADMIN
require_once 'config.php';
require_once 'auth.php';

requireLogin();
requireRole(['admin', 'superadmin']);

// RÉCUPÉRER PARAMÈTRES ACTUELS
try {
    // Table "parametres" (crée si n'existe pas)
    $stmt = $pdo->prepare("
        CREATE TABLE IF NOT EXISTS parametres (
            id INT PRIMARY KEY AUTO_INCREMENT,
            nom VARCHAR(50) UNIQUE NOT NULL,
            valeur TEXT NOT NULL,
            description VARCHAR(255),
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    $stmt->execute();

    // Paramètres par défaut
    $defaults = [
        'commission_admin' => '0.20',      // 20%
        'prix_km' => '2.50',
        'prix_temps_min' => '0.50',
        'frais_minimal' => '5.00',
        'email_support' => 'contact@a4taxi.fr',
        'telephone_support' => '03.21.16.16.07',
        'notifications_sms' => '1',
        'notifications_email' => '1',
        'maintenance_mode' => '0'
    ];

    foreach ($defaults as $nom => $valeur) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO parametres (nom, valeur, description) VALUES (?, ?, ?)");
        $stmt->execute([$nom, $valeur, "Paramètre système: $nom"]);
    }

    // Charger tous les paramètres
    $stmt = $pdo->query("SELECT * FROM parametres ORDER BY nom");
    $parametres = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // SAUVEGARDER MODIFICATIONS
    $msg = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach ($_POST['parametres'] ?? [] as $nom => $valeur) {
            $stmt = $pdo->prepare("INSERT INTO parametres (nom, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = ?");
            $stmt->execute([$nom, $valeur, $valeur]);
        }
        $msg = "✅ Paramètres sauvegardés !";
        header('Location: parametres.php?msg=' . urlencode($msg));
        exit();
    }

} catch (PDOException $e) {
    $msg = "Erreur: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Paramètres Système - Admin A4 Taxi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="Asset/CSS/paramètres.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
   
</head>
<body>
    <header class="header">
        <div class="nav-top">
            <a href="dashboard_admin.php" class="nav-link"><i class="fas fa-gauge-high"></i> Dashboard</a>
            <div style="font-size: 1.2rem; font-weight: 700;">A4 Taxi Admin</div>
            <a href="login.php?logout=1" class="nav-link"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </div>
        <h1><i class="fas fa-cogs"></i> Paramètres Système</h1>
    </header>

    <main class="main-area">
        <?php if (!empty($_GET['msg'])): ?>
            <div class="message">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars(urldecode($_GET['msg'])) ?>
            </div>
        <?php endif; ?>

        <section>
            <div class="section">
                <div class="section-title">
                    <h2><i class="fas fa-sliders-h"></i> Configuration Tarifs & Système</h2>
                    <span class="badge"><?= count($parametres) ?> paramètres</span>
                </div>

                <form method="POST">
                    <?php foreach ($parametres as $param): ?>
                    <div class="param-group">
                        <div>
                            <div class="param-label"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $param['nom']))) ?></div>
                            <div class="param-desc"><?= htmlspecialchars($param['description']) ?></div>
                        </div>
                        
                        <?php if (in_array($param['nom'], ['notifications_sms', 'notifications_email', 'maintenance_mode'])): ?>
                            <label class="switch">
                                <input type="checkbox" name="parametres[<?= $param['nom'] ?>]" 
                                       value="1" <?= $param['valeur'] == '1' ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        <?php else: ?>
                            <input type="text" name="parametres[<?= $param['nom'] ?>]" 
                                   value="<?= htmlspecialchars($param['valeur']) ?>" 
                                   class="param-input" required>
                        <?php endif; ?>
                        
                        <div style="font-weight: 600; color: #3b82f6;">
                            Actuel: <?= htmlspecialchars($param['valeur']) ?>
                        </div>
                        <i class="fas fa-edit" style="color: #6b7280; font-size: 1.2rem;"></i>
                    </div>
                    <?php endforeach; ?>

                    <div style="text-align: center; padding-top: 2rem;">
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i> Sauvegarder tous les changements
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
