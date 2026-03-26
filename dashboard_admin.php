<?php
// dashboard_admin.php - VERSION COMPLÈTE AVEC STATS RÉELLES
require_once 'config.php';
require_once 'auth.php';

requireLogin();
requireRole(['admin', 'superadmin']); // Admin + superadmin

// Gérer déconnexion
if (isset($_GET['logout'])) {
    logout();
}

// RÉCUPÉRER LES STATISTIQUES RÉELLES
try {
    // Total réservations par statut
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN statut = 'EN_ATTENTE' THEN 1 ELSE 0 END) as attente,
            SUM(CASE WHEN statut = 'ATTRIBUEE' THEN 1 ELSE 0 END) as attribuees,
            SUM(CASE WHEN statut = 'TERMINE' THEN 1 ELSE 0 END) as terminees,
            SUM(prix_estime) as revenus
        FROM Reservations
    ");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Nombre de chauffeurs
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM employe WHERE role = 'chauffeur'");
    $stmt->execute();
    $nb_chauffeurs = $stmt->fetchColumn();

    // Nombre de clients
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'client'");
    $stmt->execute();
    $nb_clients = $stmt->fetchColumn();

} catch (PDOException $e) {
    $stats = ['total' => 0, 'attente' => 0, 'attribuees' => 0, 'terminees' => 0, 'revenus' => 0];
    $nb_chauffeurs = 0;
    $nb_clients = 0;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - A4 Taxi</title>
    <link rel="stylesheet" href="Asset/CSS/dashboard_admin.css">
    <link rel="stylesheet" href="Asset/CSS/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- CSS INLINE pour cohérence avec liste.php -->
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%); }
        
        .layout { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: linear-gradient(180deg, #1f2937 0%, #111827 100%); color: white; padding: 0; }
        .sidebar-logo { padding: 2rem 1.5rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .logo-icon { font-size: 2.8rem; margin-bottom: 0.5rem; }
        .logo-text { font-size: 1.3rem; font-weight: 700; }
        
        .sidebar-nav { padding: 1.5rem 0; }
        .nav-link { 
            display: flex; align-items: center; gap: 1rem; 
            padding: 1rem 1.8rem; color: #d1d5db; text-decoration: none; 
            transition: all 0.3s; font-size: 0.95rem; 
        }
        .nav-link:hover, .nav-link.active { 
            background: rgba(255,255,255,0.1); color: white; 
        }
        .nav-link i { width: 20px; margin-right: 10px; }
        
        .main-area { flex: 1; padding: 2.5rem; }
        .page-header { margin-bottom: 2.5rem; }
        .page-header h1 { 
            font-size: 2.3rem; color: #111827; margin-bottom: 0.5rem; 
            display: flex; align-items: center; gap: 1rem; 
        }
        .page-header p { color: #6b7280; font-size: 1.1rem; }
        
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 2rem; 
            margin-bottom: 2.5rem; 
        }
        .stat-card { 
            background: white; 
            border-radius: 20px; 
            padding: 2.5rem; 
            text-align: center; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.08); 
            transition: all 0.3s; 
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { 
            width: 80px; height: 80px; 
            margin: 0 auto 1.5rem; 
            border-radius: 20px; 
            display: flex; align-items: center; justify-content: center; 
            font-size: 2rem; color: white; 
        }
        .stat-number { font-size: 2.5rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem; }
        .stat-label { color: #6b7280; font-size: 1rem; }
        
        .stat-role { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .stat-resa { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-chauffeurs { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-revenus { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        
        .dashboard-actions { display: flex; gap: 1.5rem; flex-wrap: wrap; }
        .btn-primary, .btn-secondary { 
            padding: 1.25rem 2.5rem; 
            border-radius: 12px; 
            text-decoration: none; 
            font-weight: 700; 
            font-size: 1.1rem; 
            display: flex; align-items: center; gap: 0.75rem; 
            transition: all 0.3s; 
        }
        .btn-primary { 
            background: linear-gradient(135deg, #3b82f6, #1d4ed8); 
            color: white; 
        }
        .btn-secondary { 
            background: linear-gradient(135deg, #6b7280, #4b5563); 
            color: white; 
        }
        .btn-primary:hover, .btn-secondary:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 15px 35px rgba(0,0,0,0.2); 
        }
        
        .quick-links { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
            gap: 2rem; 
            margin-top: 2.5rem; 
        }
        .quick-card { 
            background: white; 
            border-radius: 20px; 
            padding: 2.5rem; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.08); 
            text-decoration: none; 
            color: inherit; 
            transition: all 0.3s; 
        }
        .quick-card:hover { transform: translateY(-5px); }
        .quick-icon { 
            width: 60px; height: 60px; 
            border-radius: 16px; 
            display: flex; align-items: center; justify-content: center; 
            font-size: 1.5rem; color: white; margin-bottom: 1.5rem; 
        }
        
        @media (max-width: 768px) {
            .main-area { padding: 1.5rem; }
            .dashboard-actions { flex-direction: column; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-logo">
                <span class="logo-icon">🚖</span>
                <span class="logo-text">A4 TAXI Admin</span>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard_admin.php" class="nav-link active">
                    <i class="fas fa-gauge-high"></i>
                    <span>Dashboard</span>
                </a>
            
                </a>
                <a href="liste.php" class="nav-link"> <!-- ✅ LIEN VERS LISTE.PHP -->
                    <i class="fas fa-list-ul"></i>
                    <span>Gestion Courses</span>
                </a>
                <a href="historique.php" class="nav-link">
                    <i class="fas fa-clock-rotate-left"></i>
                    <span>Historique des courses</span>
                </a>
                <a href="admin-profile.php" class="nav-link">
                    <i class="fas fa-user-shield"></i>
                    <span>Profil Admin</span>
                </a>
                <a href="incidents.php" class="nav-link">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Incidents</span>
                </a>
                <a href="paramètres.php" class="nav-link">
                    <i class="fas fa-cog"></i>
                    <span>Paramètres</span>
                </a>
                <a href="?logout=1" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </nav>
        </aside>

        <!-- Contenu principal -->
        <main class="main-area">
            <header class="page-header">
                <h1>
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard Admin
                </h1>
                <p>Bonjour, <?= htmlspecialchars($_SESSION['email'] ?? 'Admin') ?> • <?= date('d M Y H:i') ?></p>
            </header>

            <!-- Stats réelles -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon stat-role">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="stat-number">ADMIN</div>
                    <div class="stat-label">Rôle</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon stat-resa">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-number"><?= number_format($stats['total']) ?></div>
                    <div class="stat-label">Total Réservations</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon stat-chauffeurs">
                        <i class="fas fa-car-side"></i>
                    </div>
                    <div class="stat-number"><?= $nb_chauffeurs ?></div>
                    <div class="stat-label">Chauffeurs</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon stat-revenus">
                        <i class="fas fa-euro-sign"></i>
                    </div>
                    <div class="stat-number"><?= number_format($stats['revenus'], 2) ?>€</div>
                    <div class="stat-label">Revenus totaux de l'entreprise</div>
                </div>
            </div>

            <!-- Actions rapides -->
            <div class="dashboard-actions">
                <a href="liste.php" class="btn-primary">
                    <i class="fas fa-list-ul"></i>
                    Gestion des Courses
                </a>
                
                <a href="admin-profile.php" class="btn-secondary">
                    <i class="fas fa-user-shield"></i>
                    Mon Profil
                </a>
            </div>

            <!-- Quick Links -->
            <div class="quick-links">
                <a href="liste.php" class="quick-card">
                    <div class="quick-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                        <i class="fas fa-list-check"></i>
                    </div>
                    <h3 style="font-size: 1.3rem; color: #1f2937; margin-bottom: 1rem;">
                        Courses en attente (<?= $stats['attente'] ?>)
                    </h3>
                    <p style="color: #6b7280;">Attribuez les courses aux chauffeurs</p>
                </a>
                
                <a href="resultats.php" class="quick-card">
                    <div class="quick-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 style="font-size: 1.3rem; color: #1f2937; margin-bottom: 1rem;">
                        Statistiques (<?= $stats['terminees'] ?> terminées)
                    </h3>
                    <p style="color: #6b7280;">Analysez vos performances</p>
                </a>
            </div>
        </main>
    </div>
</body>
</html>
