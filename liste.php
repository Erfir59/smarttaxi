<?php
require_once 'config.php';
require_once 'auth.php';

requireLogin();
requireRole(['chauffeur', 'admin']);

// ✅ is_admin strictement défini
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

$msg = '';

// ✅ BLOCAGE BACK-END : toute action POST d'un non-admin est rejetée immédiatement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$is_admin) {
        header('Location: liste.php?msg=' . urlencode("❌ Action réservée à l'administrateur."));
        exit();
    }

    $resa_id = (int)($_POST['resa_id'] ?? 0);

    try {
        if (isset($_POST['assigner_chauffeur'])) {
            $id_chauffeur = (int)$_POST['chauffeur_id'];
            $stmt = $pdo->prepare("
                UPDATE Reservation
                SET id_chauffeur = ?, statut = 'CONFIRMEE'
                WHERE id_reservation = ? AND statut = 'EN_ATTENTE'
            ");
            $stmt->execute([$id_chauffeur, $resa_id]);
            $msg = "✅ Course attribuée au chauffeur !";

        } elseif (isset($_POST['annuler'])) {
            $stmt = $pdo->prepare("
                UPDATE Reservation
                SET statut = 'ANNULEE', id_chauffeur = NULL
                WHERE id_reservation = ?
            ");
            $stmt->execute([$resa_id]);
            $msg = "✅ Course annulée !";

        } elseif (isset($_POST['remettre_attente'])) {
            $stmt = $pdo->prepare("
                UPDATE Reservation
                SET statut = 'EN_ATTENTE', id_chauffeur = NULL
                WHERE id_reservation = ?
            ");
            $stmt->execute([$resa_id]);
            $msg = "✅ Course remise en attente !";
        }

        header('Location: liste.php?msg=' . urlencode($msg));
        exit();

    } catch (PDOException $e) {
        $msg = "❌ Erreur: " . $e->getMessage();
    }
}

// REQUÊTES
try {
    // Courses en attente + annulées
    $stmt = $pdo->prepare("
        SELECT
            r.id_reservation                AS id,
            r.adresse_depart                AS depart,
            r.adresse_arrivee               AS arrivee,
            DATE(r.date_heure_reservation)  AS date_resa,
            TIME(r.date_heure_reservation)  AS heure_resa,
            r.nb_passager                   AS passagers,
            r.statut,
            r.id_chauffeur,
            c.nom_client                    AS client_nom,
            c.prenom_client                 AS client_prenom,
            c.telephone_client              AS client_tel,
            e.nom_employe                   AS chauffeur_nom,
            e.prenom_employe                AS chauffeur_prenom
        FROM Reservation r
        LEFT JOIN client  c ON r.id_client    = c.id_client
        LEFT JOIN employe e ON r.id_chauffeur = e.id_employe
        WHERE r.statut IN ('EN_ATTENTE', 'ANNULEE')
        ORDER BY r.date_heure_reservation DESC
    ");
    $stmt->execute();
    $courses_attente = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Toutes les courses
    $stmt = $pdo->prepare("
        SELECT
            r.id_reservation                AS id,
            r.adresse_depart                AS depart,
            r.adresse_arrivee               AS arrivee,
            DATE(r.date_heure_reservation)  AS date_resa,
            TIME(r.date_heure_reservation)  AS heure_resa,
            r.nb_passager                   AS passagers,
            r.statut,
            r.id_chauffeur,
            c.nom_client                    AS client_nom,
            c.prenom_client                 AS client_prenom,
            c.telephone_client              AS client_tel,
            e.nom_employe                   AS chauffeur_nom,
            e.prenom_employe                AS chauffeur_prenom
        FROM Reservation r
        LEFT JOIN client  c ON r.id_client    = c.id_client
        LEFT JOIN employe e ON r.id_chauffeur = e.id_employe
        ORDER BY r.date_heure_reservation DESC
    ");
    $stmt->execute();
    $toutes_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ✅ Chauffeurs disponibles — chargés UNIQUEMENT pour l'admin
    $chauffeurs = [];
    if ($is_admin) {
        $stmt = $pdo->prepare("
            SELECT
                e.id_employe        AS id,
                e.nom_employe       AS nom,
                e.prenom_employe    AS prenom,
                e.telephone_employe AS telephone,
                v.immatriculation,
                v.marque            AS vehicule
            FROM employe e
            JOIN type_employe te ON e.id_type_employe = te.id_type_employe
            LEFT JOIN Vehicule v ON v.id_employe = e.id_employe
            WHERE te.libelle_type_employe = 'Chauffeur'
              AND e.statut_activite = 'ACTIF'
            ORDER BY e.nom_employe, e.prenom_employe ASC
        ");
        $stmt->execute();
        $chauffeurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    die("Erreur DB: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion Courses</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="Asset/CSS/liste.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .select-chauffeur {
            width: 100%; padding: 12px 16px;
            border: 2px solid #e5e7eb; border-radius: 12px;
            font-size: 1rem; background: white;
            transition: all 0.3s ease; margin-bottom: 12px;
        }
        .select-chauffeur:focus {
            outline: none; border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59,130,246,0.1);
        }
        .btn-assign {
            width: 100%; padding: 12px 20px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white; border: none; border-radius: 12px;
            font-weight: 700; font-size: 0.95rem; cursor: pointer;
            transition: all 0.3s ease; text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-assign:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(16,185,129,0.4); }
        .btn-annuler, .btn-attente {
            width: 100%; padding: 10px 16px; margin-top: 8px;
            border: none; border-radius: 10px; font-weight: 600;
            cursor: pointer; transition: all 0.3s;
        }
        .btn-annuler { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        .btn-attente { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .btn-annuler:hover, .btn-attente:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(0,0,0,0.2); }

        /* ✅ Bloc verrou chauffeur */
        .lock-block {
            display: flex; flex-direction: column; align-items: center;
            justify-content: center; gap: 0.5rem;
            padding: 1.2rem 1rem;
            background: #f8fafc; border: 2px dashed #e2e8f0;
            border-radius: 12px; text-align: center;
            color: #94a3b8; cursor: not-allowed;
        }
        .lock-block i { font-size: 1.6rem; color: #cbd5e1; }
        .lock-block span { font-size: 0.82rem; font-weight: 600; color: #94a3b8; }

        /* ✅ Bannière info chauffeur */
        .chauffeur-banner {
            display: flex; align-items: center; gap: 0.75rem;
            background: #f0f9ff; border: 1px solid #bae6fd;
            border-radius: 10px; padding: 0.85rem 1.1rem;
            margin-bottom: 1.25rem; font-size: 0.88rem; color: #0369a1;
        }
        .chauffeur-banner i { font-size: 1.1rem; flex-shrink: 0; }
    </style>
</head>
<body>

<header class="header">
    <div class="nav-top">
        <a href="<?= $is_admin ? 'dashboard_admin.php' : 'dashboard_chauffeur.php' ?>" class="nav-link">
            <i class="fas fa-gauge-high"></i> Dashboard
        </a>
        <div style="font-weight:700; font-size:1.1rem;">
            🚖 <?= $is_admin ? 'Admin' : 'Chauffeur' ?> A4 Taxi
        </div>
        <a href="login.php?logout=1" class="nav-link">
            <i class="fas fa-sign-out-alt"></i> Déconnexion
        </a>
    </div>
    <h1><i class="fas fa-list"></i> Gestion des Courses</h1>
</header>

<main class="main-area">

    <?php if (!empty($_GET['msg'])): ?>
        <div class="message">
            <i class="fas fa-info-circle"></i>
            <?= htmlspecialchars(urldecode($_GET['msg'])) ?>
        </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════
         COURSES EN ATTENTE / ANNULÉES
    ══════════════════════════════════════ -->
    <section class="section">
        <div class="section-title">
            <h2><i class="fas fa-clock"></i> Courses en attente / annulées</h2>
            <span class="badge"><?= count($courses_attente) ?></span>
        </div>

        <?php if (!$is_admin): ?>
        <!-- ✅ Bannière visible uniquement pour le chauffeur -->
        <div class="chauffeur-banner">
            <i class="fas fa-circle-info"></i>
            <span>L'attribution des courses est <strong>réservée à l'administrateur</strong>. Vous êtes en mode consultation.</span>
        </div>
        <?php endif; ?>

        <?php if (empty($courses_attente)): ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <p>Aucune course en attente.</p>
            </div>
        <?php else: ?>
            <?php foreach ($courses_attente as $c): ?>
            <div class="course-item">

                <!-- INFO COURSE -->
                <div>
                    <div class="course-route">
                        <?= htmlspecialchars($c['depart']) ?> → <?= htmlspecialchars($c['arrivee']) ?>
                    </div>
                    <div class="course-meta">
                        📅 <?= htmlspecialchars($c['date_resa']) ?> <?= htmlspecialchars($c['heure_resa']) ?>
                        &nbsp;|&nbsp; 👥 <?= (int)$c['passagers'] ?> pass.
                        <?php if (isset($c['prix_estime'])): ?>
                            &nbsp;|&nbsp; 💶 <?= number_format($c['prix_estime'], 2) ?> €
                        <?php endif; ?>
                    </div>
                    <div class="course-client">
                        👤 <?= htmlspecialchars(($c['client_prenom'] ?? '') . ' ' . ($c['client_nom'] ?? 'Inconnu')) ?>
                        <?php if ($c['client_tel']): ?>
                            &nbsp;|&nbsp; 📞 <?= htmlspecialchars($c['client_tel']) ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ID -->
                <div class="course-id">#<?= $c['id'] ?></div>

                <!-- STATUT -->
                <div>
                    <span class="statut-badge statut-<?= $c['statut'] ?>">
                        <?= str_replace('_', ' ', ucwords(strtolower($c['statut']))) ?>
                    </span>
                </div>

                <!-- ACTIONS -->
                <div class="actions">

                    <?php if ($is_admin): ?>

                        <!-- ✅ ADMIN : formulaire d'assignation -->
                        <?php if ($c['statut'] === 'EN_ATTENTE'): ?>
                        <form method="POST" style="display:flex; flex-direction:column; gap:8px;">
                            <input type="hidden" name="resa_id" value="<?= $c['id'] ?>">
                            <select name="chauffeur_id" class="select-chauffeur" required>
                                <option value="">🔧 Choisir un chauffeur</option>
                                <?php foreach ($chauffeurs as $ch): ?>
                                    <option value="<?= $ch['id'] ?>">
                                        <?= htmlspecialchars($ch['prenom'] . ' ' . $ch['nom']) ?>
                                        <?php if ($ch['immatriculation']): ?>
                                            (<?= htmlspecialchars($ch['vehicule'] ?? '') ?> - <?= htmlspecialchars($ch['immatriculation']) ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="assigner_chauffeur" class="btn-assign">
                                <i class="fas fa-car-side"></i> Attribuer
                            </button>
                        </form>
                        <?php endif; ?>

                        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:8px;">
                            <form method="POST" style="flex:1;">
                                <input type="hidden" name="resa_id" value="<?= $c['id'] ?>">
                                <button type="submit" name="annuler" class="btn-annuler"
                                        onclick="return confirm('Annuler cette course ?')">
                                    <i class="fas fa-times"></i> Annuler
                                </button>
                            </form>
                            <?php if ($c['statut'] === 'CONFIRMEE'): ?>
                            <form method="POST" style="flex:1;">
                                <input type="hidden" name="resa_id" value="<?= $c['id'] ?>">
                                <button type="submit" name="remettre_attente" class="btn-attente">
                                    <i class="fas fa-rotate-left"></i> En attente
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>

                    <?php else: ?>

                        <!-- 🔒 CHAUFFEUR : verrou visuel, aucun formulaire -->
                        <div class="lock-block">
                            <i class="fas fa-lock"></i>
                            <span>Réservé à l'admin</span>
                        </div>

                    <?php endif; ?>

                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <!-- ══════════════════════════════════════
         TOUTES LES COURSES
    ══════════════════════════════════════ -->
    <section class="section">
        <div class="section-title">
            <h2><i class="fas fa-table-list"></i> Toutes les courses</h2>
            <span class="badge"><?= count($toutes_courses) ?></span>
        </div>

        <?php if (empty($toutes_courses)): ?>
            <div class="empty-state">
                <i class="fas fa-road"></i>
                <p>Aucune course enregistrée.</p>
            </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Trajet</th>
                    <th>Date / Heure</th>
                    <th>Client</th>
                    <th>Chauffeur</th>
                    <th>Prix</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($toutes_courses as $c): ?>
                <tr>
                    <td>#<?= $c['id'] ?></td>
                    <td>
                        <strong><?= htmlspecialchars($c['depart']) ?></strong><br>
                        → <?= htmlspecialchars($c['arrivee']) ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($c['date_resa']) ?><br>
                        <?= htmlspecialchars($c['heure_resa']) ?>
                    </td>
                    <td>
                        <?= htmlspecialchars(($c['client_prenom'] ?? '') . ' ' . ($c['client_nom'] ?? '-')) ?><br>
                        <small><?= htmlspecialchars($c['client_tel'] ?? '') ?></small>
                    </td>
                    <td>
                        <?= $c['chauffeur_nom']
                            ? htmlspecialchars(($c['chauffeur_prenom'] ?? '') . ' ' . $c['chauffeur_nom'])
                            : '<span style="color:#d97706;">Non assigné</span>' ?>
                    </td>
                    <td><?= isset($c['prix_estime']) ? number_format($c['prix_estime'], 2) . ' €' : '—' ?></td>
                    <td>
                        <span class="statut-badge statut-<?= $c['statut'] ?>">
                            <?= str_replace('_', ' ', ucwords(strtolower($c['statut']))) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($is_admin): ?>
                            <!-- ✅ ADMIN : actions dans le tableau -->
                            <?php if ($c['statut'] === 'CONFIRMEE'): ?>
                                <form method="POST">
                                    <input type="hidden" name="resa_id" value="<?= $c['id'] ?>">
                                    <button type="submit" name="remettre_attente" class="btn-attente">
                                        <i class="fas fa-rotate-left"></i> En attente
                                    </button>
                                </form>
                            <?php elseif ($c['statut'] === 'EN_ATTENTE'): ?>
                                <form method="POST">
                                    <input type="hidden" name="resa_id" value="<?= $c['id'] ?>">
                                    <button type="submit" name="annuler" class="btn-annuler"
                                            onclick="return confirm('Annuler ?')">
                                        <i class="fas fa-times"></i> Annuler
                                    </button>
                                </form>
                            <?php else: ?>
                                <span style="color:#94a3b8;">—</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- 🔒 CHAUFFEUR : aucune action dans le tableau -->
                            <span style="color:#94a3b8; font-size:0.85rem;">
                                <i class="fas fa-lock"></i> Admin
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>

</main>
</body>
</html>