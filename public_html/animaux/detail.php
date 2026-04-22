<?php
require_once '../includes/auth.php';
verifier_connexion();
require_once '../includes/path.php';
require_once '../config.php';

$role           = get_role();
$prenom         = $_SESSION['prenom'] ?? '';
$nom            = $_SESSION['nom'] ?? '';
$role_label     = get_role_affiche();
$peut_modifier  = in_array($role, ['admin', 'dirigeant'], true);
$est_soigneur   = in_array($role, ['soigneur', 'soigneur_chef', 'veterinaire'], true);

$rfid = trim($_GET['rfid'] ?? '');
if ($rfid === '') {
    header('Location: ' . url_site('/animaux/index.php'));
    exit;
}

$message      = '';
$type_message = 'success';
$onglet_actif = $_GET['onglet'] ?? 'details';
if (!in_array($onglet_actif, ['details', 'soins', 'parrainages'], true)) {
    $onglet_actif = 'details';
}

/* =========================
   CHARGER L'ANIMAL
========================= */
$stmt = oci_parse($conn, "
    SELECT a.rfid, a.nom_animal, a.date_naissance, a.poids,
           a.regime_alimentaire, a.zoo,
           a.id_espece, a.id_enclos, a.id_personnel,
           e.nom_usuel, e.nom_latin, e.est_menacee,
           en.id_enclos AS num_enclos,
           z.nom_zone,
           p.prenom_personnel, p.nom_personnel
    FROM Animal a
    LEFT JOIN Espece e    ON a.id_espece    = e.id_espece
    LEFT JOIN Enclos en   ON a.id_enclos    = en.id_enclos
    LEFT JOIN Zone z      ON en.id_zone     = z.id_zone
    LEFT JOIN Personnel p ON a.id_personnel = p.id_personnel
    WHERE a.rfid = :rfid
");
oci_bind_by_name($stmt, ':rfid', $rfid);
oci_execute($stmt);
$animal = oci_fetch_assoc($stmt);
oci_free_statement($stmt);

if (!$animal) {
    header('Location: ' . url_site('/animaux/index.php'));
    exit;
}

/* =========================
   MODIFIER L'ANIMAL
========================= */
if ($peut_modifier && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'modifier_animal') {
    $nom_animal     = trim($_POST['nom_animal'] ?? '');
    $date_naissance = trim($_POST['date_naissance'] ?? '');
    $poids          = trim($_POST['poids'] ?? '');
    $regime         = trim($_POST['regime_alimentaire'] ?? '');
    $zoo            = trim($_POST['zoo'] ?? '');
    $id_espece      = (int)($_POST['id_espece'] ?? 0);
    $id_enclos      = (int)($_POST['id_enclos'] ?? 0);
    $id_personnel   = (int)($_POST['id_personnel'] ?? 0);

    $stmt = oci_parse($conn, "
        UPDATE Animal
        SET nom_animal = :nom,
            date_naissance = TO_DATE(:dn, 'YYYY-MM-DD'),
            poids = :poids,
            regime_alimentaire = :regime,
            zoo = :zoo,
            id_espece = :ie,
            id_enclos = :enc,
            id_personnel = :ip
        WHERE rfid = :rfid
    ");

    oci_bind_by_name($stmt, ':nom', $nom_animal);
    oci_bind_by_name($stmt, ':dn', $date_naissance);
    oci_bind_by_name($stmt, ':poids', $poids);
    oci_bind_by_name($stmt, ':regime', $regime);
    oci_bind_by_name($stmt, ':zoo', $zoo);
    oci_bind_by_name($stmt, ':ie', $id_espece);
    oci_bind_by_name($stmt, ':enc', $id_enclos);
    oci_bind_by_name($stmt, ':ip', $id_personnel);
    oci_bind_by_name($stmt, ':rfid', $rfid);

    $ok = oci_execute($stmt);
    $message = $ok ? 'Animal modifié avec succès.' : 'Erreur lors de la modification.';
    $type_message = $ok ? 'success' : 'error';
    oci_free_statement($stmt);

    if ($ok) {
        $stmt2 = oci_parse($conn, "
            SELECT a.rfid, a.nom_animal, a.date_naissance, a.poids,
                   a.regime_alimentaire, a.zoo,
                   a.id_espece, a.id_enclos, a.id_personnel,
                   e.nom_usuel, e.nom_latin, e.est_menacee,
                   en.id_enclos AS num_enclos,
                   z.nom_zone,
                   p.prenom_personnel, p.nom_personnel
            FROM Animal a
            LEFT JOIN Espece e    ON a.id_espece    = e.id_espece
            LEFT JOIN Enclos en   ON a.id_enclos    = en.id_enclos
            LEFT JOIN Zone z      ON en.id_zone     = z.id_zone
            LEFT JOIN Personnel p ON a.id_personnel = p.id_personnel
            WHERE a.rfid = :rfid
        ");
        oci_bind_by_name($stmt2, ':rfid', $rfid);
        oci_execute($stmt2);
        $animal = oci_fetch_assoc($stmt2);
        oci_free_statement($stmt2);
    }
}

/* =========================
   AJOUTER UN SOIN
========================= */
if (($peut_modifier || $est_soigneur) && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ajouter_soin') {
    $date_soin      = trim($_POST['date_soin'] ?? '');
    $nom_soin_input = trim($_POST['nom_soin'] ?? '');
    $est_complexe   = isset($_POST['soin_complexe']);
    $absent         = isset($_POST['soigneur_absent']);

    // Cherche si le soin existe déjà
    $id_soin = 0;
    $st = oci_parse($conn, "SELECT id_soin FROM Soin WHERE LOWER(nom_soin) = LOWER(:ns)");
    oci_bind_by_name($st, ':ns', $nom_soin_input);
    oci_execute($st);
    $row = oci_fetch_array($st, OCI_NUM);
    oci_free_statement($st);

    if ($row) {
        $id_soin = (int)$row[0];
    } else {
        $st = oci_parse($conn, "SELECT NVL(MAX(id_soin),0)+1 FROM Soin");
        oci_execute($st);
        $r = oci_fetch_array($st, OCI_NUM);
        oci_free_statement($st);
        $id_soin = (int)$r[0];

        $type_soin = $est_complexe ? 'Complexe' : 'Simple';

        $st = oci_parse($conn, "INSERT INTO Soin(id_soin, nom_soin, type_soin) VALUES(:id, :ns, :ts)");
        oci_bind_by_name($st, ':id', $id_soin);
        oci_bind_by_name($st, ':ns', $nom_soin_input);
        oci_bind_by_name($st, ':ts', $type_soin);
        oci_execute($st);
        oci_free_statement($st);
    }

    // Détermine le personnel qui effectue le soin
    if ($est_complexe) {
        $id_pers_soin  = (int)($_POST['id_veterinaire'] ?? 0);
        $type_soigneur = 'Veterinaire';
    } elseif ($absent) {
        $id_pers_soin  = (int)($_POST['id_soigneur_remplacant'] ?? 0);
        $type_soigneur = 'Remplacant';
    } else {
        $id_pers_soin  = (int)($animal['ID_PERSONNEL'] ?? 0);
        $type_soigneur = 'Attitre';
    }

    // Génère le prochain id d'historique
    $nxt = 0;
    $st = oci_parse($conn, "SELECT NVL(MAX(id_historique_soins),0)+1 FROM Historique_soins");
    oci_execute($st);
    $r = oci_fetch_array($st, OCI_NUM);
    oci_free_statement($st);
    $nxt = (int)$r[0];

    // Insertion historique
    $st = oci_parse($conn, "
        INSERT INTO Historique_soins(
            id_historique_soins,
            type_soigneur,
            date_soin,
            id_soin,
            id_personnel,
            rfid
        )
        VALUES(
            :id,
            :ts,
            TO_DATE(:ds, 'YYYY-MM-DD'),
            :id_soin,
            :ip,
            :rf
        )
    ");

    oci_bind_by_name($st, ':id', $nxt);
    oci_bind_by_name($st, ':ts', $type_soigneur);
    oci_bind_by_name($st, ':ds', $date_soin);
    oci_bind_by_name($st, ':id_soin', $id_soin);
    oci_bind_by_name($st, ':ip', $id_pers_soin);
    oci_bind_by_name($st, ':rf', $rfid);

    $ok = oci_execute($st);
    $message = $ok ? 'Soin enregistré.' : 'Erreur lors de l\'enregistrement.';
    $type_message = $ok ? 'success' : 'error';
    oci_free_statement($st);
    $onglet_actif = 'soins';
}

/* =========================
   DONNÉES LIÉES
========================= */
$soins = [];
$st = oci_parse($conn, "
    SELECT hs.id_historique_soins,
           hs.type_soigneur,
           hs.date_soin,
           hs.id_soin,
           s.nom_soin,
           s.type_soin,
           p.prenom_personnel,
           p.nom_personnel
    FROM Historique_soins hs
    LEFT JOIN Soin s ON hs.id_soin = s.id_soin
    LEFT JOIN Personnel p ON hs.id_personnel = p.id_personnel
    WHERE hs.rfid = :rfid
    ORDER BY hs.date_soin DESC
");
oci_bind_by_name($st, ':rfid', $rfid);
if ($st && oci_execute($st)) {
    while ($l = oci_fetch_assoc($st)) $soins[] = $l;
    oci_free_statement($st);
}

$parrainages = [];
$st = oci_parse($conn, "
    SELECT p.id_parrainage,
           p.date_debut_parrainage,
           p.date_fin_parrainage,
           p.niveau,
           v.nom_visiteur,
           v.prenom_visiteur,
           v.email_visiteur,
           (
             SELECT LISTAGG(pr.nom_prestation, ', ') WITHIN GROUP(ORDER BY pr.nom_prestation)
             FROM Offrir o
             JOIN Prestation pr ON o.id_prestation = pr.id_prestation
             WHERE o.id_parrainage = p.id_parrainage
           ) prestations
    FROM Parrainage p
    LEFT JOIN Visiteur v ON p.id_visiteur = v.id_visiteur
    WHERE p.rfid = :rfid
    ORDER BY p.date_debut_parrainage DESC
");
oci_bind_by_name($st, ':rfid', $rfid);
if ($st && oci_execute($st)) {
    while ($l = oci_fetch_assoc($st)) $parrainages[] = $l;
    oci_free_statement($st);
}

$parents = [];
$st = oci_parse($conn, "
    SELECT a.rfid, a.nom_animal
    FROM Parent_fils pf
    JOIN Animal a ON pf.id_animal_parent = a.rfid
    WHERE pf.id_animal_fils = :rfid
");
oci_bind_by_name($st, ':rfid', $rfid);
if ($st && oci_execute($st)) {
    while ($l = oci_fetch_assoc($st)) $parents[] = $l;
    oci_free_statement($st);
}

$nourrissages = [];
$st = oci_parse($conn, "
    SELECT n.date_nourrissage,
           n.dose_nourrissage,
           n.nom_aliment,
           n.remarques_nourrissage,
           p.prenom_personnel,
           p.nom_personnel
    FROM Nourrissage n
    LEFT JOIN Personnel p ON n.id_personnel = p.id_personnel
    WHERE n.rfid = :rfid
    ORDER BY n.date_nourrissage DESC
    FETCH FIRST 10 ROWS ONLY
");
oci_bind_by_name($st, ':rfid', $rfid);
if ($st && oci_execute($st)) {
    while ($l = oci_fetch_assoc($st)) $nourrissages[] = $l;
    oci_free_statement($st);
}

/* =========================
   LISTES POUR LES MODALES
========================= */
$liste_especes = [];
$liste_enclos = [];
$liste_soigneurs = [];
$liste_soigneurs_specialises = [];
$liste_veterinaires = [];
$soins_suggestions = [];

$st = oci_parse($conn, "SELECT id_espece, nom_usuel FROM Espece ORDER BY nom_usuel");
if ($st && oci_execute($st)) {
    while ($l = oci_fetch_assoc($st)) $liste_especes[] = $l;
    oci_free_statement($st);
}

$st = oci_parse($conn, "SELECT en.id_enclos, z.nom_zone FROM Enclos en LEFT JOIN Zone z ON en.id_zone = z.id_zone ORDER BY en.id_enclos");
if ($st && oci_execute($st)) {
    while ($l = oci_fetch_assoc($st)) $liste_enclos[] = $l;
    oci_free_statement($st);
}

$st = oci_parse($conn, "
    SELECT DISTINCT p.id_personnel, p.prenom_personnel, p.nom_personnel
    FROM Personnel p, Historique_emploi h, Role r
    WHERE p.id_personnel = h.id_personnel
      AND h.id_role = r.id_role
      AND h.date_fin IS NULL
      AND LOWER(r.nom_role) IN ('soigneur', 'soigneur chef')
    ORDER BY p.nom_personnel
");
if ($st && oci_execute($st)) {
    while ($l = oci_fetch_assoc($st)) $liste_soigneurs[] = $l;
    oci_free_statement($st);
}

$id_esp = (int)($animal['ID_ESPECE'] ?? 0);
$st = oci_parse($conn, "
    SELECT DISTINCT p.id_personnel, p.prenom_personnel, p.nom_personnel
    FROM Personnel p, Historique_emploi h, Role r, Specialiser s
    WHERE p.id_personnel = h.id_personnel
      AND h.id_role = r.id_role
      AND h.date_fin IS NULL
      AND s.id_personnel = p.id_personnel
      AND s.id_espece = :ie
      AND LOWER(r.nom_role) IN ('soigneur', 'soigneur chef')
    ORDER BY p.nom_personnel
");
oci_bind_by_name($st, ':ie', $id_esp);
if ($st && oci_execute($st)) {
    while ($l = oci_fetch_assoc($st)) $liste_soigneurs_specialises[] = $l;
    oci_free_statement($st);
}

$st = oci_parse($conn, "
    SELECT DISTINCT p.id_personnel, p.prenom_personnel, p.nom_personnel
    FROM Personnel p, Historique_emploi h, Role r
    WHERE p.id_personnel = h.id_personnel
      AND h.id_role = r.id_role
      AND h.date_fin IS NULL
      AND LOWER(r.nom_role) = 'veterinaire'
    ORDER BY p.nom_personnel
");
if ($st && oci_execute($st)) {
    while ($l = oci_fetch_assoc($st)) $liste_veterinaires[] = $l;
    oci_free_statement($st);
}

$st = oci_parse($conn, "SELECT id_soin, nom_soin, type_soin FROM Soin ORDER BY nom_soin");
if ($st && oci_execute($st)) {
    while ($l = oci_fetch_assoc($st)) $soins_suggestions[] = $l;
    oci_free_statement($st);
}

oci_close($conn);

/* =========================
   FONCTIONS
========================= */
function formater_date_affichage($d) {
    if (empty($d)) return '—';
    $ts = strtotime((string)$d);
    return $ts ? date('d/m/Y', $ts) : '—';
}

function formater_date_input($d) {
    if (empty($d)) return '';
    $ts = strtotime((string)$d);
    return $ts ? date('Y-m-d', $ts) : '';
}

/* =========================
   IMAGE HERO
========================= */
$animal_images = [
    'lion'     => 'https://images.unsplash.com/photo-1546182990-dffeafbe841d?auto=format&fit=crop&w=800&q=80',
    'elephant' => 'https://images.unsplash.com/photo-1564760055775-d63b17a55c44?auto=format&fit=crop&w=800&q=80',
    'panda'    => 'https://images.unsplash.com/photo-1530595467517-49d9a2272dc9?auto=format&fit=crop&w=800&q=80',
    'aigle'    => 'https://images.unsplash.com/photo-1611689342806-0863700e1a23?auto=format&fit=crop&w=800&q=80',
    'grizzly'  => 'https://images.unsplash.com/photo-1589656966895-2f33e7653819?auto=format&fit=crop&w=800&q=80',
    'girafe'   => 'https://images.unsplash.com/photo-1547721064-da6cfb341d50?auto=format&fit=crop&w=800&q=80',
    'zebre'    => 'https://images.unsplash.com/photo-1504173010664-32509107de75?auto=format&fit=crop&w=800&q=80',
    'default'  => 'https://images.unsplash.com/photo-1474511320723-9a56873867b5?auto=format&fit=crop&w=800&q=80'
];

$esp_lower = strtolower($animal['NOM_USUEL'] ?? '');
$hero_img = $animal_images['default'];
foreach ($animal_images as $k => $v) {
    if ($k !== 'default' && str_contains($esp_lower, $k)) {
        $hero_img = $v;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo htmlspecialchars($animal['NOM_ANIMAL'] ?? 'Animal'); ?> — Zoo'land</title>
  <link href="<?php echo htmlspecialchars(url_site('/assets/vendor/bootstrap/css/bootstrap.min.css')); ?>" rel="stylesheet">
  <link href="<?php echo htmlspecialchars(url_site('/assets/css/bootstrap-icons-local.css')); ?>" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400&display=swap" rel="stylesheet">
  <link href="<?php echo htmlspecialchars(url_site('/assets/css/global.css')); ?>" rel="stylesheet">
  <link href="<?php echo htmlspecialchars(url_site('/assets/css/animaux.css')); ?>" rel="stylesheet">
  <style>
    .detail-hero{position:relative;overflow:hidden;border-radius:32px;min-height:280px;color:#fff;isolation:isolate;box-shadow:var(--shadow-lg);margin-bottom:1.75rem}
    .detail-hero::before{content:'';position:absolute;inset:0;background:linear-gradient(110deg,rgba(10,25,20,.85) 0%,rgba(10,25,20,.45) 50%,rgba(10,25,20,.15) 100%),url('<?php echo $hero_img;?>') center/cover;transform:scale(1.04);z-index:-2}
    .detail-hero::after{content:'';position:absolute;inset:0;background:radial-gradient(circle at 75% 20%,rgba(255,255,255,.18),transparent 22%),radial-gradient(circle at 10% 75%,rgba(26,156,91,.22),transparent 24%);z-index:-1}
    .detail-tabs{display:flex;gap:.55rem;padding:1.25rem 1.5rem 0;border-bottom:1px solid rgba(26,35,50,.07);flex-wrap:wrap}
    .detail-tab{display:inline-flex;align-items:center;gap:.45rem;padding:.65rem 1.1rem;border-radius:18px 18px 0 0;font-weight:700;font-size:.88rem;color:var(--txt-muted);text-decoration:none;border:1px solid transparent;border-bottom:none;transition:all .2s ease;background:transparent}
    .detail-tab:hover{color:var(--txt);background:rgba(255,255,255,.6)}
    .detail-tab.active{background:#fff;color:var(--green);border-color:rgba(26,35,50,.08);border-bottom-color:#fff;position:relative;z-index:1;margin-bottom:-1px}
    .detail-tab-count{display:inline-flex;align-items:center;justify-content:center;min-width:20px;height:20px;padding:0 5px;border-radius:999px;font-size:.7rem;font-weight:800;background:rgba(26,35,50,.08);color:var(--txt-muted)}
    .detail-tab.active .detail-tab-count{background:rgba(26,156,91,.12);color:var(--green)}
    .info-block{background:rgba(255,255,255,.7);border:1px solid rgba(255,255,255,.9);border-radius:18px;padding:1rem 1.15rem}
    .info-label{font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--txt-muted);margin-bottom:.25rem}
    .info-value{font-weight:700;font-size:.97rem;margin:0}
    .soin-entry-detail{display:flex;align-items:flex-start;gap:1rem;padding:.9rem 1.1rem;background:rgba(255,255,255,.72);border:1px solid rgba(255,255,255,.9);border-radius:18px;margin-bottom:.6rem;transition:transform .18s ease}
    .soin-entry-detail:hover{transform:translateX(3px)}
    .parrainage-entry{padding:1.1rem 1.25rem;background:rgba(255,255,255,.72);border:1px solid rgba(255,255,255,.9);border-radius:18px;margin-bottom:.7rem}
    .hidden-block{display:none}
    .regime-badge{display:inline-flex;align-items:center;gap:.4rem;padding:.35rem .85rem;border-radius:var(--radius-pill);font-weight:700;font-size:.84rem}
    .regime-carnivore{background:#fff0e6;color:#b84400;border:1px solid rgba(184,68,0,.15)}
    .regime-herbivore{background:rgba(0,208,132,.12);color:var(--green);border:1px solid rgba(26,156,91,.15)}
    .regime-omnivore{background:rgba(211,138,16,.12);color:#b97b00;border:1px solid rgba(245,166,35,.15)}
  </style>
</head>
<body>
<div class="d-flex app-layout">
  <div class="app-sidebar-col"><?php include '../includes/sidebar.php'; ?></div>
  <main class="app-content-col">
    <div class="page-padding">

      <div class="detail-hero reveal">
        <div style="padding:2.5rem 2rem">
          <div class="hero-pill mb-3">
            <i class="bi bi-broadcast"></i> <?php echo htmlspecialchars($animal['RFID']); ?>
          </div>
          <h2 style="font-family:'Nunito',sans-serif;font-size:clamp(2rem,4vw,3.5rem);font-weight:800;margin:0 0 .4rem;line-height:1.05">
            <?php echo htmlspecialchars($animal['NOM_ANIMAL'] ?? '—'); ?>
          </h2>
          <div style="display:flex;flex-wrap:wrap;gap:.65rem;align-items:center;margin-bottom:1.5rem">
            <span style="color:rgba(255,255,255,.82);font-size:1rem"><?php echo htmlspecialchars($animal['NOM_USUEL'] ?? ''); ?></span>
            <?php if (!empty($animal['NOM_LATIN'])): ?>
            <span style="color:rgba(255,255,255,.55);font-style:italic;font-size:.9rem"><?php echo htmlspecialchars($animal['NOM_LATIN']); ?></span>
            <?php endif; ?>
            <?php if ((int)($animal['EST_MENACEE'] ?? 0) === 1): ?>
            <span class="badge badge-menace"><i class="bi bi-exclamation-triangle-fill"></i> Menacée</span>
            <?php endif; ?>
          </div>

          <div style="display:flex;flex-wrap:wrap;gap:.85rem">
            <?php if (!empty($animal['NOM_ZONE'])): ?>
            <span style="display:inline-flex;align-items:center;gap:.4rem;padding:.5rem 1rem;border-radius:var(--radius-pill);background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);font-size:.85rem;font-weight:700;backdrop-filter:blur(8px)">
              <i class="bi bi-geo-alt-fill"></i> <?php echo htmlspecialchars($animal['NOM_ZONE']); ?>
            </span>
            <?php endif; ?>
            <?php if (!empty($animal['POIDS'])): ?>
            <span style="display:inline-flex;align-items:center;gap:.4rem;padding:.5rem 1rem;border-radius:var(--radius-pill);background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);font-size:.85rem;font-weight:700;backdrop-filter:blur(8px)">
              <i class="bi bi-speedometer2"></i> <?php echo htmlspecialchars($animal['POIDS']); ?> kg
            </span>
            <?php endif; ?>
            <?php if (!empty($animal['REGIME_ALIMENTAIRE'])): ?>
            <span style="display:inline-flex;align-items:center;gap:.4rem;padding:.5rem 1rem;border-radius:var(--radius-pill);background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);font-size:.85rem;font-weight:700;backdrop-filter:blur(8px)">
              <i class="bi bi-egg-fried"></i> <?php echo htmlspecialchars($animal['REGIME_ALIMENTAIRE']); ?>
            </span>
            <?php endif; ?>
            <?php if (!empty($animal['ZOO'])): ?>
            <span style="display:inline-flex;align-items:center;gap:.4rem;padding:.5rem 1rem;border-radius:var(--radius-pill);background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);font-size:.85rem;font-weight:700;backdrop-filter:blur(8px)">
              <i class="bi bi-building"></i> <?php echo htmlspecialchars($animal['ZOO']); ?>
            </span>
            <?php endif; ?>
          </div>

          <div style="display:flex;flex-wrap:wrap;gap:.7rem;margin-top:1.35rem">
            <?php if ($peut_modifier): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalModifierAnimal">
              <i class="bi bi-pencil-fill"></i> Modifier
            </button>
            <?php endif; ?>
            <a href="<?php echo htmlspecialchars(url_site('/animaux/index.php')); ?>" class="btn btn-ghost">
              <i class="bi bi-arrow-left"></i> Retour aux animaux
            </a>
          </div>
        </div>
      </div>

      <?php if (!empty($message)): ?>
      <div class="alert-<?php echo $type_message === 'success' ? 'success' : 'danger'; ?> mb-4 reveal">
        <?php echo htmlspecialchars($message); ?>
      </div>
      <?php endif; ?>

      <div class="section-block reveal" style="overflow:visible">
        <div class="detail-tabs">
          <a class="detail-tab <?php echo $onglet_actif === 'details' ? 'active' : ''; ?>"
             href="?rfid=<?php echo urlencode($rfid); ?>&onglet=details">
            <i class="bi bi-info-circle-fill"></i> Détails
          </a>
          <a class="detail-tab <?php echo $onglet_actif === 'soins' ? 'active' : ''; ?>"
             href="?rfid=<?php echo urlencode($rfid); ?>&onglet=soins">
            <i class="bi bi-bandaid-fill"></i> Soins
            <span class="detail-tab-count"><?php echo count($soins); ?></span>
          </a>
          <a class="detail-tab <?php echo $onglet_actif === 'parrainages' ? 'active' : ''; ?>"
             href="?rfid=<?php echo urlencode($rfid); ?>&onglet=parrainages">
            <i class="bi bi-heart-fill"></i> Parrainages
            <span class="detail-tab-count"><?php echo count($parrainages); ?></span>
          </a>
        </div>

        <?php if ($onglet_actif === 'details'): ?>
        <div style="padding:1.75rem">
          <div class="row g-3 mb-4">
            <div class="col-sm-6 col-md-4">
              <div class="info-block">
                <div class="info-label">RFID</div>
                <div class="info-value" style="font-family:monospace;font-size:.88rem"><?php echo htmlspecialchars($animal['RFID']); ?></div>
              </div>
            </div>
            <div class="col-sm-6 col-md-4">
              <div class="info-block">
                <div class="info-label">Date de naissance</div>
                <div class="info-value"><?php echo formater_date_affichage($animal['DATE_NAISSANCE']); ?></div>
              </div>
            </div>
            <div class="col-sm-6 col-md-4">
              <div class="info-block">
                <div class="info-label">Poids</div>
                <div class="info-value"><?php echo !empty($animal['POIDS']) ? htmlspecialchars($animal['POIDS']) . ' kg' : '—'; ?></div>
              </div>
            </div>
            <div class="col-sm-6 col-md-4">
              <div class="info-block">
                <div class="info-label">Régime alimentaire</div>
                <?php
                $reg = strtolower($animal['REGIME_ALIMENTAIRE'] ?? '');
                $rc = str_contains($reg, 'carni')
                    ? 'regime-carnivore'
                    : (str_contains($reg, 'herbi') ? 'regime-herbivore' : 'regime-omnivore');
                ?>
                <span class="regime-badge <?php echo $rc; ?>">
                  <i class="bi bi-egg-fried"></i> <?php echo htmlspecialchars($animal['REGIME_ALIMENTAIRE'] ?? '—'); ?>
                </span>
              </div>
            </div>
            <div class="col-sm-6 col-md-4">
              <div class="info-block">
                <div class="info-label">Enclos</div>
                <div class="info-value">
                  <?php if (!empty($animal['NUM_ENCLOS'])): ?>
                    <a href="<?php echo htmlspecialchars(url_site('/enclos/detail.php?id=' . (int)$animal['NUM_ENCLOS'])); ?>" class="text-decoration-none">
                      Enclos <?php echo (int)$animal['NUM_ENCLOS']; ?>
                    </a>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-md-4">
              <div class="info-block">
                <div class="info-label">Zone</div>
                <div class="info-value"><?php echo htmlspecialchars($animal['NOM_ZONE'] ?? '—'); ?></div>
              </div>
            </div>
            <div class="col-sm-6 col-md-4">
              <div class="info-block">
                <div class="info-label">Soigneur attitré</div>
                <?php $sn = trim(($animal['PRENOM_PERSONNEL'] ?? '') . ' ' . ($animal['NOM_PERSONNEL'] ?? '')); ?>
                <div class="info-value"><?php echo $sn !== '' ? htmlspecialchars($sn) : 'Non assigné'; ?></div>
              </div>
            </div>
            <div class="col-sm-6 col-md-4">
              <div class="info-block">
                <div class="info-label">Zoo d'origine</div>
                <div class="info-value"><?php echo htmlspecialchars($animal['ZOO'] ?? '—'); ?></div>
              </div>
            </div>
            <div class="col-sm-6 col-md-4">
              <div class="info-block">
                <div class="info-label">Espèce</div>
                <div class="info-value"><?php echo htmlspecialchars($animal['NOM_USUEL'] ?? '—'); ?></div>
              </div>
            </div>
          </div>

          <?php if (!empty($parents)): ?>
          <div style="padding-top:1.25rem;border-top:1px solid rgba(26,35,50,.08)">
            <div class="overline mb-3"><i class="bi bi-diagram-2 me-1"></i> Généalogie</div>
            <div class="d-flex flex-wrap gap-3">
              <?php foreach ($parents as $idx => $parent): ?>
              <a href="<?php echo htmlspecialchars(url_site('/animaux/detail.php?rfid=' . urlencode($parent['RFID']))); ?>"
                 style="display:inline-flex;align-items:center;gap:.6rem;padding:.75rem 1.1rem;background:rgba(255,255,255,.8);border:1px solid rgba(255,255,255,.9);border-radius:18px;color:var(--txt);font-weight:700;font-size:.88rem;text-decoration:none;transition:all .2s ease"
                 onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
                <i class="bi bi-person-heart" style="color:var(--g)"></i>
                Parent <?php echo $idx + 1; ?> — <?php echo htmlspecialchars($parent['NOM_ANIMAL'] ?? $parent['RFID']); ?>
              </a>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <?php if (!empty($nourrissages)): ?>
          <div style="padding-top:1.25rem;margin-top:1.25rem;border-top:1px solid rgba(26,35,50,.08)">
            <div class="overline mb-3"><i class="bi bi-egg-fried me-1"></i> Derniers nourrissages</div>
            <div class="table-responsive">
              <table class="zoo-table table" style="border-radius:18px;overflow:hidden">
                <thead>
                  <tr><th>Date</th><th>Aliment</th><th>Dose</th><th>Soigneur</th><th>Remarques</th></tr>
                </thead>
                <tbody>
                <?php foreach ($nourrissages as $n): ?>
                <tr>
                  <td><?php echo formater_date_affichage($n['DATE_NOURRISSAGE']); ?></td>
                  <td class="fw-semibold"><?php echo htmlspecialchars($n['NOM_ALIMENT'] ?? '—'); ?></td>
                  <td><?php echo !empty($n['DOSE_NOURRISSAGE']) ? $n['DOSE_NOURRISSAGE'] . ' kg' : '—'; ?></td>
                  <td><?php echo htmlspecialchars(($n['PRENOM_PERSONNEL'] ?? '') . ' ' . ($n['NOM_PERSONNEL'] ?? '')); ?></td>
                  <td class="text-muted" style="font-size:.84rem"><?php echo htmlspecialchars($n['REMARQUES_NOURRISSAGE'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($onglet_actif === 'soins'): ?>
        <div style="padding:1.75rem">
          <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <span style="font-size:.88rem;color:var(--txt-muted)"><?php echo count($soins); ?> soin(s) enregistré(s)</span>
            <?php if ($peut_modifier || $est_soigneur): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAjouterSoin">
              <i class="bi bi-plus-lg"></i> Ajouter un soin
            </button>
            <?php endif; ?>
          </div>

          <?php if (empty($soins)): ?>
          <div class="text-center py-5 text-muted"><i class="bi bi-bandaid" style="font-size:2rem;opacity:.3;display:block;margin-bottom:.75rem"></i>Aucun soin enregistré</div>
          <?php else: foreach ($soins as $soin):
            $ts_lower = strtolower($soin['TYPE_SOIGNEUR'] ?? '');
            if (str_contains($ts_lower, 'vet')) {
                $tc_bg='var(--violet-soft)'; $tc_col='var(--v)'; $tc_lbl='Vétérinaire';
            } elseif (str_contains($ts_lower, 'rempla')) {
                $tc_bg='rgba(211,138,16,.12)'; $tc_col='#b97b00'; $tc_lbl='Remplaçant';
            } else {
                $tc_bg='rgba(0,208,132,.12)'; $tc_col='var(--g)'; $tc_lbl='Attitré';
            }
            $is_complexe = strtolower($soin['TYPE_SOIN'] ?? '') === 'complexe';
          ?>
          <div class="soin-entry-detail">
            <div style="width:46px;height:46px;border-radius:14px;background:<?php echo $is_complexe ? 'var(--coral-soft)' : 'var(--sky-soft)'; ?>;display:flex;align-items:center;justify-content:center;color:<?php echo $is_complexe ? 'var(--r)' : 'var(--s)'; ?>;font-size:1.15rem;flex-shrink:0">
              <i class="bi bi-<?php echo $is_complexe ? 'heart-pulse-fill' : 'bandaid-fill'; ?>"></i>
            </div>
            <div style="flex:1">
              <div style="font-weight:700;font-size:.97rem;margin-bottom:.3rem">
                <?php if (!empty($soin['ID_SOIN'])): ?>
                  <a href="<?php echo htmlspecialchars(url_site('/soins/detail.php?id=' . (int)$soin['ID_SOIN'])); ?>" class="text-decoration-none">
                    <?php echo htmlspecialchars($soin['NOM_SOIN'] ?? '—'); ?>
                  </a>
                <?php else: ?>
                  <?php echo htmlspecialchars($soin['NOM_SOIN'] ?? '—'); ?>
                <?php endif; ?>
              </div>
              <div style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;font-size:.82rem;color:var(--txt-muted)">
                <span><i class="bi bi-calendar3 me-1"></i><?php echo formater_date_affichage($soin['DATE_SOIN']); ?></span>
                <span><i class="bi bi-person-fill me-1"></i><?php echo htmlspecialchars(trim(($soin['PRENOM_PERSONNEL'] ?? '') . ' ' . ($soin['NOM_PERSONNEL'] ?? ''))); ?></span>
                <span style="padding:.2rem .55rem;border-radius:8px;background:<?php echo $tc_bg; ?>;color:<?php echo $tc_col; ?>;font-weight:700;font-size:.72rem"><?php echo $tc_lbl; ?></span>
                <?php if ($is_complexe): ?><span style="padding:.2rem .55rem;border-radius:8px;background:var(--coral-soft);color:var(--rose);font-weight:700;font-size:.72rem">Complexe</span><?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($onglet_actif === 'parrainages'): ?>
        <div style="padding:1.75rem">
          <div class="overline mb-3"><?php echo count($parrainages); ?> parrainage(s)</div>
          <?php if (empty($parrainages)): ?>
          <div class="text-center py-5 text-muted"><i class="bi bi-heart" style="font-size:2rem;opacity:.3;display:block;margin-bottom:.75rem"></i>Aucun parrainage</div>
          <?php else: foreach ($parrainages as $par):
            $niv = strtolower(trim($par['NIVEAU'] ?? ''));
            $actif = empty($par['DATE_FIN_PARRAINAGE']) || strtotime((string)$par['DATE_FIN_PARRAINAGE']) >= time();
            $prests = !empty($par['PRESTATIONS']) ? explode(',', $par['PRESTATIONS']) : [];
          ?>
          <div class="parrainage-entry">
            <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
              <div>
                <div style="font-weight:700"><?php echo htmlspecialchars(trim(($par['PRENOM_VISITEUR'] ?? '') . ' ' . ($par['NOM_VISITEUR'] ?? ''))); ?></div>
                <div style="font-size:.82rem;color:var(--txt-muted)"><?php echo htmlspecialchars($par['EMAIL_VISITEUR'] ?? ''); ?></div>
              </div>
              <div class="d-flex gap-2 align-items-center">
                <?php if ($niv === 'or'): ?>
                  <span class="niveau-or" style="padding:.25rem .65rem;border-radius:999px;background:linear-gradient(135deg,#ffd700,#ffb300);color:#6b4500;font-size:.75rem;font-weight:800">⭐ Or</span>
                <?php elseif ($niv === 'argent'): ?>
                  <span style="padding:.25rem .65rem;border-radius:999px;background:linear-gradient(135deg,#e0e0e0,#c0c0c0);color:#3a3a3a;font-size:.75rem;font-weight:800">🥈 Argent</span>
                <?php elseif ($niv === 'bronze'): ?>
                  <span style="padding:.25rem .65rem;border-radius:999px;background:linear-gradient(135deg,#cd7f32,#a0522d);color:#fff;font-size:.75rem;font-weight:800">🥉 Bronze</span>
                <?php endif; ?>
                <span style="padding:.22rem .6rem;border-radius:999px;font-size:.72rem;font-weight:700;background:<?php echo $actif ? 'rgba(0,208,132,.12)' : 'rgba(26,35,50,.06)'; ?>;color:<?php echo $actif ? 'var(--g)' : 'var(--txt-muted)'; ?>;">
                  <?php echo $actif ? 'Actif' : 'Expiré'; ?>
                </span>
              </div>
            </div>
            <div style="font-size:.82rem;color:var(--txt-muted);margin-bottom:.6rem">
              <i class="bi bi-calendar3 me-1"></i>
              <?php echo formater_date_affichage($par['DATE_DEBUT_PARRAINAGE']); ?>
              <?php if (!empty($par['DATE_FIN_PARRAINAGE'])): ?>
                → <?php echo formater_date_affichage($par['DATE_FIN_PARRAINAGE']); ?>
              <?php endif; ?>
            </div>
            <?php if (!empty($prests)): ?>
            <div style="display:flex;flex-wrap:wrap;gap:.4rem">
              <?php foreach ($prests as $pr): $pr = trim($pr); if ($pr): ?>
              <span style="padding:.2rem .55rem;border-radius:8px;background:var(--violet-soft);color:var(--v);font-size:.73rem;font-weight:700;border:1px solid rgba(124,92,191,.12)">
                <i class="bi bi-gift-fill me-1"></i><?php echo htmlspecialchars($pr); ?>
              </span>
              <?php endif; endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; endif; ?>
        </div>
        <?php endif; ?>

      </div>
    </div>
  </main>
</div>

<?php if ($peut_modifier): ?>
<div class="modal fade" id="modalModifierAnimal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Modifier <?php echo htmlspecialchars($animal['NOM_ANIMAL']); ?></h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="POST" action="?rfid=<?php echo urlencode($rfid); ?>&onglet=details">
          <input type="hidden" name="action" value="modifier_animal">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Nom</label><input type="text" name="nom_animal" class="form-control" value="<?php echo htmlspecialchars($animal['NOM_ANIMAL'] ?? ''); ?>" required></div>
            <div class="col-md-6"><label class="form-label">Date de naissance</label><input type="date" name="date_naissance" class="form-control" value="<?php echo formater_date_input($animal['DATE_NAISSANCE']); ?>"></div>
            <div class="col-md-6"><label class="form-label">Poids (kg)</label><input type="number" step="0.01" name="poids" class="form-control" value="<?php echo htmlspecialchars($animal['POIDS'] ?? ''); ?>"></div>
            <div class="col-md-6"><label class="form-label">Régime alimentaire</label><input type="text" name="regime_alimentaire" class="form-control" value="<?php echo htmlspecialchars($animal['REGIME_ALIMENTAIRE'] ?? ''); ?>"></div>
            <div class="col-md-6"><label class="form-label">Zoo d'origine</label><input type="text" name="zoo" class="form-control" value="<?php echo htmlspecialchars($animal['ZOO'] ?? ''); ?>"></div>
            <div class="col-md-6"><label class="form-label">Espèce</label><select name="id_espece" class="form-select"><option value="">—</option><?php foreach ($liste_especes as $e): ?><option value="<?php echo $e['ID_ESPECE']; ?>"<?php echo ((int)$e['ID_ESPECE'] === (int)($animal['ID_ESPECE'] ?? 0)) ? ' selected' : ''; ?>><?php echo htmlspecialchars($e['NOM_USUEL']); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Enclos</label><select name="id_enclos" class="form-select"><option value="">—</option><?php foreach ($liste_enclos as $e): ?><option value="<?php echo $e['ID_ENCLOS']; ?>"<?php echo ((int)$e['ID_ENCLOS'] === (int)($animal['ID_ENCLOS'] ?? 0)) ? ' selected' : ''; ?>>Enclos <?php echo $e['ID_ENCLOS']; ?> — <?php echo htmlspecialchars($e['NOM_ZONE'] ?? ''); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Soigneur attitré</label><select name="id_personnel" class="form-select"><option value="">—</option><?php foreach ($liste_soigneurs as $p): ?><option value="<?php echo $p['ID_PERSONNEL']; ?>"<?php echo ((int)$p['ID_PERSONNEL'] === (int)($animal['ID_PERSONNEL'] ?? 0)) ? ' selected' : ''; ?>><?php echo htmlspecialchars($p['PRENOM_PERSONNEL'] . ' ' . $p['NOM_PERSONNEL']); ?></option><?php endforeach; ?></select></div>
          </div>
          <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Enregistrer</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($peut_modifier || $est_soigneur): ?>
<div class="modal fade" id="modalAjouterSoin" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Nouveau soin — <?php echo htmlspecialchars($animal['NOM_ANIMAL']); ?></h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="POST" action="?rfid=<?php echo urlencode($rfid); ?>&onglet=soins">
          <input type="hidden" name="action" value="ajouter_soin">

          <div class="mb-3">
            <label class="form-label">Date du soin</label>
            <input type="date" name="date_soin" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Nom du soin</label>
            <input type="text" name="nom_soin" class="form-control" placeholder="Ex: Vaccination, Bilan de santé…" list="datalistSoins" required>
            <datalist id="datalistSoins">
              <?php foreach ($soins_suggestions as $s): ?>
              <option value="<?php echo htmlspecialchars($s['NOM_SOIN']); ?>"></option>
              <?php endforeach; ?>
            </datalist>
          </div>

          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="checkComplexe" name="soin_complexe" value="1">
            <label class="form-check-label" for="checkComplexe">Soin complexe (vétérinaire)</label>
          </div>

          <div id="blocSimple">
            <div class="mb-3">
              <label class="form-label">Soigneur attitré</label>
              <select class="form-select" disabled>
                <option><?php echo htmlspecialchars(trim(($animal['PRENOM_PERSONNEL'] ?? '') . ' ' . ($animal['NOM_PERSONNEL'] ?? '')) ?: 'Non assigné'); ?></option>
              </select>
            </div>

            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="checkAbsent" name="soigneur_absent" value="1">
              <label class="form-check-label" for="checkAbsent">Soigneur absent — désigner un remplaçant</label>
            </div>

            <div class="mb-3 hidden-block" id="blocRemplacant">
              <label class="form-label">Soigneur remplaçant</label>
              <select name="id_soigneur_remplacant" class="form-select">
                <option value="">— Choisir —</option>
                <?php
                $attitreId = (int)($animal['ID_PERSONNEL'] ?? 0);
                foreach ($liste_soigneurs_specialises as $p):
                    if ((int)$p['ID_PERSONNEL'] === $attitreId) continue;
                ?>
                <option value="<?php echo $p['ID_PERSONNEL']; ?>"><?php echo htmlspecialchars($p['PRENOM_PERSONNEL'] . ' ' . $p['NOM_PERSONNEL']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="mb-3 hidden-block" id="blocVeterinaire">
            <label class="form-label">Vétérinaire</label>
            <select name="id_veterinaire" class="form-select">
              <option value="">— Choisir —</option>
              <?php foreach ($liste_veterinaires as $v): ?>
              <option value="<?php echo $v['ID_PERSONNEL']; ?>">Dr. <?php echo htmlspecialchars($v['PRENOM_PERSONNEL'] . ' ' . $v['NOM_PERSONNEL']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Enregistrer</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script src="<?php echo htmlspecialchars(url_site('/assets/vendor/bootstrap/js/bootstrap.bundle.min.js')); ?>"></script>
<script>
function toggleSoinUI() {
  const checkComplexe = document.getElementById('checkComplexe');
  const checkAbsent = document.getElementById('checkAbsent');
  const blocSimple = document.getElementById('blocSimple');
  const blocVeterinaire = document.getElementById('blocVeterinaire');
  const blocRemplacant = document.getElementById('blocRemplacant');

  const isC = checkComplexe ? checkComplexe.checked : false;
  const isA = checkAbsent ? checkAbsent.checked : false;

  if (blocSimple) blocSimple.classList.toggle('hidden-block', isC);
  if (blocVeterinaire) blocVeterinaire.classList.toggle('hidden-block', !isC);
  if (!isC && blocRemplacant) blocRemplacant.classList.toggle('hidden-block', !isA);
}

const checkComplexe = document.getElementById('checkComplexe');
const checkAbsent = document.getElementById('checkAbsent');

if (checkComplexe) checkComplexe.addEventListener('change', toggleSoinUI);
if (checkAbsent) checkAbsent.addEventListener('change', toggleSoinUI);

toggleSoinUI();

document.querySelectorAll('.reveal').forEach((el, i) => {
  setTimeout(() => el.classList.add('visible'), 60 + i * 50);
});
</script>
</body>
</html>
