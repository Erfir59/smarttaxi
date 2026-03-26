<?php
require_once 'config.php'; // Même niveau que dashboard_admin.php



// Vérifier si c'est un client connecté
if (!isset($_SESSION['users_id']) || $_SESSION['role'] !== 'client') {
    header('Location: client-profile.php');
    exit();
}


$users_id = $_SESSION['users_id'];

// Récupérer infos client
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$users_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

// Modifier profil
if ($_POST) {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $telephone = trim($_POST['telephone']);
    $adresse = trim($_POST['adresse']);
    
    $stmt = $pdo->prepare("UPDATE users SET nom=?, prenom=?, telephone=?, adresse=? WHERE id=?");
    $stmt->execute([$nom, $prenom, $telephone, $adresse, $users_id]);
    
    header('Location: client-profile.php?success=1');
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Profil Client - A4 Taxi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="Asset/CSS/dashboard_client.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f8f9fa; min-height: 100vh; }
        .layout { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #2c3e50; color: white; }
        .sidebar-logo { padding: 20px; border-bottom: 1px solid #34495e; }
        .sidebar-nav a { display: flex; align-items: center; padding: 15px 20px; color: #ecf0f1; text-decoration: none; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: #667eea; }
        .main-area { flex: 1; padding: 20px; }
        .page-header { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin: 20px 0; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 10px; text-align: center; }
        .card { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 25px; }
        label { display: block; margin-bottom: 8px; color: #555; font-weight: 500; }
        input, textarea { width: 100%; padding: 15px; border: 2px solid #eee; border-radius: 8px; font-size: 16px; transition: border-color 0.3s; box-sizing: border-box; }
        input:focus, textarea:focus { border-color: #667eea; outline: none; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; width: 100%; }
        .btn-primary:hover { transform: translateY(-2px); }
        .btn-secondary { background: #28a745; color: white; padding: 12px 24px; border: none; border-radius: 5px; text-decoration: none; display: inline-block; margin-right: 10px; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>
    <div class="layout">
        <!-- Sidebar (identique au dashboard) -->
        <aside class="sidebar">
            <div class="sidebar-logo">
                <span class="logo-icon">🚖</span>
                <span class="logo-text">A4 TAXI Client</span>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard_client.php" class="nav-link">
                    <i class="fas fa-chart-line"></i> Dashboard
                </a>
                <a href="client-profile.php" class="nav-link active">
                    <i class="fas fa-user-shield"></i> Profil Client
                </a>
                <a href="liste_reservations.php" class="nav-link">
                    <i class="fas fa-list-ul"></i> Réservations
                </a>
                <a href="?logout=1" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </nav>
        </aside>

        <!-- Contenu principal -->
        <main class="main-area">
            <header class="page-header">
                <h1><i class="fas fa-user-shield"></i> Profil Client</h1>
                <p>Bonjour, <?= htmlspecialchars($client['prenom'] ?? '') ?> • <?= date('d M Y H:i') ?></p>
                <a href="dashboard_client.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Retour Dashboard</a>
            </header>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">✅ Profil mis à jour avec succès !</div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <h3><?php echo $pdo->query("SELECT COUNT(*) FROM users WHERE role='client'")->fetchColumn(); ?></h3>
                    <p>Clients</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-car"></i>
                    <h3>25</h3>
                    <p>Courses/jour</p>
                </div>
            </div>

            <!-- Formulaire profil -->
            <div class="card">
                <h2><i class="fas fa-edit"></i> Mes informations</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Nom complet</label>
                        <input type="text" name="nom" value="<?= htmlspecialchars($client['nom'].' '.$client['prenom']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="tel" name="telephone" value="<?= htmlspecialchars($client['telephone']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" value="<?= htmlspecialchars($client['email']) ?>" readonly style="background: #f8f9fa;">
                    </div>
                    <div class="form-group">
                        <label>Adresse</label>
                        <textarea name="adresse" rows="3"><?= htmlspecialchars($client['adresse'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
