<?php
// dashboard_chauffeur.php - AVEC BOUTON MISE EN ATTENTE
require_once 'config.php';
require_once 'auth.php';

requireLogin();
requireRole(['chauffeur']);

if (isset($_GET['logout'])) {
    logout();
}


// ID de l'employé connecté (stocké en session au login)
$employe_id = $_SESSION['employe_id'] ?? $_SESSION['users_id'] ?? $_SESSION['users_id'] ?? null;
if (!$employe_id) {
    header('Location: login.php');
    exit();
}

// 1. Infos employé chauffeur - VERSION AUTO-ADAPTATIVE
try {
    $stmt = $pdo->prepare("
        SELECT * 
        FROM employe 
        WHERE id_employe = ?
    ");
    $stmt->execute([$employe_id]);
    $employe = $stmt->fetch(PDO::FETCH_ASSOC);
  
    if (!$employe) {
        // Fallback vers table users si employe vide
        $stmt = $pdo->prepare("
            SELECT * FROM users WHERE id = ? AND role = 'chauffeur'
        ");
        $stmt->execute([$employe_id]);
        $employe = $stmt->fetch(PDO::FETCH_ASSOC);
    }
  
    if (!$employe) {
        die("Erreur: Chauffeur non trouvé. ID: " . $employe_id);
    }
  
    // Détection automatique des noms de colonnes
    $_SESSION['nom'] = trim(
        ($employe['prenom'] ?? $employe['name'] ?? $employe['first_name'] ?? '') . ' ' . 
        ($employe['nom'] ?? $employe['name'] ?? $employe['last_name'] ?? '')
    );
    $_SESSION['immatriculation'] = $employe['immatriculation'] ?? $employe['immat'] ?? $employe['plate'] ?? '';
    $_SESSION['vehicule'] = $employe['vehicule'] ?? $employe['vehicle'] ?? $employe['voiture'] ?? 'Véhicule standard';
    $_SESSION['telephone_chauffeur'] = $employe['telephone'] ?? $employe['phone'] ?? $employe['tel'] ?? '';

} catch (PDOException $e) {
    die("Erreur base de données: " . $e->getMessage());
}

// 2. COURSES DISPONIBLES (EN_ATTENTE, non attribuées)
try {
    $stmt = $pdo->prepare("
        SELECT r.*, u.nom as client_nom, u.prenom as client_prenom, u.telephone as client_tel
        FROM Reservations r
        JOIN users u ON r.client_id = u.id
        WHERE r.statut = 'EN_ATTENTE' AND r.chauffeur_id IS NULL
        ORDER BY r.created_at ASC
        LIMIT 10
    ");
    $stmt->execute();
    $disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $disponibles = [];
}

// 3. MES COURSES ATTRIBUÉES
try {
    $stmt = $pdo->prepare("
        SELECT r.*, u.nom as client_nom, u.prenom as client_prenom, u.telephone as client_tel
        FROM Reservation r
        LEFT JOIN users u ON r.client_id = u.id
        WHERE r.chauffeur_id = ?
        ORDER BY FIELD(r.statut, 'ATTRIBUEE','ACCEPTEE','EN_APPROCHE','EN_COURS'), r.date_resa, r.heure_resa
    ");
    $stmt->execute([$employe_id]);
    $mes_resas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mes_resas = [];
}

// 4. ACTIONS COURSES - AVEC MISE EN ATTENTE
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resa_id'], $_POST['action'])) {
    $resa_id = (int)$_POST['resa_id'];
    $action = $_POST['action'];

    try {
        if ($action === 'accepter') {
            $stmt = $pdo->prepare("
                UPDATE Reservation
                SET chauffeur_id = ?, statut = 'ATTRIBUEE' 
                WHERE id = ? AND statut = 'EN_ATTENTE'
            ");
            $stmt->execute([$employe_id, $resa_id]);
            $msg = 'Course acceptée ! ✅';
        } elseif ($action === 'refuser') {
            $stmt = $pdo->prepare("
                UPDATE Reservation
                SET statut = 'ANNULEE' 
                WHERE id = ? AND statut = 'EN_ATTENTE'
            ");
            $stmt->execute([$resa_id]);
            $msg = 'Course refusée.';
        } 
        // **NOUVEAU** : Mise en attente
        elseif ($action === 'attente') {
            $stmt = $pdo->prepare("
                UPDATE Reservation 
                SET statut = 'EN_ATTENTE', chauffeur_id = NULL
                WHERE id = ? AND chauffeur_id = ?
            ");
            $stmt->execute([$resa_id, $employe_id]);
            $msg = 'Course remise en attente.';
        }
        elseif ($action === 'update_statut' && isset($_POST['nouveau_statut'])) {
            $nouveau_statut = $_POST['nouveau_statut'];
            $stmt = $pdo->prepare("
                UPDATE Reservation 
                SET statut = ? 
                WHERE id = ? AND chauffeur_id = ?
            ");
            $stmt->execute([$nouveau_statut, $resa_id, $employe_id]);
            $msg = 'Statut mis à jour.';
        }

        header('Location: dashboard_chauffeur.php?msg=' . urlencode($msg));
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
    <title>Dashboard Chauffeur - <?= htmlspecialchars($_SESSION['nom'] ?? 'Chauffeur') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="Asset/CSS/sidebar.css">
    <link rel="stylesheet" href="Asset/CSS/dashboard_chauffeur.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- CSS INLINE pour éviter les conflits avec les fichiers externes -->
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
        .page-header { margin-bottom: 2.5rem; }
        .page-header h1 { font-size: 2.3rem; color: #111827; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 1rem; }
        .page-header p { color: #6b7280; font-size: 1.1rem; }
        
        .message { padding: 1.25rem 1.75rem; border-radius: 12px; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .message-success { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #166534; border-left: 5px solid #22c55e; }
        
        .section { background: white; border-radius: 20px; padding: 2.5rem; margin-bottom: 2.5rem; box-shadow: 0 20px 60px rgba(0,0,0,0.08); }
        .section-title { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; }
        .section-title h2 { font-size: 1.6rem; color: #111827; display: flex; align-items: center; gap: 0.75rem; }
        .badge { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; padding: 0.5rem 1.25rem; border-radius: 25px; font-weight: 700; font-size: 0.9rem; }
        
        .course-card { display: flex; justify-content: space-between; gap: 2rem; padding: 2rem; border-radius: 16px; margin-bottom: 1.5rem; background: linear-gradient(135deg, #f8fafc, #f1f5f9); border: 1px solid #e2e8f0; transition: all 0.3s; }
        .course-card:hover { transform: translateY(-4px); box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .course-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem; }
        .course-header h3 { font-size: 1.4rem; color: #1f2937; margin: 0; }
        .statut-badge { padding: 0.5rem 1rem; border-radius: 20px; font-weight: 700; font-size: 0.85rem; }
        
        .course-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; margin-bottom: 1.25rem; font-size: 0.95rem; color: #4b5563; }
        .client-info { background: linear-gradient(135deg, rgba(59,130,246,0.1), rgba(99,102,241,0.1)); padding: 1.25rem; border-radius: 12px; border-left: 4px solid #3b82f6; }
        
        .actions { display: flex; flex-direction: column; gap: 0.75rem; min-width: 200px; }
        .btn { border: none; border-radius: 12px; padding: 0.875rem 1.25rem; cursor: pointer; font-weight: 700; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem; transition: all 0.3s; }
        .btn-accept { background: linear-gradient(135deg, #10b981, #059669); color: white; box-shadow: 0 4px 15px rgba(16,185,129,0.3); }
        .btn-refuse { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; box-shadow: 0 4px 15px rgba(239,68,68,0.3); }
        .btn-attente { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; box-shadow: 0 4px 15px rgba(245,158,11,0.3); }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.2); }
        
        .status-buttons { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1.5rem; }
        .btn-status { background: #f3f4f6; color: #374151; border: 2px solid #d1d5db; }
        
        .empty-state { text-align: center; padding: 4rem 2rem; color: #6b7280; }
        .empty-state i { font-size: 4rem; margin-bottom: 1rem; opacity: 0.4; }
        
        @media (max-width: 768px) {
            .sidebar { position: fixed; width: 280px; transform: translateX(-100%); z-index: 1000; }
            .course-card { flex-direction: column; gap: 1.5rem; }
            .main-area { padding: 1rem; }
            .actions { flex-direction: row; flex-wrap: wrap; }
            .btn { flex: 1; min-width: 120px; }
        }
    </style>
</head>
<body>
    <div class="layout">
        <!-- SIDEBAR EMPLOYE -->
        <aside class="sidebar">
            <div class="sidebar-logo">
                <div class="logo-icon">🚖</div>
                <div class="logo-text">A4 Taxi Chauffeur</div>
                <div class="vehicule-info">
                    <div style="font-size: 1.1rem; font-weight: 700; color: #fbbf24;">
                        <?= htmlspecialchars($_SESSION['immatriculation'] ?? 'Non renseigné') ?>
                    </div>
                    <div style="font-size: 0.9rem;">
                        <?= htmlspecialchars($_SESSION['vehicule'] ?? 'Véhicule standard') ?>
                    </div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard_chauffeur.php" class="nav-link active">
                    <i class="fas fa-gauge-high"></i> <span>Dashboard</span>
                </a>
                <a href="liste.php" class="nav-link">
                    <i class="fas fa-car-side"></i> <span>Mes courses</span>
                </a>
                <a href="incidents.php" class="nav-link">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Incidents</span>
                </a>
                <a href="chauffeur-profile.php" class="nav-link">
                    <i class="fas fa-user-tie"></i> <span>Mon profil</span>
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
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard Chauffeur
                </h1>
                <p>Bienvenue <?= htmlspecialchars($_SESSION['nom'] ?? 'Chauffeur') ?></p>
            </header>

            <?php if (!empty($_GET['msg'])): ?>
                <div class="message message-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars(urldecode($_GET['msg'])) ?>
                </div>
            <?php endif; ?>

            <!-- COURSES DISPONIBLES - 3 BOUTONS -->
            <section class="disponibles">
                <div class="section">
                    <div class="section-title">
                        <h2><i class="fas fa-list-check"></i> Courses disponibles</h2>
                        <span class="badge"><?= count($disponibles) ?></span>
                    </div>

                    <?php if (empty($disponibles)): ?>
                        <div class="empty-state">
                            <i class="fas fa-bell-slash"></i>
                            <h3>Aucune course disponible</h3>
                            <p>Les courses apparaîtront ici dès qu'elles seront réservées.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($disponibles as $resa): ?>
                        <div class="course-card">
                            <div style="flex: 1;">
                                <div class="course-header">
                                    <h3>
                                        <?= htmlspecialchars($resa['depart']) ?> 
                                        <i class="fas fa-arrow-right" style="color: #10b981; margin: 0 0.75rem;"></i>
                                        <?= htmlspecialchars($resa['arrivee']) ?>
                                    </h3>
                                </div>
                                
                                <div class="course-details">
                                    <div><i class="fas fa-calendar"></i> <?= htmlspecialchars($resa['date_resa']) ?> <?= htmlspecialchars($resa['heure_resa']) ?></div>
                                    <div><i class="fas fa-users"></i> <?= (int)$resa['passagers'] ?> passag.</div>
                                    <div><i class="fas fa-suitcase"></i> <?= (int)($resa['bagages'] ?? 0) ?> bagages</div>
                                    <div style="color: #059669; font-weight: 700;">
                                        <i class="fas fa-euro-sign"></i> <?= number_format($resa['prix_estime'] ?? 0, 2) ?> €
                                    </div>
                                </div>
                                
                                <div class="client-info">
                                    <strong>👤 <?= htmlspecialchars(($resa['client_prenom'] ?? '') . ' ' . $resa['client_nom']) ?></strong><br>
                                    📞 <a href="tel:<?= htmlspecialchars($resa['client_tel']) ?>" style="color: #3b82f6; font-weight: 600;">
                                        <?= htmlspecialchars($resa['client_tel']) ?>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="actions">
                                <form method="post" style="display: block;">
                                    <input type="hidden" name="resa_id" value="<?= (int)$resa['id'] ?>">
                                    <button class="btn btn-accept" type="submit" name="action" value="accepter">
                                        <i class="fas fa-check-circle"></i> Accepter
                                    </button>
                                </form>
                                <form method="post" style="display: block;" onsubmit="return confirm('Refuser cette course ?');">
                                    <input type="hidden" name="resa_id" value="<?= (int)$resa['id'] ?>">
                                    <button class="btn btn-refuse" type="submit" name="action" value="refuser">
                                        <i class="fas fa-times-circle"></i> Refuser
                                    </button>
                                </form>
                                <!-- **NOUVEAU BOUTON MISE EN ATTENTE** -->
                                <form method="post" style="display: block;" onsubmit="return confirm('Remettre cette course en attente ?');">
                                    <input type="hidden" name="resa_id" value="<?= (int)$resa['id'] ?>">
                                    <button class="btn btn-attente" type="submit" name="action" value="attente">
                                        <i class="fas fa-pause-circle"></i> En attente
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- MES COURSES - BOUTON EN ATTENTE DISPONIBLE -->
            <section class="mes-courses" id="mes-courses">
                <div class="section">
                    <div class="section-title">
                        <h2><i class="fas fa-car-side"></i> Mes courses actives</h2>
                        <span class="badge"><?= count($mes_resas) ?></span>
                    </div>

                    <?php if (empty($mes_resas)): ?>
                        <div class="empty-state">
                            <i class="fas fa-road"></i>
                            <h3>Aucune course active</h3>
                            <p>Acceptez une course disponible pour commencer !</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($mes_resas as $resa): ?>
                        <div class="course-card">
                            <div style="flex: 1;">
                                <div class="course-header">
                                    <h3><?= htmlspecialchars($resa['depart']) ?> → <?= htmlspecialchars($resa['arrivee']) ?></h3>
                                    <span class="statut-badge" style="
                                        background: <?= [
                                            'ATTRIBUEE' => '#dbeafe', 'ACCEPTEE' => '#dcfce7', 
                                            'EN_APPROCHE' => '#fef2f2', 'EN_COURS' => '#fee2e2', 
                                            'TERMINE' => '#f0fdf4', 'EN_ATTENTE' => '#fef3c7'
                                        ][$resa['statut']] ?? '#f3f4f6'; ?>;
                                        color: <?= [
                                            'ATTRIBUEE' => '#1e40af', 'ACCEPTEE' => '#166534', 
                                            'EN_APPROCHE' => '#dc2626', 'EN_COURS' => '#dc2626', 
                                            'TERMINE' => '#15803d', 'EN_ATTENTE' => '#92400e'
                                        ][$resa['statut']] ?? '#6b7280' ?>;
                                    ">
                                        <?= str_replace('_', ' ', ucwords(strtolower($resa['statut'] ?? 'en attente'))) ?>
                                    </span>
                                </div>
                                
                                <div class="course-details">
                                    <div><i class="fas fa-calendar-alt"></i> <?= htmlspecialchars($resa['date_resa']) ?> <?= htmlspecialchars($resa['heure_resa']) ?></div>
                                    <div><i class="fas fa-users"></i> <?= (int)($resa['passagers'] ?? 1) ?> passag.</div>
                                    <div><i class="fas fa-route"></i> <?= number_format($resa['distance_estimee'] ?? 0, 1) ?> km</div>
                                    <div style="color: #059669; font-weight: 700; font-size: 1.1rem;">
                                        <i class="fas fa-euro-sign"></i> <?= number_format($resa['prix_estime'] ?? 0, 2) ?> €
                                    </div>
                                </div>
                                
                                <?php if (!empty($resa['client_nom'])): ?>
                                <div class="client-info">
                                    <strong>👤 <?= htmlspecialchars(($resa['client_prenom'] ?? '') . ' ' . $resa['client_nom']) ?></strong><br>
                                    📞 <a href="tel:<?= htmlspecialchars($resa['client_tel']) ?>" style="color: #3b82f6;">
                                        <?= htmlspecialchars($resa['client_tel']) ?>
                                    </a>
                                </div>
                                <?php endif; ?>

                                <?php if (in_array($resa['statut'], ['ATTRIBUEE', 'ACCEPTEE'])): ?>
                                <div class="status-buttons">
                                    <form method="post">
                                        <input type="hidden" name="resa_id" value="<?= (int)$resa['id'] ?>">
                                        <button class="btn btn-status" type="submit" name="action" value="update_statut">
                                            <i class="fas fa-map-marker-alt"></i> En approche
                                            <input type="hidden" name="nouveau_statut" value="EN_APPROCHE">
                                        </button>
                                    </form>
                                    <form method="post">
                                        <input type="hidden" name="resa_id" value="<?= (int)$resa['id'] ?>">
                                        <button class="btn btn-status" type="submit" name="action" value="update_statut">
                                            <i class="fas fa-car"></i> En cours
                                            <input type="hidden" name="nouveau_statut" value="EN_COURS">
                                        </button>
                                    </form>
                                    <form method="post">
                                        <input type="hidden" name="resa_id" value="<?= (int)$resa['id'] ?>">
                                        <button class="btn btn-status" type="submit" name="action" value="update_statut">
                                            <i class="fas fa-flag-checkered"></i> Terminée
                                            <input type="hidden" name="nouveau_statut" value="TERMINE">
                                        </button>
                                    </form>
                                    <!-- **BOUTON EN ATTENTE POUR MES COURSES** -->
                                    <form method="post" style="margin-top: 0.5rem;">
                                        <input type="hidden" name="resa_id" value="<?= (int)$resa['id'] ?>">
                                        <button class="btn btn-attente" type="submit" name="action" value="attente" 
                                                onclick="return confirm('Remettre cette course en attente pour d\'autres chauffeurs ?');">
                                            <i class="fas fa-pause-circle"></i> En attente
                                        </button>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <script>
        // Auto-refresh des courses disponibles toutes les 30s
        setInterval(() => {
            if (document.querySelector('.disponibles .course-card')) {
                location.reload();
            }
        }, 30000);

        // Animations hover
        document.querySelectorAll('.course-card').forEach(card => {
            card.addEventListener('mouseenter', () => card.style.transform = 'translateY(-4px)');
            card.addEventListener('mouseleave', () => card.style.transform = 'translateY(0)');
        });
    </script>
</body>
</html>
