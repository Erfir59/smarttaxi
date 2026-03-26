<?php
// incidents.php - INCIDENTS + SUIVI GPS EN TEMPS RÉEL
require_once 'config.php';
require_once 'auth.php';

requireLogin();
requireRole(['admin', 'chauffeur']);

$is_admin = in_array($_SESSION['role'], ['admin', 'chauffeur']);

// ✅ TABLE GPS TRACKING
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS gps_tracking (
            id INT PRIMARY KEY AUTO_INCREMENT,
            chauffeur_id INT NOT NULL,
            reservation_id INT,
            latitude DECIMAL(10,8) NOT NULL,
            longitude DECIMAL(11,8) NOT NULL,
            vitesse DECIMAL(6,2) DEFAULT 0,
            `precision` INT DEFAULT 0,
            heading DECIMAL(6,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(chauffeur_id),
            INDEX(reservation_id),
            INDEX(created_at)
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS incidents (
            id INT PRIMARY KEY AUTO_INCREMENT,
            reservation_id INT NOT NULL,
            chauffeur_id INT,
            type_incident ENUM('accident', 'travaux', 'panne', 'retard', 'bagarre', 'autre') NOT NULL,
            description TEXT,
            latitude DECIMAL(10,8),
            longitude DECIMAL(11,8),
            severity ENUM('mineur', 'modere', 'majeur') DEFAULT 'mineur',
            statut ENUM('ouvert', 'en_cours', 'resolu') DEFAULT 'ouvert',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            resolved_at TIMESTAMP NULL,
            INDEX(reservation_id),
            INDEX(chauffeur_id),
            INDEX(statut)
        )
    ");
} catch (PDOException $e) {
    die("Erreur DB: " . $e->getMessage());
}

// ─────────────────────────────────────────
// API AJAX
// ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // Mise à jour position GPS d'un chauffeur
    if ($_POST['action'] === 'update_gps') {
        $chauffeur_id = (int)$_POST['chauffeur_id'];
        $resa_id      = $_POST['reservation_id'] ?: null;
        $lat          = (float)$_POST['lat'];
        $lng          = (float)$_POST['lng'];
        $vitesse      = (float)($_POST['vitesse']   ?? 0);
        $precision    = (int)  ($_POST['precision'] ?? 0);

        $stmt = $pdo->prepare("J
            INSERT INTO gps_tracking (chauffeur_id, reservation_id, latitude, longitude, vitesse, `precision`)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$chauffeur_id, $resa_id, $lat, $lng, $vitesse, $precision]);
        echo json_encode(['status' => 'OK']);
        exit();
    }

    // Récupérer toutes les positions live des chauffeurs (admin)
    if ($_POST['action'] === 'get_all_positions') {
        $stmt = $pdo->prepare("
            SELECT 
                gt.chauffeur_id,
                gt.latitude,
                gt.longitude,
                gt.vitesse,
                gt.created_at,
                e.nom_employe   AS nom,
                e.prenom_employe AS prenom,
                r.adresse_depart  AS depart,
                r.adresse_arrivee AS arrivee
            FROM gps_tracking gt
            JOIN employe e ON gt.chauffeur_id = e.id_employe
            LEFT JOIN Reservation r ON gt.reservation_id = r.id_reservation
            WHERE gt.id IN (
                SELECT MAX(id) FROM gps_tracking GROUP BY chauffeur_id
            )
            AND gt.created_at >= NOW() - INTERVAL 5 MINUTE
        ");
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit();
    }

    // Récupérer incidents + dernière position GPS
    if ($_POST['action'] === 'get_incidents_gps') {
        $stmt = $pdo->prepare("
            SELECT 
                i.*,
                gt.latitude  AS gps_lat,
                gt.longitude AS gps_lng,
                gt.vitesse,
                r.adresse_depart  AS depart,
                r.adresse_arrivee AS arrivee,
                e.nom_employe     AS chauffeur_nom,
                e.prenom_employe  AS chauffeur_prenom
            FROM incidents i
            LEFT JOIN gps_tracking gt ON gt.chauffeur_id = i.chauffeur_id
                AND gt.id = (SELECT MAX(id) FROM gps_tracking gt2 WHERE gt2.chauffeur_id = i.chauffeur_id)
            LEFT JOIN Reservation r ON i.reservation_id = r.id_reservation
            LEFT JOIN employe e     ON i.chauffeur_id   = e.id_employe
            WHERE i.statut != 'resolu'
            ORDER BY i.created_at DESC
        ");
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit();
    }

    // Résoudre un incident (admin)
    if ($_POST['action'] === 'resoudre_incident' && $is_admin) {
        $id = (int)$_POST['incident_id'];
        $stmt = $pdo->prepare("UPDATE incidents SET statut='resolu', resolved_at=NOW() WHERE id=?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'OK']);
        exit();
    }

    echo json_encode(['status' => 'ERROR', 'msg' => 'Action inconnue']);
    exit();
}

// ─────────────────────────────────────────
// DONNÉES INITIALES
// ─────────────────────────────────────────
try {
    $stmt = $pdo->prepare("
        SELECT 
            i.*,
            gt.latitude  AS gps_lat,
            gt.longitude AS gps_lng,
            gt.vitesse,
            r.adresse_depart  AS depart,
            r.adresse_arrivee AS arrivee,
            e.nom_employe     AS chauffeur_nom,
            e.prenom_employe  AS chauffeur_prenom
        FROM incidents i
        LEFT JOIN gps_tracking gt ON gt.chauffeur_id = i.chauffeur_id
            AND gt.id = (SELECT MAX(id) FROM gps_tracking gt2 WHERE gt2.chauffeur_id = i.chauffeur_id)
        LEFT JOIN Reservation r ON i.reservation_id = r.id_reservation
        LEFT JOIN employe e     ON i.chauffeur_id   = e.id_employe
        WHERE i.statut != 'resolu'
        ORDER BY i.created_at DESC
    ");
    $stmt->execute();
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $incidents = [];
}

// Réservation en cours du chauffeur
$current_resa = null;
if (!$is_admin) {
    try {
        $stmt = $pdo->prepare("
            SELECT id_reservation AS id 
            FROM Reservation 
            WHERE id_chauffeur = ? AND statut = 'CONFIRMEE'
            ORDER BY date_heure_reservation DESC LIMIT 1
        ");
        $stmt->execute([$_SESSION['users_id'] ?? 0]);
        $current_resa = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Incidents GPS - A4 Taxi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- Leaflet Routing CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- CSS local -->
    <link rel="stylesheet" href="Asset/CSS/incidents.css">

    <style>
        /* ── LAYOUT ── */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f1f5f9; }

        .header { background: #1e293b; color: white; padding: 0; }
        .nav-top { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1.5rem; background: #0f172a; }
        .nav-link { color: #94a3b8; text-decoration: none; font-size: 0.9rem; transition: color .2s; }
        .nav-link:hover { color: white; }
        .header h1 { padding: 1rem 1.5rem; font-size: 1.4rem; border-top: 1px solid #334155; }

        .main-area { max-width: 1400px; margin: 0 auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1.5rem; }

        .section { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .section-title { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .section-title h2 { font-size: 1.1rem; color: #1e293b; }
        .badge { background: #3b82f6; color: white; border-radius: 20px; padding: 2px 10px; font-size: 0.8rem; }

        /* ── CARTE ── */
        #map { height: 480px; border-radius: 10px; border: 2px solid #e2e8f0; z-index: 1; }

        /* ── PANNEAU LÉGENDE ── */
        .map-controls { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; margin-top: 0.75rem; }
        .legend-item { display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; color: #475569; }
        .legend-dot { width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0; }

        /* ── GPS STATUS ── */
        .gps-panel { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
        .gps-status { padding: 0.4rem 1rem; border-radius: 20px; font-size: 0.85rem; font-weight: 600; transition: all .3s; }
        .gps-online  { background: #dcfce7; color: #166534; }
        .gps-offline { background: #fee2e2; color: #991b1b; }
        .gps-waiting { background: #fef3c7; color: #92400e; }
        .gps-info { font-size: 0.8rem; color: #64748b; }

        /* ── BOUTONS ── */
        .btn { padding: 0.5rem 1.1rem; border-radius: 8px; border: none; cursor: pointer; font-size: 0.9rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; transition: all .2s; }
        .btn-gps   { background: #10b981; color: white; }
        .btn-gps:hover   { background: #059669; }
        .btn-stop  { background: #ef4444; color: white; }
        .btn-stop:hover  { background: #dc2626; }
        .btn-resolve { background: #6366f1; color: white; font-size: 0.8rem; padding: 0.3rem 0.7rem; }
        .btn-resolve:hover { background: #4f46e5; }

        /* ── INCIDENTS LIST ── */
        .incident-item { display: grid; grid-template-columns: 1fr auto auto auto; gap: 1rem; align-items: center; padding: 1rem; border-radius: 10px; border-left: 4px solid #e2e8f0; margin-bottom: 0.75rem; background: #f8fafc; transition: background .2s; }
        .incident-item:hover { background: #f1f5f9; }
        .incident-majeur  { border-left-color: #ef4444; background: #fff5f5; }
        .incident-modere  { border-left-color: #f59e0b; background: #fffbeb; }
        .incident-mineur  { border-left-color: #3b82f6; background: #eff6ff; }

        .incident-route   { font-weight: 700; color: #1e293b; font-size: 0.95rem; }
        .incident-details { font-size: 0.82rem; color: #64748b; margin-top: 0.25rem; }
        .incident-gps     { font-size: 0.78rem; color: #94a3b8; margin-top: 0.2rem; font-family: monospace; }

        .severity-badge { padding: 0.25rem 0.7rem; border-radius: 12px; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; }
        .severity-majeur { background: #fee2e2; color: #dc2626; }
        .severity-modere { background: #fef3c7; color: #d97706; }
        .severity-mineur { background: #dbeafe; color: #2563eb; }

        .statut-badge { padding: 0.2rem 0.6rem; border-radius: 10px; font-size: 0.75rem; font-weight: 600; }
        .statut-ouvert   { background: #fee2e2; color: #dc2626; }
        .statut-en_cours { background: #fef3c7; color: #d97706; }

        .type-icon { font-size: 1.4rem; }

        .empty-state { text-align: center; padding: 2rem; color: #94a3b8; }
        .empty-state i { font-size: 2.5rem; margin-bottom: 0.5rem; display: block; }

        /* ── STATS MINI ── */
        .stats-row { display: flex; gap: 1rem; flex-wrap: wrap; }
        .stat-card { background: white; border-radius: 10px; padding: 1rem 1.5rem; flex: 1; min-width: 140px; box-shadow: 0 1px 4px rgba(0,0,0,.08); text-align: center; }
        .stat-card .stat-num { font-size: 2rem; font-weight: 800; }
        .stat-card .stat-label { font-size: 0.8rem; color: #64748b; margin-top: 0.2rem; }
        .stat-online .stat-num { color: #10b981; }
        .stat-warning .stat-num { color: #f59e0b; }
        .stat-danger .stat-num { color: #ef4444; }

        /* ── MARKER CUSTOM ── */
        .marker-chauffeur { background: transparent; border: none; }
        .marker-incident  { background: transparent; border: none; }
        .car-icon  { font-size: 22px; filter: drop-shadow(0 2px 4px rgba(0,0,0,.4)); }
        .inc-icon  { font-size: 20px; filter: drop-shadow(0 2px 4px rgba(0,0,0,.4)); }

        /* ── POPUP ── */
        .leaflet-popup-content { font-size: 0.9rem; line-height: 1.5; min-width: 180px; }
        .popup-title { font-weight: 700; color: #1e293b; margin-bottom: 0.3rem; }
        .popup-row { display: flex; gap: 0.4rem; align-items: center; color: #475569; font-size: 0.82rem; }

        @media (max-width: 768px) {
            .incident-item { grid-template-columns: 1fr; }
            #map { height: 320px; }
        }
    </style>
</head>
<body>

<header class="header">
    <div class="nav-top">
        <a href="<?= $is_admin ? 'dashboard_admin.php' : 'dashboard_chauffeur.php' ?>" class="nav-link">
            <i class="fas fa-gauge-high"></i> Dashboard
        </a>
        <div style="font-size: 1.1rem; font-weight: 700; color: white;">🚖 A4 Taxi</div>
        <a href="login.php?logout=1" class="nav-link"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
    </div>
    <h1><i class="fas fa-map-marker-alt"></i> Incidents &amp; Suivi GPS Live</h1>
</header>

<main class="main-area">

    <!-- ── STATS ── -->
    <div class="stats-row" id="statsRow">
        <div class="stat-card stat-online">
            <div class="stat-num" id="stat-online">0</div>
            <div class="stat-label">Chauffeurs en ligne</div>
        </div>
        <div class="stat-card stat-warning">
            <div class="stat-num"><?= count(array_filter($incidents, fn($i) => $i['severity'] === 'modere')) ?></div>
            <div class="stat-label">Incidents modérés</div>
        </div>
        <div class="stat-card stat-danger">
            <div class="stat-num"><?= count(array_filter($incidents, fn($i) => $i['severity'] === 'majeur')) ?></div>
            <div class="stat-label">Incidents majeurs</div>
        </div>
        <div class="stat-card">
            <div class="stat-num"><?= count($incidents) ?></div>
            <div class="stat-label">Total actifs</div>
        </div>
    </div>

    <!-- ── CARTE GPS ── -->
    <section class="section">
        <div class="section-title">
            <h2><i class="fas fa-map"></i> Carte en temps réel</h2>
            <div style="display:flex; gap:0.5rem; align-items:center;">
                <span style="font-size:0.8rem; color:#64748b;" id="last-update">–</span>
                <button class="btn" style="background:#f1f5f9; color:#475569; font-size:0.8rem;" onclick="refreshAll()">
                    <i class="fas fa-rotate"></i> Rafraîchir
                </button>
            </div>
        </div>

        <div id="map"></div>

        <!-- LÉGENDE -->
        <div class="map-controls">
            <div class="legend-item"><span class="legend-dot" style="background:#10b981"></span> Chauffeur actif</div>
            <div class="legend-item"><span class="legend-dot" style="background:#94a3b8"></span> Chauffeur inactif</div>
            <div class="legend-item"><span class="legend-dot" style="background:#ef4444"></span> Incident majeur</div>
            <div class="legend-item"><span class="legend-dot" style="background:#f59e0b"></span> Incident modéré</div>
            <div class="legend-item"><span class="legend-dot" style="background:#3b82f6"></span> Incident mineur</div>
        </div>
    </section>

    <!-- ── GPS TRACKER (chauffeur seulement) ── -->
    <?php if (!$is_admin): ?>
    <section class="section">
        <div class="section-title">
            <h2><i class="fas fa-satellite-dish"></i> Mon suivi GPS</h2>
        </div>
        <div class="gps-panel">
            <div id="gps-status" class="gps-status gps-offline">
                <i class="fas fa-circle-xmark"></i> GPS désactivé
            </div>
            <button class="btn btn-gps" id="btn-start-gps" onclick="startGPS()">
                <i class="fas fa-satellite"></i> Activer le GPS
            </button>
            <button class="btn btn-stop" id="btn-stop-gps" onclick="stopGPS()" style="display:none;">
                <i class="fas fa-stop"></i> Arrêter
            </button>
            <div class="gps-info" id="gps-info">Position non transmise</div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ── INCIDENTS ── -->
    <section class="section">
        <div class="section-title">
            <h2><i class="fas fa-triangle-exclamation"></i> Incidents actifs</h2>
            <span class="badge" id="badge-incidents"><?= count($incidents) ?></span>
        </div>

        <div id="incidents-list">
            <?php if (empty($incidents)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle" style="color:#10b981;"></i>
                    <p>Aucun incident actif — tout roule !</p>
                </div>
            <?php else: ?>
                <?php foreach ($incidents as $i): ?>
                <?php
                    $icons = ['accident'=>'💥','travaux'=>'🚧','panne'=>'🔧','retard'=>'⏱️','bagarre'=>'🚨','autre'=>'ℹ️'];
                    $icon = $icons[$i['type_incident']] ?? 'ℹ️';
                ?>
                <div class="incident-item incident-<?= htmlspecialchars($i['severity'] ?? 'mineur') ?>" id="inc-<?= $i['id'] ?>">
                    <div>
                        <div class="incident-route">
                            <?= $icon ?> <?= htmlspecialchars($i['depart'] ?? '—') ?> → <?= htmlspecialchars($i['arrivee'] ?? '—') ?>
                        </div>
                        <div class="incident-details">
                            👤 <?= htmlspecialchars(($i['chauffeur_prenom'] ?? '') . ' ' . ($i['chauffeur_nom'] ?? 'Inconnu')) ?>
                            &nbsp;·&nbsp; 🕐 <?= date('H:i', strtotime($i['created_at'])) ?>
                            <?php if ($i['description']): ?>
                                &nbsp;·&nbsp; <?= htmlspecialchars(mb_substr($i['description'], 0, 60)) ?>…
                            <?php endif; ?>
                        </div>
                        <div class="incident-gps">
                            <?php if ($i['gps_lat']): ?>
                                📍 <?= number_format($i['gps_lat'], 5) ?>, <?= number_format($i['gps_lng'], 5) ?>
                                &nbsp;·&nbsp; 🚦 <?= number_format($i['vitesse'] ?? 0, 1) ?> km/h
                                <a href="#" onclick="flyToIncident(<?= $i['gps_lat'] ?>, <?= $i['gps_lng'] ?>); return false;"
                                   style="color:#3b82f6; text-decoration:none; margin-left:0.4rem;">
                                    <i class="fas fa-location-crosshairs"></i> Voir sur carte
                                </a>
                            <?php else: ?>
                                📍 Position GPS non disponible
                            <?php endif; ?>
                        </div>
                    </div>

                    <span class="severity-badge severity-<?= htmlspecialchars($i['severity'] ?? 'mineur') ?>">
                        <?= ucfirst($i['severity'] ?? 'mineur') ?>
                    </span>

                    <span class="statut-badge statut-<?= $i['statut'] ?>">
                        <?= str_replace('_', ' ', ucfirst($i['statut'])) ?>
                    </span>

                    <div>
                        <?php if ($is_admin): ?>
                            <button class="btn btn-resolve" onclick="resoudreIncident(<?= $i['id'] ?>)">
                                <i class="fas fa-check"></i> Résoudre
                            </button>
                        <?php else: ?>
                            <span style="color:#94a3b8; font-size:0.8rem;">Signalé</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

</main>

<!-- ── LEAFLET JS ── -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// ═══════════════════════════════════════════════
// 1. INITIALISATION CARTE
// ═══════════════════════════════════════════════
const map = L.map('map', {
    center: [50.2929, 2.7773], // Arras (zone A4 Taxi)
    zoom: 12,
    zoomControl: true
});

// Fond de carte OpenStreetMap
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19
}).addTo(map);

// ═══════════════════════════════════════════════
// 2. ICÔNES PERSONNALISÉES
// ═══════════════════════════════════════════════
function makeDriverIcon(isActive) {
    return L.divIcon({
        className: 'marker-chauffeur',
        html: `<div style="
            background: ${isActive ? '#10b981' : '#94a3b8'};
            color: white;
            border-radius: 50%;
            width: 36px; height: 36px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,.35);
            border: 3px solid white;
        "><i class="fas fa-car"></i></div>`,
        iconSize: [36, 36],
        iconAnchor: [18, 18]
    });
}

function makeIncidentIcon(severity) {
    const colors = { majeur: '#ef4444', modere: '#f59e0b', mineur: '#3b82f6' };
    const color = colors[severity] || '#64748b';
    return L.divIcon({
        className: 'marker-incident',
        html: `<div style="
            background: ${color};
            color: white;
            border-radius: 8px;
            width: 34px; height: 34px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,.35);
            border: 3px solid white;
            animation: pulse 2s infinite;
        "><i class="fas fa-triangle-exclamation"></i></div>`,
        iconSize: [34, 34],
        iconAnchor: [17, 17]
    });
}

// Pulse animation CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0%   { box-shadow: 0 0 0 0 rgba(239,68,68,.5); }
        70%  { box-shadow: 0 0 0 10px rgba(239,68,68,0); }
        100% { box-shadow: 0 0 0 0 rgba(239,68,68,0); }
    }
`;
document.head.appendChild(style);

// Icône "ma position" (chauffeur)
function makeMyIcon() {
    return L.divIcon({
        className: 'marker-chauffeur',
        html: `<div style="
            background: #6366f1;
            color: white;
            border-radius: 50%;
            width: 40px; height: 40px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            box-shadow: 0 0 0 6px rgba(99,102,241,.25);
            border: 3px solid white;
        "><i class="fas fa-circle-dot"></i></div>`,
        iconSize: [40, 40],
        iconAnchor: [20, 20]
    });
}

// ═══════════════════════════════════════════════
// 3. STOCKAGE MARQUEURS
// ═══════════════════════════════════════════════
let driverMarkers   = {}; // chauffeur_id → marker
let incidentMarkers = {}; // incident.id → marker
let myMarker        = null;
let watchId         = null;

// ═══════════════════════════════════════════════
// 4. MISE À JOUR DES POSITIONS (ADMIN)
// ═══════════════════════════════════════════════
function refreshDrivers() {
    fetch('incidents.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_all_positions'
    })
    .then(r => r.json())
    .then(drivers => {
        document.getElementById('stat-online').textContent = drivers.length;

        drivers.forEach(d => {
            const lat  = parseFloat(d.latitude);
            const lng  = parseFloat(d.longitude);
            const name = `${d.prenom} ${d.nom}`;
            const popup = `
                <div class="popup-title"><i class="fas fa-car"></i> ${name}</div>
                <div class="popup-row"><i class="fas fa-tachometer-alt"></i> ${parseFloat(d.vitesse||0).toFixed(1)} km/h</div>
                ${d.depart ? `<div class="popup-row"><i class="fas fa-route"></i> ${d.depart} → ${d.arrivee}</div>` : ''}
                <div class="popup-row" style="color:#94a3b8; font-size:0.75rem;">${d.created_at}</div>
            `;

            if (driverMarkers[d.chauffeur_id]) {
                driverMarkers[d.chauffeur_id]
                    .setLatLng([lat, lng])
                    .setIcon(makeDriverIcon(true, parseFloat(d.heading || 0)))
                    .setPopupContent(popup);
            } else {
                driverMarkers[d.chauffeur_id] = L.marker([lat, lng], { icon: makeDriverIcon(true, parseFloat(d.heading || 0)) })
                    .addTo(map)
                    .bindPopup(popup);
            }
        });

        document.getElementById('last-update').textContent = 'Mis à jour : ' + new Date().toLocaleTimeString('fr-FR');
    })
    .catch(() => {});
}

// ═══════════════════════════════════════════════
// 5. MISE À JOUR DES INCIDENTS SUR CARTE
// ═══════════════════════════════════════════════
function refreshIncidents() {
    fetch('incidents.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_incidents_gps'
    })
    .then(r => r.json())
    .then(data => {
        data.forEach(inc => {
            const lat = parseFloat(inc.gps_lat);
            const lng = parseFloat(inc.gps_lng);
            if (!lat || !lng) return;

            const popup = `
                <div class="popup-title">⚠️ ${inc.type_incident.toUpperCase()}</div>
                <div class="popup-row"><i class="fas fa-route"></i> ${inc.depart||'?'} → ${inc.arrivee||'?'}</div>
                <div class="popup-row"><i class="fas fa-user"></i> ${inc.chauffeur_prenom||''} ${inc.chauffeur_nom||''}</div>
                <div class="popup-row"><i class="fas fa-tachometer-alt"></i> ${parseFloat(inc.vitesse||0).toFixed(1)} km/h</div>
                <div class="popup-row" style="margin-top:4px;">
                    <span style="background:${inc.severity==='majeur'?'#fee2e2':inc.severity==='modere'?'#fef3c7':'#dbeafe'};
                                 color:${inc.severity==='majeur'?'#dc2626':inc.severity==='modere'?'#d97706':'#2563eb'};
                                 padding:2px 8px; border-radius:8px; font-size:0.78rem; font-weight:700;">
                        ${inc.severity||'mineur'}
                    </span>
                </div>
            `;

            if (incidentMarkers[inc.id]) {
                incidentMarkers[inc.id]
                    .setLatLng([lat, lng])
                    .setPopupContent(popup);
            } else {
                incidentMarkers[inc.id] = L.marker([lat, lng], { icon: makeIncidentIcon(inc.severity || 'mineur') })
                    .addTo(map)
                    .bindPopup(popup);
            }
        });
    })
    .catch(() => {});
}

// ═══════════════════════════════════════════════
// 6. GPS CHAUFFEUR
// ═══════════════════════════════════════════════
<?php if (!$is_admin): ?>
const MY_CHAUFFEUR_ID = <?= json_encode($_SESSION['users_id'] ?? 0) ?>;
const CURRENT_RESA_ID = <?= json_encode($current_resa['id'] ?? null) ?>;

function startGPS() {
    if (!navigator.geolocation) {
        alert("Géolocalisation non supportée par ce navigateur.");
        return;
    }

    setGpsStatus('waiting', 'Recherche du signal GPS…');
    document.getElementById('btn-start-gps').style.display = 'none';
    document.getElementById('btn-stop-gps').style.display  = 'inline-flex';

    watchId = navigator.geolocation.watchPosition(
        position => {
            const lat     = position.coords.latitude;
            const lng     = position.coords.longitude;
            const speed   = position.coords.speed ? (position.coords.speed * 3.6).toFixed(1) : 0;
            const acc     = Math.round(position.coords.accuracy);

            // Envoyer position au serveur
            fetch('incidents.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=update_gps&chauffeur_id=${MY_CHAUFFEUR_ID}&reservation_id=${CURRENT_RESA_ID}&lat=${lat}&lng=${lng}&vitesse=${speed}&precision=${acc}`
            });

            // Mise à jour UI
            setGpsStatus('online', `GPS actif • ${speed} km/h • précision ${acc}m`);
            document.getElementById('gps-info').textContent = `📍 ${lat.toFixed(5)}, ${lng.toFixed(5)}`;

            // Marqueur sur la carte
            map.setView([lat, lng], 15);
            if (myMarker) {
                myMarker.setLatLng([lat, lng]);
            } else {
                myMarker = L.marker([lat, lng], { icon: makeMyIcon() })
                    .addTo(map)
                    .bindPopup('<b>Vous êtes ici</b>');
            }
        },
        error => {
            const msgs = { 1:'Accès refusé', 2:'Position indisponible', 3:'Délai dépassé' };
            setGpsStatus('offline', 'Erreur GPS : ' + (msgs[error.code] || 'inconnue'));
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 5000 }
    );
}

function stopGPS() {
    if (watchId !== null) {
        navigator.geolocation.clearWatch(watchId);
        watchId = null;
    }
    setGpsStatus('offline', 'GPS désactivé');
    document.getElementById('btn-start-gps').style.display = 'inline-flex';
    document.getElementById('btn-stop-gps').style.display  = 'none';
    document.getElementById('gps-info').textContent = 'Position non transmise';
}

function setGpsStatus(state, text) {
    const el = document.getElementById('gps-status');
    el.className = 'gps-status gps-' + state;
    const icons = { online: 'fa-circle-check', offline: 'fa-circle-xmark', waiting: 'fa-circle-notch fa-spin' };
    el.innerHTML = `<i class="fas ${icons[state]||'fa-circle'}"></i> ${text}`;
}
<?php endif; ?>

// ═══════════════════════════════════════════════
// 7. CENTRAGE SUR UN INCIDENT
// ═══════════════════════════════════════════════
function flyToIncident(lat, lng) {
    map.flyTo([lat, lng], 16, { duration: 1.2 });
    // Ouvrir le popup si marker existant
    for (const [id, marker] of Object.entries(incidentMarkers)) {
        const pos = marker.getLatLng();
        if (Math.abs(pos.lat - lat) < 0.0001 && Math.abs(pos.lng - lng) < 0.0001) {
            marker.openPopup();
        }
    }
}

// ═══════════════════════════════════════════════
// 8. RÉSOUDRE UN INCIDENT (ADMIN)
// ═══════════════════════════════════════════════
function resoudreIncident(incidentId) {
    if (!confirm('Marquer cet incident comme résolu ?')) return;

    fetch('incidents.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=resoudre_incident&incident_id=${incidentId}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'OK') {
            // Retirer de la liste
            const el = document.getElementById('inc-' + incidentId);
            if (el) el.remove();

            // Retirer le marqueur
            if (incidentMarkers[incidentId]) {
                map.removeLayer(incidentMarkers[incidentId]);
                delete incidentMarkers[incidentId];
            }

            // Mettre à jour le badge
            const badge = document.getElementById('badge-incidents');
            badge.textContent = parseInt(badge.textContent) - 1;
        }
    });
}

// ═══════════════════════════════════════════════
// 9. RAFRAÎCHISSEMENT GLOBAL
// ═══════════════════════════════════════════════
function refreshAll() {
    refreshDrivers();
    refreshIncidents();
}

// Démarrage
refreshAll();

// Auto-refresh toutes les 10 secondes
setInterval(refreshAll, 10000);
</script>

</body>
</html>