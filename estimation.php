<?php
require_once 'config.php';

$tarifs_2025 = [
    'prise_en_charge'    => 4.48,
    'km'                 => 1.29,
    'horaire'            => 41.76 / 60, // € par minute
    'minimum'            => 8.00,
    'bagages'            => 2.00,
    'passagers_sup5'     => 4.00,
    'reservation_immediate' => 2.00
];

// Valeurs POST conservées pour réaffichage
$depart   = trim($_POST['depart']    ?? '');
$arrivee  = trim($_POST['arrivee']   ?? '');
$passagers = max(1, (int)($_POST['passagers'] ?? 2));
$bagages   = max(0, (int)($_POST['bagages']   ?? 0));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>A4 Taxi - Estimation tarif 2025</title>

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <!-- CSS existant -->
    <link rel="stylesheet" href="Asset/CSS/estimation.css">

    <style>
        /* ── CARTE ─────────────────────────────────────────── */
        #map-wrap {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,.09);
            overflow: hidden;
            margin-bottom: 2rem;
            display: none; /* caché jusqu'au calcul */
        }
        #map-wrap.visible { display: block; }

        #map {
            height: 380px;
            width: 100%;
        }

        .map-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .85rem 1.25rem;
            background: #1f2937;
            color: white;
            font-size: .9rem;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .map-toolbar strong { color: #fbbf24; }
        .map-badge {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            background: rgba(255,255,255,.1);
            border-radius: 99px;
            padding: .3rem .85rem;
            font-size: .82rem;
        }

        /* ── LOADER ────────────────────────────────────────── */
        .loader-wrap {
            display: none;
            align-items: center;
            justify-content: center;
            gap: .75rem;
            padding: 1.25rem;
            color: #6b7280;
            font-size: .95rem;
        }
        .loader-wrap.visible { display: flex; }
        .spinner {
            width: 22px; height: 22px;
            border: 3px solid #e5e7eb;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin .7s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── RÉSULTAT ──────────────────────────────────────── */
        .resultat { margin-top: 1.5rem; }

        /* ── ERREUR ────────────────────────────────────────── */
        .error-box {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
            padding: 1rem 1.25rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            display: none;
        }
        .error-box.visible { display: block; }
    </style>
</head>
<body>

<header class="header">
    <div class="container">
        <a href="index.php" class="logo">
            <img src="Design-sans-titre.jpg" alt="A4 Taxi">
            <span>A4 Taxi Arras</span>
        </a>
    </div>
</header>

<div class="container">
    <section class="hero">
        <h1><i class="fas fa-euro-sign"></i> Estimez votre course</h1>
        <p>Tarifs 2025 officiels — Arrêté du 20 janvier 2025<br>
           <small>Prise en charge 4,48 € + 1,29 €/km + temps de parcours</small></p>
    </section>

    <!-- Formulaire -->
    <form id="form-estimation" class="estimation-form" onsubmit="calculer(event)">

        <div class="input-group">
            <label>Départ <span style="color:var(--danger)">*</span></label>
            <div class="input-wrapper">
                <i class="fas fa-map-pin"></i>
                <input type="text" id="depart" name="depart"
                       value="<?= htmlspecialchars($depart) ?>"
                       required placeholder="Gare d'Arras, Hôpital, Hôtel de Ville…">
            </div>
        </div>

        <div class="input-group">
            <label>Arrivée <span style="color:var(--danger)">*</span></label>
            <div class="input-wrapper">
                <i class="fas fa-flag-checkered"></i>
                <input type="text" id="arrivee" name="arrivee"
                       value="<?= htmlspecialchars($arrivee) ?>"
                       required placeholder="Aéroport Lesquin, Lille, CHU…">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div class="input-group">
                <label>Passagers</label>
                <div class="input-wrapper">
                    <i class="fas fa-users"></i>
                    <select id="passagers" name="passagers">
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                        <option value="<?= $i ?>" <?= $i === $passagers ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="input-group">
                <label>Bagages</label>
                <div class="input-wrapper">
                    <i class="fas fa-suitcase"></i>
                    <select id="bagages" name="bagages">
                        <?php for ($i = 0; $i <= 5; $i++): ?>
                        <option value="<?= $i ?>" <?= $i === $bagages ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
        </div>

        <button type="submit" class="btn-estimer">
            <i class="fas fa-calculator"></i> Calculer le tarif 2025
        </button>
    </form>

    <!-- Loader -->
    <div class="loader-wrap" id="loader">
        <div class="spinner"></div>
        Calcul de l'itinéraire en cours…
    </div>

    <!-- Erreur JS -->
    <div class="error-box" id="erreur-box"></div>

    <!-- Carte -->
    <div id="map-wrap">
        <div class="map-toolbar">
            <span>🗺️ Itinéraire calculé</span>
            <span class="map-badge"><i class="fas fa-road"></i> <span id="badge-distance">—</span></span>
            <span class="map-badge"><i class="fas fa-clock"></i> <span id="badge-duree">—</span></span>
        </div>
        <div id="map"></div>
    </div>

    <!-- Résultat tarif -->
    <div class="resultat" id="resultat" style="display:none;">
        <h2><i class="fas fa-check-circle"></i> Estimation</h2>
        <p id="res-trajet"></p>
        <div style="font-size:1.3rem; margin:1rem 0;" id="res-resume"></div>

        <div class="detail-tarif" id="res-detail"></div>

        <div class="total-final" id="res-total"></div>

        <div class="reglementation">
            <strong>✅ Conformément à l'Arrêté du 20 janvier 2025</strong><br>
            Tarif minimum 8 € • Prix km : 1,29 € • Horaire : 41,76 €/h
        </div>

        <div class="actions">
            <a href="reservation.php" class="btn-secondary">
                <i class="fas fa-car"></i> Réserver maintenant
            </a>
            <a href="tel:+33612345678" class="btn-secondary">
                <i class="fas fa-phone"></i> Appeler 06 12 34 56 78
            </a>
        </div>
    </div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// ── CONFIG TARIFS (miroir PHP) ──────────────────────────────
const TARIFS = {
    prise_en_charge:       4.48,
    km:                    1.29,
    horaire:               41.76 / 60,
    minimum:               8.00,
    bagages:               2.00,
    passagers_sup5:        4.00,
    reservation_immediate: 2.00
};

// ── CARTE LEAFLET ───────────────────────────────────────────
let map, routeLayer, markerDepart, markerArrivee;

function initMap() {
    if (map) return;
    map = L.map('map').setView([50.29, 2.78], 11); // Centré Arras
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
}

// ── GÉOCODAGE (Nominatim) ───────────────────────────────────
async function geocoder(adresse) {
    const url = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(adresse)}&format=json&limit=1&countrycodes=fr`;
    const r = await fetch(url, { headers: { 'Accept-Language': 'fr' } });
    const data = await r.json();
    if (!data.length) throw new Error(`Adresse introuvable : "${adresse}"`);
    return { lat: parseFloat(data[0].lat), lng: parseFloat(data[0].lon), label: data[0].display_name };
}

// ── ITINÉRAIRE (OSRM — gratuit, sans clé) ──────────────────
async function getRoute(dep, arr) {
    const url = `https://router.project-osrm.org/route/v1/driving/${dep.lng},${dep.lat};${arr.lng},${arr.lat}?overview=full&geometries=geojson`;
    const r   = await fetch(url);
    const data = await r.json();
    if (data.code !== 'Ok') throw new Error('Impossible de calculer l\'itinéraire.');
    const route = data.routes[0];
    return {
        distance_km:   route.distance / 1000,          // mètres → km
        duree_min:     route.duration / 60,            // secondes → minutes
        geometry:      route.geometry                  // GeoJSON LineString
    };
}

// ── CALCUL TARIF ────────────────────────────────────────────
function calculerTarif(distance_km, duree_min, passagers, bagages) {
    const prise_en_charge = TARIFS.prise_en_charge;
    const cout_km         = distance_km * TARIFS.km;
    const cout_temps      = duree_min   * TARIFS.horaire;
    const cout_base       = prise_en_charge + cout_km + cout_temps;

    const sup_passagers   = passagers > 5 ? (passagers - 4) * TARIFS.passagers_sup5 : 0;
    const sup_bagages     = bagages * TARIFS.bagages;
    const reservation     = TARIFS.reservation_immediate;

    const total = Math.max(TARIFS.minimum, cout_base + sup_passagers + sup_bagages + reservation);

    return { prise_en_charge, cout_km, cout_temps, sup_passagers, sup_bagages, reservation, total };
}

function fmt(n) { return n.toFixed(2).replace('.', ',') + ' €'; }
function fmtKm(n) { return n.toFixed(1).replace('.', ',') + ' km'; }
function fmtMin(n) {
    const h = Math.floor(n / 60), m = Math.round(n % 60);
    return h > 0 ? `${h}h${String(m).padStart(2,'0')}` : `${m} min`;
}

// ── MARQUEURS PERSONNALISÉS ─────────────────────────────────
function makeIcon(color) {
    return L.divIcon({
        className: '',
        html: `<div style="width:14px;height:14px;background:${color};border:3px solid white;border-radius:50%;box-shadow:0 2px 6px rgba(0,0,0,.4)"></div>`,
        iconSize: [14, 14],
        iconAnchor: [7, 7]
    });
}

// ── FONCTION PRINCIPALE ─────────────────────────────────────
async function calculer(e) {
    e.preventDefault();

    const dep      = document.getElementById('depart').value.trim();
    const arr      = document.getElementById('arrivee').value.trim();
    const passagers = parseInt(document.getElementById('passagers').value);
    const bagages   = parseInt(document.getElementById('bagages').value);

    if (!dep || !arr) return;

    // Reset UI
    document.getElementById('erreur-box').classList.remove('visible');
    document.getElementById('resultat').style.display = 'none';
    document.getElementById('map-wrap').classList.remove('visible');
    document.getElementById('loader').classList.add('visible');

    try {
        // 1. Géocodage
        const [coordDep, coordArr] = await Promise.all([geocoder(dep), geocoder(arr)]);

        // 2. Itinéraire OSRM
        const route = await getRoute(coordDep, coordArr);

        // 3. Carte
        initMap();
        document.getElementById('map-wrap').classList.add('visible');

        // Tracé
        if (routeLayer)  map.removeLayer(routeLayer);
        if (markerDepart) map.removeLayer(markerDepart);
        if (markerArrivee) map.removeLayer(markerArrivee);

        routeLayer = L.geoJSON(route.geometry, {
            style: { color: '#3b82f6', weight: 5, opacity: .85 }
        }).addTo(map);

        markerDepart  = L.marker([coordDep.lat, coordDep.lng], { icon: makeIcon('#10b981') })
                          .addTo(map).bindPopup(`<b>Départ</b><br>${dep}`);
        markerArrivee = L.marker([coordArr.lat, coordArr.lng], { icon: makeIcon('#ef4444') })
                          .addTo(map).bindPopup(`<b>Arrivée</b><br>${arr}`);

        map.fitBounds(routeLayer.getBounds(), { padding: [40, 40] });

        // Badges carte
        document.getElementById('badge-distance').textContent = fmtKm(route.distance_km);
        document.getElementById('badge-duree').textContent    = fmtMin(route.duree_min);

        // 4. Tarif
        const t = calculerTarif(route.distance_km, route.duree_min, passagers, bagages);

        document.getElementById('res-trajet').innerHTML =
            `<strong>${dep} → ${arr}</strong>`;
        document.getElementById('res-resume').innerHTML =
            `📏 ${fmtKm(route.distance_km)} &nbsp;•&nbsp; ⏱️ ${fmtMin(route.duree_min)}`;

        let detail = `
            <div class="detail-row"><span>Prise en charge</span><span>${fmt(t.prise_en_charge)}</span></div>
            <div class="detail-row"><span>Km parcourus (${fmtKm(route.distance_km)})</span><span>${fmt(t.cout_km)}</span></div>
            <div class="detail-row"><span>Temps (${fmtMin(route.duree_min)})</span><span>${fmt(t.cout_temps)}</span></div>
        `;
        if (t.sup_passagers > 0)
            detail += `<div class="detail-row"><span>Passagers supp. (${passagers})</span><span>${fmt(t.sup_passagers)}</span></div>`;
        if (t.sup_bagages > 0)
            detail += `<div class="detail-row"><span>Bagages (${bagages})</span><span>${fmt(t.sup_bagages)}</span></div>`;
        detail += `<div class="detail-row"><span>Réservation immédiate</span><span>${fmt(t.reservation)}</span></div>`;

        document.getElementById('res-detail').innerHTML = detail;
        document.getElementById('res-total').textContent = fmt(t.total);
        document.getElementById('resultat').style.display = 'block';

    } catch (err) {
        const box = document.getElementById('erreur-box');
        box.textContent = err.message;
        box.classList.add('visible');
    } finally {
        document.getElementById('loader').classList.remove('visible');
    }
}
</script>

</body>
</html>