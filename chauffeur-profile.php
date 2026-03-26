<?php
// profil_chauffeur.php - PROFIL CHAUFFEUR COMPLET
require_once 'config.php';
require_once 'auth.php';

requireLogin();
requireRole(['chauffeur']);
if (isset($_GET['logout'])) {
    logout();
}


// ID de l'employé connecté
$employe_id = $_SESSION['employe_id'] ?? $_SESSION['users_id'] ?? null;
if (!$employe_id) {
    header('Location: login.php');
    exit();
}

// Récupération des infos chauffeur
try {
    $stmt = $pdo->prepare("SELECT * FROM employe WHERE id_employe = ?");
    $stmt->execute([$employe_id]);
    $profil = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profil) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'chauffeur'");
        $stmt->execute([$employe_id]);
        $profil = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$profil) {
        die("Profil chauffeur non trouvé.");
    }

    // Normalisation des données personnelles
    $nom_complet = trim(($profil['prenom'] ?? $profil['name'] ?? $profil['first_name'] ?? '') . ' ' .
        ($profil['nom'] ?? $profil['last_name'] ?? ''));
    $telephone = $profil['telephone'] ?? $profil['phone'] ?? $profil['tel'] ?? '';
    $email     = $profil['email']     ?? $profil['mail']  ?? '';

    // Récupération des données véhicule depuis la table Vehicule
    $stmt = $pdo->prepare("SELECT * FROM Vehicule WHERE id_employe = ?");
    $stmt->execute([$employe_id]);
    $vehicule_data   = $stmt->fetch(PDO::FETCH_ASSOC);
    $marque          = $vehicule_data['marque']          ?? '';
    $modele          = $vehicule_data['modele']          ?? '';
    $vehicule        = $vehicule_data['vehicule']        ?? $profil['vehicule']        ?? 'Non renseigné';
    $immatriculation = $vehicule_data['immatriculation'] ?? $profil['immatriculation'] ?? '';

} catch (PDOException $e) {
    die("Erreur base de données: " . $e->getMessage());
}

// Stats du chauffeur
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_courses,
            SUM(CASE WHEN statut = 'TERMINE' THEN 1 ELSE 0 END) as courses_terminees,
            SUM(prix_estime) as chiffre_affaires
        FROM Reservations 
        WHERE employe_id = ?
    ");
    $stmt->execute([$employe_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = ['total_courses' => 0, 'courses_terminees' => 0, 'chiffre_affaires' => 0];
}

// MISE À JOUR PROFIL
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $telephone_new = trim($_POST['telephone'] ?? '');

        // Mise à jour véhicule dans la table Vehicule
        $stmt = $pdo->prepare("
            UPDATE Vehicule 
            SET marque = ?, modele = ?, Vehicule = ?, immatriculation = ?
            WHERE employe_id = ?
        ");
        $stmt->execute([
            trim($_POST['marque']          ?? ''),
            trim($_POST['modele']          ?? ''),
            trim($_POST['vehicule']        ?? ''),
            trim($_POST['immatriculation'] ?? ''),
            $employe_id
        ]);

        // Téléphone + email restent dans users
        $stmt = $pdo->prepare("UPDATE users SET telephone = ?, email = ? WHERE id = ?");
        $stmt->execute([$telephone_new, $_POST['email'] ?? $email, $employe_id]);

        $msg = 'Profil mis à jour avec succès ! ✅';
        header('Location: chauffeur-profile.php?msg=' . urlencode($msg));
        exit();

    } catch (PDOException $e) {
        $msg = 'Erreur: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Profil - <?= htmlspecialchars($nom_complet) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="Asset/CSS/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%); min-height: 100vh; }

        .layout { display: flex; min-height: 100vh; }

        .sidebar { width: 260px; background: linear-gradient(180deg, #1f2937 0%, #111827 100%); color: white; padding: 0; }
        .sidebar-logo { padding: 2rem 1.5rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .logo-icon { font-size: 2.8rem; margin-bottom: 0.5rem; }
        .logo-text { font-size: 1.3rem; font-weight: 700; }
        .vehicule-info { margin-top: 1rem; font-size: 0.9rem; opacity: 0.9; background: rgba(255,255,255,0.1); padding: 1rem; border-radius: 12px; }

        .sidebar-nav { padding: 1.5rem 0; }
        .nav-link { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.8rem; color: #d1d5db; text-decoration: none; transition: all 0.3s; font-size: 0.95rem; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: white; }

        .main-area { flex: 1; padding: 2.5rem; }
        .page-header { margin-bottom: 2.5rem; text-align: center; }
        .page-header h1 { font-size: 2.5rem; color: #111827; margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: center; gap: 1rem; }

        .message { padding: 1.25rem 1.75rem; border-radius: 12px; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .message-success { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #166534; border-left: 5px solid #22c55e; }
        .message-error { background: linear-gradient(135deg, #fee2e2, #fecaca); color: #991b1b; border-left: 5px solid #ef4444; }

        .profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2.5rem; }
        .card { background: white; border-radius: 20px; padding: 2.5rem; box-shadow: 0 20px 60px rgba(0,0,0,0.08); }
        .card-title { font-size: 1.4rem; color: #111827; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; padding-bottom: 1rem; border-bottom: 2px solid #f3f4f6; }

        .profile-avatar { width: 120px; height: 120px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3rem; color: white; margin: 0 auto 2rem; }
        .profile-info { display: grid; gap: 1.5rem; }
        .info-item { display: flex; align-items: center; gap: 1rem; padding: 1rem; background: #f8fafc; border-radius: 12px; }
        .info-icon { width: 40px; height: 40px; background: #3b82f6; color: white; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1.5rem; }
        .stat-card { text-align: center; padding: 2rem 1rem; background: linear-gradient(135deg, #f8fafc, #f1f5f9); border-radius: 16px; border: 1px solid #e2e8f0; }
        .stat-number { font-size: 2.5rem; font-weight: 700; color: #1f2937; }
        .stat-label { color: #6b7280; font-size: 0.95rem; margin-top: 0.25rem; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 2rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-label { display: block; margin-bottom: 0.5rem; color: #374151; font-weight: 600; }
        .form-input { width: 100%; padding: 1rem 1.25rem; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 1rem; transition: all 0.3s; font-family: inherit; }
        .form-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }

        .form-section-title { font-size: 1rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.08em; margin: 1.5rem 0 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid #f3f4f6; grid-column: 1 / -1; }

        .btn-save { background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; padding: 1.25rem 2.5rem; border-radius: 12px; font-size: 1.1rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 0.75rem; transition: all 0.3s; width: 100%; justify-content: center; font-family: inherit; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(16,185,129,0.3); }

        @media (max-width: 768px) {
            .sidebar { position: fixed; width: 280px; transform: translateX(-100%); z-index: 1000; }
            .profile-grid { grid-template-columns: 1fr; }
            .form-grid { grid-template-columns: 1fr; }
            .main-area { padding: 1rem; }
        }
    </style>
</head>
<body>
<div class="layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <div class="logo-icon">🚖</div>
            <div class="logo-text">A4 Taxi Chauffeur</div>
            <div class="vehicule-info">
                <div style="font-size: 1.1rem; font-weight: 700; color: #fbbf24;">
                    <?= htmlspecialchars($immatriculation ?: 'Immat. N/A') ?>
                </div>
                <div style="font-size: 0.9rem;">
                    <?= htmlspecialchars(trim("$marque $modele") ?: $vehicule) ?>
                </div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard_chauffeur.php" class="nav-link">
                <i class="fas fa-gauge-high"></i> <span>Dashboard</span>
            </a>
            <a href="dashboard_chauffeur.php#mes-courses" class="nav-link">
                <i class="fas fa-car-side"></i> <span>Mes courses</span>
            </a>
            <a href="profil_chauffeur.php" class="nav-link active">
                <i class="fas fa-user-tie"></i> <span>Profil</span>
            </a>
            <a href="login.php?logout=1" class="nav-link">
                <i class="fas fa-sign-out-alt"></i> <span>Déconnexion</span>
            </a>
        </nav>
    </aside>

    <!-- CONTENU PRINCIPAL -->
    <main class="main-area">
        <header class="page-header">
            <h1>
                <i class="fas fa-user-tie"></i>
                Mon Profil
            </h1>
            <p>Gérez vos informations personnelles et consultez vos statistiques</p>
        </header>

        <?php if (!empty($_GET['msg'])): ?>
            <div class="message message-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars(urldecode($_GET['msg'])) ?>
            </div>
        <?php endif; ?>

        <?php if ($msg && str_starts_with($msg, 'Erreur')): ?>
            <div class="message message-error">
                <i class="fas fa-circle-exclamation"></i>
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <div class="profile-grid">

            <!-- ── INFOS ── -->
            <div class="card">
                <h2 class="card-title">
                    <i class="fas fa-id-card"></i>
                    Mes informations
                </h2>

                <div class="profile-avatar">
                    <?= strtoupper(substr($nom_complet, 0, 2)) ?>
                </div>

                <div class="profile-info">
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-user"></i></div>
                        <div>
                            <strong><?= htmlspecialchars($nom_complet) ?></strong>
                            <div style="font-size:.9rem;color:#6b7280;">Nom complet</div>
                        </div>
                    </div>

                    <?php if ($email): ?>
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-envelope"></i></div>
                        <div>
                            <strong><?= htmlspecialchars($email) ?></strong>
                            <div style="font-size:.9rem;color:#6b7280;">Email</div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-phone"></i></div>
                        <div>
                            <strong><?= htmlspecialchars($telephone ?: 'Non renseigné') ?></strong>
                            <div style="font-size:.9rem;color:#6b7280;">Téléphone</div>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-car"></i></div>
                        <div>
                            <strong><?= htmlspecialchars($marque ?: 'Non renseigné') ?></strong>
                            <div style="font-size:.9rem;color:#6b7280;">Marque</div>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-tag"></i></div>
                        <div>
                            <strong><?= htmlspecialchars($modele ?: 'Non renseigné') ?></strong>
                            <div style="font-size:.9rem;color:#6b7280;">Modèle</div>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-hashtag"></i></div>
                        <div>
                            <strong><?= htmlspecialchars($immatriculation ?: 'Non renseigné') ?></strong>
                            <div style="font-size:.9rem;color:#6b7280;">Immatriculation</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── STATS ── -->
            <div class="card">
                <h2 class="card-title">
                    <i class="fas fa-chart-line"></i>
                    Mes statistiques
                </h2>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number" style="color:#3b82f6;">
                            <?= number_format($stats['total_courses']) ?>
                        </div>
                        <div class="stat-label">Total courses</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" style="color:#10b981;">
                            <?= number_format($stats['courses_terminees']) ?>
                        </div>
                        <div class="stat-label">Terminées</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" style="color:#f59e0b; font-size:1.8rem;">
                            <?= number_format($stats['chiffre_affaires'] ?? 0, 2) ?> €
                        </div>
                        <div class="stat-label">Chiffre d'affaires</div>
                    </div>
                </div>
            </div>

            <!-- ── FORMULAIRE ── -->
            <div class="card" style="grid-column: 1 / -1;">
                <h2 class="card-title">
                    <i class="fas fa-edit"></i>
                    Modifier mes informations
                </h2>

                <form method="POST">
                    <div class="form-grid">

                        <!-- Infos personnelles -->
                        <div class="form-section-title">👤 Informations personnelles</div>

                        <div class="form-group">
                            <label class="form-label">Téléphone</label>
                            <input type="tel" class="form-input" name="telephone"
                                   value="<?= htmlspecialchars($telephone) ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-input" name="email"
                                   value="<?= htmlspecialchars($email) ?>">
                        </div>

                        <!-- Infos véhicule -->
                        <div class="form-section-title">🚘 Informations véhicule</div>

                        <div class="form-group">
                            <label class="form-label">Marque</label>
                            <input type="text" class="form-input" name="marque"
                                   value="<?= htmlspecialchars($marque) ?>" placeholder="Ex : Peugeot">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Modèle</label>
                            <input type="text" class="form-input" name="modele"
                                   value="<?= htmlspecialchars($modele) ?>" placeholder="Ex : 508">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Immatriculation</label>
                            <input type="text" class="form-input" name="immatriculation"
                                   value="<?= htmlspecialchars($immatriculation) ?>" placeholder="Ex : AB-123-CD" required>
                        </div>

                        <!-- Bouton pleine largeur -->
                        <div style="grid-column: 1 / -1;">
                            <button type="submit" class="btn-save">
                                <i class="fas fa-save"></i>
                                Enregistrer les modifications
                            </button>
                        </div>

                    </div>
                </form>
            </div>

        </div>
    </main>
</div>
</body>
</html>