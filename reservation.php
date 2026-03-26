<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();

$message     = null;
$erreur      = null;
$type_course = $_GET['type'] ?? 'simple';
$users_id    = (int)$_SESSION['users_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $type = $_POST['type'] ?? 'simple';

    // ✅ Lecture des champs selon la section active (noms uniques par section)
    if ($type === 'simple') {
        $depart    = trim($_POST['simple_depart']   ?? '');
        $arrivee   = trim($_POST['simple_arrivee']  ?? '');
        $passagers = (int)($_POST['simple_passagers'] ?? 1);
        $bagages   = (int)($_POST['simple_bagages']   ?? 0);
    } elseif ($type === 'roundtrip') {
        $depart    = trim($_POST['roundtrip_depart']   ?? '');
        $arrivee   = trim($_POST['roundtrip_arrivee']  ?? '');
        $passagers = (int)($_POST['roundtrip_passagers'] ?? 1);
        $bagages   = (int)($_POST['roundtrip_bagages']   ?? 0);
    } elseif ($type === 'programme') {
        $depart    = trim($_POST['programme_depart']   ?? '');
        $arrivee   = trim($_POST['programme_arrivee']  ?? '');
        $passagers = (int)($_POST['programme_passagers'] ?? 1);
        $bagages   = (int)($_POST['programme_bagages']   ?? 0);
    } else {
        $depart = $arrivee = '';
        $passagers = 1;
        $bagages = 0;
    }

    // ✅ Validation après avoir lu les bons champs
    if (empty($depart) || empty($arrivee)) {
        $erreur = "Les adresses départ et arrivée sont obligatoires.";
    } else {
        try {
            $sql = "
                INSERT INTO Reservation 
                    (date_heure_reservation, adresse_depart, adresse_arrivee,
                     nb_passager, statut, id_client, id_chauffeur, id_vehicule, id_tarifs, users_id, created_at)
                VALUES (?, ?, ?, ?, 'EN_ATTENTE', NULL, NULL, NULL, NULL, ?, NOW())
            ";

            if ($type === 'simple') {
                $date       = $_POST['simple_date']  ?? date('Y-m-d');
                $heure      = $_POST['simple_heure'] ?? '00:00';
                $date_heure = $date . ' ' . $heure . ':00';

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$date_heure, $depart, $arrivee, $passagers, $users_id]);

            } elseif ($type === 'roundtrip') {
                $datetime_aller  = ($_POST['roundtrip_date_aller']  ?? '') . ' ' . ($_POST['roundtrip_heure_aller']  ?? '00:00') . ':00';
                $datetime_retour = ($_POST['roundtrip_date_retour'] ?? '') . ' ' . ($_POST['roundtrip_heure_retour'] ?? '00:00') . ':00';

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$datetime_aller,  $depart,  $arrivee, $passagers, $users_id]);
                $stmt->execute([$datetime_retour, $arrivee, $depart,  $passagers, $users_id]);

            } elseif ($type === 'programme') {
                $date_heure = ($_POST['programme_date'] ?? '') . ' ' . ($_POST['programme_heure'] ?? '00:00') . ':00';

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$date_heure, $depart, $arrivee, $passagers, $users_id]);
            }

            header('Location: dashboard_client.php?reservation=ok');
            exit();

        } catch (PDOException $e) {
            $erreur = "Erreur technique : " . $e->getMessage();
            error_log("Reservation error: " . $e->getMessage());
        }
    }
}

// ✅ Récupérer réservations AVANT le HTML
try {
    $stmt = $pdo->prepare("
        SELECT id_reservation, date_heure_reservation, adresse_depart, adresse_arrivee,
               nb_passager, statut, created_at
        FROM Reservation
        WHERE users_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$users_id]);
    $mes_reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mes_reservations = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>A4 Taxi - Réservation</title>
    <link rel="stylesheet" href="Asset/CSS/reservation.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #667eea; --primary-dark: #5a67d8; --success: #48bb78; --danger: #f56565; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; margin: 0; background: linear-gradient(135deg, #0a0e27, #1a1f3a); min-height: 100vh; }
        .header { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 1rem 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .logo { display: flex; align-items: center; text-decoration: none; color: #333; font-weight: bold; font-size: 1.5rem; gap: 0.5rem; }
        .nav-tabs { display: flex; background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .nav-tab { flex: 1; padding: 1rem; border: none; background: none; cursor: pointer; font-size: 1rem; transition: all 0.3s; }
        .nav-tab.active { background: var(--primary); color: white; }
        .reservation-form { padding: 2rem; max-width: 800px; margin: 0 auto; }
        .error-banner { background: var(--danger); color: white; padding: 1rem 1.5rem; border-radius: 10px; margin: 1rem 0; }
        .form-container { background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .input-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333; }
        .input-wrapper { position: relative; }
        .input-wrapper i { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #999; }
        input, select { width: 100%; padding: 1rem 1rem 1rem 3rem; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 1rem; box-sizing: border-box; transition: border-color 0.3s; }
        input:focus, select:focus { outline: none; border-color: var(--primary); }
        .reserve-btn { width: 100%; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border: none; padding: 1.2rem; border-radius: 10px; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: transform 0.2s; }
        .reserve-btn:hover { transform: translateY(-2px); }
        .form-section { display: none; }
        .form-section.active { display: block; animation: fadeIn 0.3s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 600px) { .row-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<header class="header">
    <div class="container">
        <a href="index.php" class="logo">🚖 A4 Taxi</a>
    </div>
</header>

<nav class="nav-tabs">
    <button class="nav-tab <?= $type_course==='simple'    ? 'active' : '' ?>" onclick="switchTab('simple', event)">
        <i class="fas fa-map-marker-alt"></i> Course simple
    </button>
    <button class="nav-tab <?= $type_course==='roundtrip' ? 'active' : '' ?>" onclick="switchTab('roundtrip', event)">
        <i class="fas fa-exchange-alt"></i> Aller-retour
    </button>
    <button class="nav-tab <?= $type_course==='programme' ? 'active' : '' ?>" onclick="switchTab('programme', event)">
        <i class="fas fa-clock"></i> Programmée
    </button>
</nav>

<main class="reservation-form">

    <?php if ($erreur): ?>
        <div class="error-banner">
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($erreur) ?>
        </div>
    <?php endif; ?>

    <!-- ✅ FORMULAIRE — chaque champ a un name unique par section -->
    <form method="POST" class="form-container" id="reservationForm">
        <input type="hidden" name="type" value="<?= htmlspecialchars($type_course) ?>" id="type_input">

        <!-- ==================== COURSE SIMPLE ==================== -->
        <div id="simple" class="form-section <?= $type_course==='simple' ? 'active' : '' ?>">
            <div class="input-group">
                <label>Adresse de départ *</label>
                <div class="input-wrapper">
                    <i class="fas fa-map-pin"></i>
                    <input type="text" name="simple_depart" placeholder="Ex: Gare d'Arras">
                </div>
            </div>
            <div class="input-group">
                <label>Adresse d'arrivée *</label>
                <div class="input-wrapper">
                    <i class="fas fa-flag-checkered"></i>
                    <input type="text" name="simple_arrivee" placeholder="Ex: Hôpital d'Arras">
                </div>
            </div>
            <div class="row-2">
                <div class="input-group">
                    <label>Date *</label>
                    <div class="input-wrapper">
                        <i class="fas fa-calendar"></i>
                        <input type="date" name="simple_date" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="input-group">
                    <label>Heure de départ *</label>
                    <div class="input-wrapper">
                        <i class="fas fa-clock"></i>
                        <input type="time" name="simple_heure">
                    </div>
                </div>
            </div>
            <div class="row-2">
                <div class="input-group">
                    <label>Passagers</label>
                    <div class="input-wrapper">
                        <i class="fas fa-users"></i>
                        <select name="simple_passagers">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="input-group">
                    <label>Bagages</label>
                    <div class="input-wrapper">
                        <i class="fas fa-suitcase"></i>
                        <select name="simple_bagages">
                            <?php for($i=0; $i<=5; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>
            <button type="submit" class="reserve-btn">
                <i class="fas fa-car"></i> Réserver maintenant
            </button>
        </div>

        <!-- ==================== ALLER-RETOUR ==================== -->
        <div id="roundtrip" class="form-section <?= $type_course==='roundtrip' ? 'active' : '' ?>">
            <div class="input-group">
                <label>Adresse de départ *</label>
                <div class="input-wrapper">
                    <i class="fas fa-map-pin"></i>
                    <input type="text" name="roundtrip_depart" placeholder="Ex: Gare d'Arras">
                </div>
            </div>
            <div class="input-group">
                <label>Adresse d'arrivée *</label>
                <div class="input-wrapper">
                    <i class="fas fa-flag-checkered"></i>
                    <input type="text" name="roundtrip_arrivee" placeholder="Ex: Lille">
                </div>
            </div>
            <div class="row-2">
                <div class="input-group">
                    <label>Date aller *</label>
                    <div class="input-wrapper">
                        <i class="fas fa-calendar"></i>
                        <input type="date" name="roundtrip_date_aller">
                    </div>
                </div>
                <div class="input-group">
                    <label>Heure aller *</label>
                    <div class="input-wrapper">
                        <i class="fas fa-clock"></i>
                        <input type="time" name="roundtrip_heure_aller">
                    </div>
                </div>
            </div>
            <div class="row-2">
                <div class="input-group">
                    <label>Date retour *</label>
                    <div class="input-wrapper">
                        <i class="fas fa-calendar"></i>
                        <input type="date" name="roundtrip_date_retour">
                    </div>
                </div>
                <div class="input-group">
                    <label>Heure retour *</label>
                    <div class="input-wrapper">
                        <i class="fas fa-clock"></i>
                        <input type="time" name="roundtrip_heure_retour">
                    </div>
                </div>
            </div>
            <div class="row-2">
                <div class="input-group">
                    <label>Passagers</label>
                    <div class="input-wrapper">
                        <i class="fas fa-users"></i>
                        <select name="roundtrip_passagers">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="input-group">
                    <label>Bagages</label>
                    <div class="input-wrapper">
                        <i class="fas fa-suitcase"></i>
                        <select name="roundtrip_bagages">
                            <?php for($i=0; $i<=5; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>
            <button type="submit" class="reserve-btn">
                <i class="fas fa-exchange-alt"></i> Réserver Aller-Retour
            </button>
        </div>

        <!-- ==================== PROGRAMMÉE ==================== -->
        <div id="programme" class="form-section <?= $type_course==='programme' ? 'active' : '' ?>">
            <div class="input-group">
                <label>Adresse de départ *</label>
                <div class="input-wrapper">
                    <i class="fas fa-map-pin"></i>
                    <input type="text" name="programme_depart" placeholder="Ex: 5bis Bd Strasbourg, Arras">
                </div>
            </div>
            <div class="input-group">
                <label>Adresse d'arrivée *</label>
                <div class="input-wrapper">
                    <i class="fas fa-flag-checkered"></i>
                    <input type="text" name="programme_arrivee" placeholder="Ex: Aéroport Lesquin">
                </div>
            </div>
            <div class="row-2">
                <div class="input-group">
                    <label>Date *</label>
                    <div class="input-wrapper">
                        <i class="fas fa-calendar"></i>
                        <input type="date" name="programme_date">
                    </div>
                </div>
                <div class="input-group">
                    <label>Heure *</label>
                    <div class="input-wrapper">
                        <i class="fas fa-clock"></i>
                        <input type="time" name="programme_heure">
                    </div>
                </div>
            </div>
            <div class="row-2">
                <div class="input-group">
                    <label>Passagers</label>
                    <div class="input-wrapper">
                        <i class="fas fa-users"></i>
                        <select name="programme_passagers">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="input-group">
                    <label>Bagages</label>
                    <div class="input-wrapper">
                        <i class="fas fa-suitcase"></i>
                        <select name="programme_bagages">
                            <?php for($i=0; $i<=5; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>
            <button type="submit" class="reserve-btn">
                <i class="fas fa-calendar-check"></i> Programmer la course
            </button>
        </div>

    </form>

</main>

<script>
    function switchTab(tabName, event) {
        // ✅ Mise à jour des onglets
        document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
        event.target.closest('.nav-tab').classList.add('active');

        // ✅ Affichage de la bonne section
        document.querySelectorAll('.form-section').forEach(s => s.classList.remove('active'));
        document.getElementById(tabName).classList.add('active');

        // ✅ Mise à jour du champ hidden type
        document.getElementById('type_input').value = tabName;

        // ✅ URL propre
        window.history.pushState({}, '', `?type=${tabName}`);
    }
</script>
</body>
</html>