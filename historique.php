<?php
// historique.php - VERSION ADMIN COMPLÈTE ET FONCTIONNELLE
require_once 'config.php';
require_once 'auth.php';

requireLogin();
requireRole(['admin', 'chauffeur']); // Réservé aux admins

// Gérer déconnexion
if (isset($_GET['logout'])) {
    logout();
}

// PARAMÈTRES DE PAGINATION & FILTRES
$page        = max(1, (int)($_GET['page'] ?? 1));
$per_page    = 10;
$offset      = ($page - 1) * $per_page;
$filtre      = $_GET['filtre'] ?? 'tous';
$recherche   = trim($_GET['q'] ?? '');
$tri         = $_GET['tri'] ?? 'date_desc';

try {
    // COMPTE TOTAL POUR PAGINATION
    $where_conditions = [];
    $params = [];
    
    if ($filtre === 'en_attente') {
        $where_conditions[] = "statut = 'EN_ATTENTE'";
    } elseif ($filtre === 'attribuee') {
        $where_conditions[] = "statut = 'ATTRIBUEE'";
    } elseif ($filtre === 'terminee') {
        $where_conditions[] = "statut = 'TERMINE'";
    }
    
    if ($recherche !== '') {
        $where_conditions[] = "(adresse_depart LIKE :q OR adresse_arrivee LIKE :q OR chauffeur_nom LIKE :q)";
        $params[':q'] = "%$recherche%";
    }
    
    $where_sql = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Compte total
    $count_sql = "SELECT COUNT(*) FROM Reservations $where_sql";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
    $total_pages = max(1, ceil($total / $per_page));
    
    // TRIS DISPONIBLES
    $order_map = [
        'date_desc'  => 'date_creation DESC',
        'date_asc'   => 'date_creation ASC',
        'prix_desc'  => 'prix_estime DESC',
        'prix_asc'   => 'prix_estime ASC',
    ];
    $order_sql = $order_map[$tri] ?? 'date_creation DESC';
    
    // RÉCUPÉRATION DES RÉSERVATIONS
    $sql = "
        SELECT 
            id, date_creation, adresse_depart, adresse_arrivee, 
            prix_estime, statut, chauffeur_nom, note_client,
            vehicule_type, duree_estimee_minutes, distance_estimee_km
        FROM Reservations
        $where_sql
        ORDER BY $order_sql
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // STATISTIQUES GÉNÉRALES
    $stats_sql = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN statut='EN_ATTENTE' THEN 1 ELSE 0 END) as attente,
            SUM(CASE WHEN statut='ATTRIBUEE' THEN 1 ELSE 0 END) as attribuees,
            SUM(CASE WHEN statut='TERMINE' THEN 1 ELSE 0 END) as terminees,
            COALESCE(SUM(prix_estime), 0) as revenus
        FROM Reservations
    ";
    $stmt_stats = $pdo->query($stats_sql);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $reservations = [];
    $total = 0;
    $total_pages = 1;
    $stats = ['total' => 0, 'attente' => 0, 'attribuees' => 0, 'terminees' => 0, 'revenus' => 0];
}

// Helpers
function statut_label($statut) {
    return match($statut) {
        'EN_ATTENTE' => 'En attente',
        'ATTRIBUEE'  => 'Attribuée', 
        'TERMINE'    => 'Terminée',
        default      => ucfirst($statut)
    };
}

function statut_class($statut) {
    return match($statut) {
        'EN_ATTENTE' => 'badge-warning',
        'ATTRIBUEE'  => 'badge-info',
        'TERMINE'    => 'badge-success',
        default      => 'badge-secondary'
    };
}

function etoiles($note) {
    $html = '';
    $note = (int)$note;
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span class="star' . ($i <= $note ? ' filled' : '') . '">★</span>';
    }
    return $html;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique - A4 Taxi</title>
    <link rel="stylesheet" href="Asset/CSS/historique.css">
    <link rel="stylesheet" href="Asset/CSS/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
   
</head>
<body>
    <div class="layout">
        <!-- SIDEBAR ADMIN -->
        <aside class="sidebar">
            <div class="sidebar-logo">
                <span class="logo-icon">🚖</span>
                <span class="logo-text">A4 TAXI Admin</span>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard_admin.php" class="nav-link">
                    <i class="fas fa-gauge-high"></i>
                    <span>Dashboard</span>
                </a>
                <a href="liste.php" class="nav-link">
                    <i class="fas fa-list-ul"></i>
                    <span>Gestion Courses</span>
                </a>
                <a href="historique.php" class="nav-link active">
                    <i class="fas fa-clock-rotate-left"></i>
                    <span>Historique</span>
                </a>
                <a href="admin-profile.php" class="nav-link">
                    <i class="fas fa-user-shield"></i>
                    <span>Profil Admin</span>
                </a>
                <a href="?logout=1" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </nav>
        </aside>

        <!-- CONTENU PRINCIPAL -->
        <main class="main-area">
            <header class="page-header">
                <h1>
                    <i class="fas fa-history"></i>
                    Historique Complet
                </h1>
                <p>Bonjour, <?= htmlspecialchars($_SESSION['email'] ?? 'Admin') ?> • 
                   <?= date('d M Y H:i') ?> • <?= $total ?> réservation<?= $total > 1 ? 's' : '' ?>
                </p>
            </header>

            <!-- STATS RAPIDES -->
            <div class="stats-header">
                <div class="stat-item">
                    <div class="stat-number"><?= number_format($stats['total']) ?></div>
                    <div class="stat-label">Total</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= $stats['attente'] ?></div>
                    <div class="stat-label">En attente</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= $stats['attribuees'] ?></div>
                    <div class="stat-label">Attribuées</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= $stats['terminees'] ?></div>
                    <div class="stat-label">Terminées</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= number_format($stats['revenus'], 2) ?>€</div>
                    <div class="stat-label">Revenus</div>
                </div>
            </div>

            <!-- FILTRES & RECHERCHE -->
            <form method="GET" action="historique.php">
                <div class="toolbar">
                    <div class="filter-tabs">
                        <button type="submit" name="filtre" value="tous" class="tab <?= $filtre === 'tous' ? 'active' : '' ?>">
                            <i class="fas fa-list"></i> Toutes
                        </button>
                        <button type="submit" name="filtre" value="en_attente" class="tab <?= $filtre === 'en_attente' ? 'active' : '' ?>">
                            <i class="fas fa-clock"></i> En attente
                        </button>
                        <button type="submit" name="filtre" value="attribuee" class="tab <?= $filtre === 'attribuee' ? 'active' : '' ?>">
                            <i class="fas fa-car"></i> Attribuées
                        </button>
                        <button type="submit" name="filtre" value="terminee" class="tab <?= $filtre === 'terminee' ? 'active' : '' ?>">
                            <i class="fas fa-check-circle"></i> Terminées
                        </button>
                    </div>
                    
                    <div class="search-wrap">
                        <input type="search" name="q" class="search-box" 
                               placeholder="Rechercher adresse, chauffeur..." 
                               value="<?= htmlspecialchars($recherche) ?>">
                        <select name="tri" class="select-tri" onchange="this.form.submit()">
                            <option value="date_desc" <?= $tri === 'date_desc' ? 'selected' : '' ?>>Plus récentes</option>
                            <option value="date_asc" <?= $tri === 'date_asc' ? 'selected' : '' ?>>Plus anciennes</option>
                            <option value="prix_desc" <?= $tri === 'prix_desc' ? 'selected' : '' ?>>Prix ↓</option>
                            <option value="prix_asc" <?= $tri === 'prix_asc' ? 'selected' : '' ?>>Prix ↑</option>
                        </select>
                        <input type="hidden" name="filtre" value="<?= htmlspecialchars($filtre) ?>">
                    </div>
                </div>
            </form>

            <!-- LISTE DES RÉSERVATIONS -->
            <?php if (empty($reservations)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📋</div>
                    <h3>Aucune réservation trouvée</h3>
                    <p>Modifiez vos filtres de recherche ou vérifiez qu'il existe des données dans la base.</p>
                </div>
            <?php else: ?>
                <div class="reservations-grid">
                    <?php foreach ($reservations as $i => $resa): ?>
                        <div class="reservation-card">
                            <div class="card-header">
                                <div class="route-info">
                                    <div class="route-start">
                                        <div class="route-dot"></div>
                                        <strong><?= htmlspecialchars($resa['adresse_depart']) ?></strong>
                                    </div>
                                    <div class="route-end">
                                        <div class="route-dot"></div>
                                        → <?= htmlspecialchars($resa['adresse_arrivee']) ?>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 1.8rem; font-weight: 700; color: var(--gray-800); margin-bottom: 0.5rem;">
                                        <?= number_format($resa['prix_estime'], 2) ?>€
                                    </div>
                                    <span class="badge <?= statut_class($resa['statut']) ?>">
                                        <?= statut_label($resa['statut']) ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="card-meta">
                                <?php if ($resa['duree_estimee_minutes']): ?>
                                    <span class="meta-item">
                                        <i class="fas fa-clock"></i> <?= $resa['duree_estimee_minutes'] ?> min
                                    </span>
                                <?php endif; ?>
                                <?php if ($resa['distance_estimee_km']): ?>
                                    <span class="meta-item">
                                        <i class="fas fa-route"></i> <?= number_format($resa['distance_estimee_km'], 1) ?> km
                                    </span>
                                <?php endif; ?>
                                <?php if ($resa['vehicule_type']): ?>
                                    <span class="meta-item">
                                        <i class="fas fa-car"></i> <?= htmlspecialchars($resa['vehicule_type']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($resa['note_client']): ?>
                                    <span class="meta-item">
                                        <i class="fas fa-star"></i> <?= etoiles($resa['note_client']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-footer">
                                <div style="color: #6b7280;">
                                    <i class="fas fa-calendar"></i> 
                                    <?= date('d/m/Y H:i', strtotime($resa['date_creation'])) ?>
                                    <?php if ($resa['chauffeur_nom']): ?>
                                        • Chauffeur: <?= htmlspecialchars($resa['chauffeur_nom']) ?>
                                    <?php endif; ?>
                                </div>
                                <a href="detail_reservation.php?id=<?= $resa['id'] ?>" 
                                   class="page-btn" style="padding: 0.75rem 1.5rem;">
                                    <i class="fas fa-eye"></i> Détails
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- PAGINATION -->
                <?php if ($total_pages > 1): ?>
                    <nav class="pagination">
                        <?php
                        $base_url = '?filtre=' . urlencode($filtre) . '&q=' . urlencode($recherche) . '&tri=' . urlencode($tri);
                        ?>
                        <a href="<?= $base_url ?>&page=<?= max(1, $page-1) ?>" 
                           class="page-btn <?= $page <= 1 ? 'active' : '' ?>"
                           <?= $page <= 1 ? 'style="opacity:0.5; cursor:not-allowed;"' : '' ?>>
                            ‹
                        </a>
                        
                        <?php 
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        if ($start > 1): ?>
                            <a href="<?= $base_url ?>&page=1" class="page-btn">1</a>
                            <?php if ($start > 2): ?>
                                <span class="page-dots">…</span>
                            <?php endif;
                        endif;
                        
                        for ($p = $start; $p <= $end; $p++): ?>
                            <a href="<?= $base_url ?>&page=<?= $p ?>" 
                               class="page-btn <?= $p === $page ? 'active' : '' ?>">
                                <?= $p ?>
                            </a>
                        <?php endfor;
                        
                        if ($end < $total_pages): ?>
                            <?php if ($end < $total_pages - 1): ?>
                                <span class="page-dots">…</span>
                            <?php endif; ?>
                            <a href="<?= $base_url ?>&page=<?= $total_pages ?>" class="page-btn"><?= $total_pages ?></a>
                        <?php endif; ?>
                        
                        <a href="<?= $base_url ?>&page=<?= min($total_pages, $page+1) ?>" 
                           class="page-btn <?= $page >= $total_pages ? 'active' : '' ?>"
                           <?= $page >= $total_pages ? 'style="opacity:0.5; cursor:not-allowed;"' : '' ?>>
                        </a>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
