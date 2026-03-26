<?php
require_once 'config.php';
require_once 'auth.php';

requireLogin();
requireRole(['client']);

// Gérer déconnexion
if (isset($_GET['logout'])) {
    logout();
}

$users_id = $_SESSION['users_id'];

// Récupérer réservations client
try {
    $stmt = $pdo->prepare("
        SELECT r.*, u.nom as chauffeur_nom, u.immatriculation, u.telephone as chauffeur_tel
        FROM Reservations r
        LEFT JOIN users u ON r.chauffeur_id = u.id
        WHERE r.client_id = ?
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$users_id]);
    $reservations = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Reservations WHERE client_id = ?");
    $stmt->execute([$users_id]);
    $total_reservations = $stmt->fetchColumn();
} catch (PDOException $e) {
    $reservations = [];
    $total_reservations = 0;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Client - A4 Taxi</title>
    <link rel="stylesheet" href="Asset/CSS/dashboard_client.css">
    <link rel="stylesheet" href="Asset/CSS/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Styles intégrés pour cohérence */
        .statut {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .statut.EN_ATTENTE {
            background: #fef3c7;
            color: #d97706;
        }

        .statut.ATTRIBUEE {
            background: #dbeafe;
            color: #1e40af;
        }

        .statut.ACCEPTEE {
            background: #dcfce7;
            color: #166534;
        }

        .statut.EN_COURS {
            background: #fef2f2;
            color: #dc2626;
        }

        .statut.TERMINE {
            background: #f0fdf4;
            color: #15803d;
        }

        .reservation-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border-left: 4px solid #3b82f6;
        }

        .resa-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .resa-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.5rem;
            font-size: 0.95rem;
            color: #64748b;
        }
    </style>
</head>

<body>
    <div class="layout">
        <!-- SIDEBAR CLIENT -->
        <aside class="sidebar">
            <div class="sidebar-logo">
                <span class="logo-icon">🚖</span>
                <span class="logo-text">A4 Taxi Client</span>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard_client.php" class="nav-link active">
                    <i class="fas fa-home"></i> <span>Dashboard</span>
                </a>
                <a href="estimation.php" class="nav-link">
                    <i class="fas fa-calculator"></i> <span>Estimation</span>
                </a>
                <a href="reservation.php" class="nav-link">
                    <i class="fas fa-plus-circle"></i> <span>Nouvelle réservation</span>
                </a>

                <a href="client-profile.php" class="nav-link">
                    <i class="fas fa-user"></i> <span>Profil</span>
                </a>
                <a href="?logout=1" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i> <span>Déconnexion</span>
                </a>
            </nav>
        </aside>

        <!-- CONTENU PRINCIPAL -->
        <main class="main-area">
            <?php if (isset($_GET['reservation']) && $_GET['reservation'] === 'ok'): ?>
                <div style="
        background: linear-gradient(135deg, #dcfce7, #bbf7d0);
        color: #166534;
        border-left: 5px solid #22c55e;
        padding: 1.25rem 1.75rem;
        border-radius: 12px;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: 600;
        font-size: 1.1rem;
    ">
                    <i class="fas fa-check-circle" style="font-size:1.5rem;"></i>
                    ✅ Réservation effectuée avec succès ! Un chauffeur vous contactera sous peu.
                </div>
            <?php endif; ?>
            <!-- Header -->
            <header class="page-header" style="padding: 2rem 0;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1 style="color: #1e40af; font-size: 2.2rem; margin: 0;">Bonjour, <?= htmlspecialchars($_SESSION['nom']) ?> !</h1>
                        <p style="color: #6b7280; margin: 0.5rem 0 0 0; font-size: 1.1rem;">Votre espace client A4 Taxi</p>
                    </div>
                    <div style="text-align: right;">
                        <span style="background: #dbeafe; color: #1e40af; padding: 0.5rem 1rem; border-radius: 25px; font-weight: 600;">
                            <i class="fas fa-user"></i> CLIENT
                        </span>
                    </div>
                </div>
            </header>

            <!-- STATS CENTRÉES -->
            <div class="stats-grid" style="
                display: grid; 
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
                gap: 2rem; 
                margin: 2rem auto 3rem; 
                max-width: 1000px;
            ">
                <div class="stat-card" style="
                    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
                    color: white; padding: 2rem; border-radius: 20px; text-align: center;
                ">
                    <i class="fas fa-list-check" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.9;"></i>
                    <h3 style="margin: 0 0 1rem 0; font-size: 1rem; opacity: 0.9;">Total réservations</h3>
                    <p style="font-size: 2.8rem; font-weight: 800; margin: 0;"><?= $total_reservations ?></p>
                    </