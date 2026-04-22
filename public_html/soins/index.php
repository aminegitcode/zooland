<?php

require_once '../includes/auth.php';
require_role(['admin','dirigeant','soigneur','soigneur_chef','veterinaire']);

require_once '../includes/path.php';
require_once '../includes/helpers.php';
require_once '../config.php';

$role = get_role();
$peut_modifier = in_array($role, ['admin','dirigeant','soigneur_chef','veterinaire'], true);
$est_soigneur  = in_array($role, ['soigneur','soigneur_chef','veterinaire'], true);

$message = '';
$type = 'success';

/* =========================
   AJOUTER UN SOIN
========================= */
if (($peut_modifier || $est_soigneur) && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ajouter_soin') {
    $date_soin      = trim($_POST['date_soin'] ?? date('Y-m-d'));
    $nom_soin_input = trim($_POST['nom_soin'] ?? '');
    $rfid           = trim($_POST['rfid'] ?? '');
    $est_complexe   = isset($_POST['soin_complexe']);
    $absent         = isset($_POST['soigneur_absent']);

    if ($nom_soin_input === '' || $rfid === '') {
        $message = 'Veuillez remplir les champs obligatoires.';
        $type = 'danger';
    } else {
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

            $id_soin = (int)($r[0] ?? 1);
            $type_soin = $est_complexe ? 'Complexe' : 'Simple';

            $st = oci_parse($conn, "
                INSERT INTO Soin(id_soin, nom_soin, type_soin)
                VALUES(:id, :ns, :ts)
            ");
            oci_bind_by_name($st, ':id', $id_soin);
            oci_bind_by_name($st, ':ns', $nom_soin_input);
            oci_bind_by_name($st, ':ts', $type_soin);
            $ok_soin = oci_execute($st);
            oci_free_statement($st);

            if (!$ok_soin) {
                $message = 'Erreur lors de la création du soin.';
                $type = 'danger';
            }
        }

        if ($type !== 'danger') {
            // Récupère le soigneur attitré et l'espèce de l'animal
            $animal_data = null;
            $st = oci_parse($conn, "
                SELECT a.id_personnel, a.id_espece
                FROM Animal a
                WHERE a.rfid = :rfid
            ");
            oci_bind_by_name($st, ':rfid', $rfid);
            oci_execute($st);
            $animal_data = oci_fetch_assoc($st);
            oci_free_statement($st);

            $animal_soigneur_id = (int)($animal_data['ID_PERSONNEL'] ?? 0);

            if ($est_complexe) {
                $id_pers_soin  = (int)($_POST['id_veterinaire'] ?? 0);
                $type_soigneur = 'Veterinaire';
            } elseif ($absent) {
                $id_pers_soin  = (int)($_POST['id_soigneur_remplacant'] ?? 0);
                $type_soigneur = 'Remplacant';
            } else {
                $id_pers_soin  = $animal_soigneur_id;
                $type_soigneur = 'Attitre';
            }

            if ($id_pers_soin <= 0) {
                $message = 'Veuillez sélectionner un personnel valide pour ce soin.';
                $type = 'danger';
            } else {
                $st = oci_parse($conn, "SELECT NVL(MAX(id_historique_soins),0)+1 FROM Historique_soins");
                oci_execute($st);
                $r = oci_fetch_array($st, OCI_NUM);
                oci_free_statement($st);
                $id_hist = (int)($r[0] ?? 1);

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
                oci_bind_by_name($st, ':id', $id_hist);
                oci_bind_by_name($st, ':ts', $type_soigneur);
                oci_bind_by_name($st, ':ds', $date_soin);
                oci_bind_by_name($st, ':id_soin', $id_soin);
                oci_bind_by_name($st, ':ip', $id_pers_soin);
                oci_bind_by_name($st, ':rf', $rfid);

                $ok = oci_execute($st);
                oci_free_statement($st);

                $message = $ok ? 'Soin enregistré.' : 'Erreur lors de l\'enregistrement du soin.';
                $type = $ok ? 'success' : 'danger';
            }
        }
    }
}

/* =========================
   AJOUTER UN NOURRISSAGE
========================= */
if ($peut_modifier && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ajouter_nourrissage') {
    $date = trim($_POST['date_nourrissage'] ?? date('Y-m-d'));
    $dose = trim($_POST['dose_nourrissage'] ?? '');
    $aliment = trim($_POST['nom_aliment'] ?? '');
    $rem = trim($_POST['remarques_nourrissage'] ?? '');
    $idPers = (int)($_POST['id_personnel_nour'] ?? 0);
    $rfid = trim($_POST['rfid_nour'] ?? '');

    $st = oci_parse($conn, "SELECT NVL(MAX(id_nourrissage),0)+1 FROM Nourrissage");
    oci_execute($st);
    $r = oci_fetch_array($st, OCI_NUM);
    $id = (int)($r[0] ?? 1);
    oci_free_statement($st);

    $st = oci_parse($conn, "
        INSERT INTO Nourrissage(
            id_nourrissage,
            date_nourrissage,
            dose_nourrissage,
            nom_aliment,
            remarques_nourrissage,
            id_personnel,
            rfid
        ) VALUES(
            :id,
            TO_DATE(:d,'YYYY-MM-DD'),
            :do,
            :al,
            :re,
            :pe,
            :rf
        )
    ");
    oci_bind_by_name($st, ':id', $id);
    oci_bind_by_name($st, ':d', $date);
    oci_bind_by_name($st, ':do', $dose);
    oci_bind_by_name($st, ':al', $aliment);
    oci_bind_by_name($st, ':re', $rem);
    oci_bind_by_name($st, ':pe', $idPers);
    oci_bind_by_name($st, ':rf', $rfid);

    $ok = oci_execute($st);
    oci_free_statement($st);

    $message = $ok ? 'Nourrissage enregistré.' : 'Erreur nourrissage.';
    $type = $ok ? 'success' : 'danger';
}

/* =========================
   LISTE PERSONNEL
========================= */
$liste_pers = [];
$st = oci_parse($conn, "
    SELECT DISTINCT p.id_personnel, p.prenom_personnel, p.nom_personnel
    FROM Personnel p
    JOIN Historique_emploi h ON p.id_personnel = h.id_personnel
    JOIN Role r ON h.id_role = r.id_role
    WHERE h.date_fin IS NULL
      AND LOWER(r.nom_role) IN ('soigneur','soigneur chef','veterinaire')
    ORDER BY p.nom_personnel, p.prenom_personnel
");
if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) {
        $liste_pers[] = $row;
    }
    oci_free_statement($st);
}

/* =========================
   LISTE ANIMAUX + SOIGNEUR ATTITRÉ
========================= */
$animaux = [];
$st = oci_parse($conn, "
    SELECT a.rfid,
           a.nom_animal,
           a.id_personnel,
           a.id_espece,
           p.prenom_personnel,
           p.nom_personnel
    FROM Animal a
    LEFT JOIN Personnel p ON a.id_personnel = p.id_personnel
    ORDER BY a.nom_animal
");
if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) {
        $animaux[] = $row;
    }
    oci_free_statement($st);
}

/* =========================
   SOIGNEURS SPÉCIALISÉS PAR ANIMAL
========================= */
$soigneurs_specialises_par_animal = [];
foreach ($animaux as $animal_item) {
    $rfid_animal = $animal_item['RFID'] ?? '';
    $id_espece   = (int)($animal_item['ID_ESPECE'] ?? 0);
    $id_attitre  = (int)($animal_item['ID_PERSONNEL'] ?? 0);

    $soigneurs_specialises_par_animal[$rfid_animal] = [];

    if ($id_espece > 0) {
        $st = oci_parse($conn, "
            SELECT DISTINCT p.id_personnel, p.prenom_personnel, p.nom_personnel
            FROM Personnel p
            JOIN Historique_emploi h ON p.id_personnel = h.id_personnel
            JOIN Role r ON h.id_role = r.id_role
            JOIN Specialiser s ON s.id_personnel = p.id_personnel
            WHERE h.date_fin IS NULL
              AND s.id_espece = :ie
              AND LOWER(r.nom_role) IN ('soigneur', 'soigneur chef')
            ORDER BY p.nom_personnel, p.prenom_personnel
        ");
        oci_bind_by_name($st, ':ie', $id_espece);

        if ($st && oci_execute($st)) {
            while ($row = oci_fetch_assoc($st)) {
                if ((int)$row['ID_PERSONNEL'] === $id_attitre) {
                    continue;
                }
                $soigneurs_specialises_par_animal[$rfid_animal][] = $row;
            }
            oci_free_statement($st);
        }
    }
}

/* =========================
   LISTE VÉTÉRINAIRES
========================= */
$liste_veterinaires = [];
$st = oci_parse($conn, "
    SELECT DISTINCT p.id_personnel, p.prenom_personnel, p.nom_personnel
    FROM Personnel p
    JOIN Historique_emploi h ON p.id_personnel = h.id_personnel
    JOIN Role r ON h.id_role = r.id_role
    WHERE h.date_fin IS NULL
      AND LOWER(r.nom_role) = 'veterinaire'
    ORDER BY p.nom_personnel, p.prenom_personnel
");
if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) {
        $liste_veterinaires[] = $row;
    }
    oci_free_statement($st);
}

/* =========================
   SUGGESTIONS DE SOINS
========================= */
$soins_suggestions = [];
$st = oci_parse($conn, "SELECT id_soin, nom_soin, type_soin FROM Soin ORDER BY nom_soin");
if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) {
        $soins_suggestions[] = $row;
    }
    oci_free_statement($st);
}

/* =========================
   LISTE DES SOINS
========================= */
$soins = [];
$st = oci_parse($conn, "
    SELECT hs.id_historique_soins,
           hs.date_soin,
           hs.type_soigneur,
           s.nom_soin,
           s.type_soin,
           a.nom_animal,
           p.prenom_personnel,
           p.nom_personnel
    FROM Historique_soins hs
    LEFT JOIN Soin s ON hs.id_soin = s.id_soin
    LEFT JOIN Animal a ON hs.rfid = a.rfid
    LEFT JOIN Personnel p ON hs.id_personnel = p.id_personnel
    ORDER BY hs.date_soin DESC, hs.id_historique_soins DESC
");
if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) {
        $soins[] = $row;
    }
    oci_free_statement($st);
}

/* =========================
   LISTE DES NOURRISSAGES
========================= */
$nour = [];
$st = oci_parse($conn, "
    SELECT n.id_nourrissage,
           n.date_nourrissage,
           n.dose_nourrissage,
           n.nom_aliment,
           n.remarques_nourrissage,
           a.nom_animal,
           p.prenom_personnel,
           p.nom_personnel
    FROM Nourrissage n
    LEFT JOIN Animal a ON n.rfid = a.rfid
    LEFT JOIN Personnel p ON n.id_personnel = p.id_personnel
    ORDER BY n.date_nourrissage DESC, n.id_nourrissage DESC
");
if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) {
        $nour[] = $row;
    }
    oci_free_statement($st);
}

oci_close($conn);

/* =========================
   CONFIG PAGE
========================= */
$page_title = 'Soins & nourrissages';
$page_css = '/assets/css/soins.css';
$page_hero = [
    'kicker' => 'Soins & Alimentation',
    'icon'   => 'bi bi-bandaid-fill',
    'title'  => 'Soins et nourrissages',
    'desc'   => 'Suivi médical et alimentaire de tous les animaux du parc.',
    'image'  => url_site('/assets/img/care-hero.svg'),
    'actions'=> array_filter([
        ($peut_modifier || $est_soigneur) ? ['label'=>'Nouveau soin','icon'=>'bi bi-plus-lg','target'=>'#mSoin','class'=>'btn-primary'] : null,
        $peut_modifier ? ['label'=>'Nouveau nourrissage','icon'=>'bi bi-egg-fried','target'=>'#mNour','class'=>'btn-light-surface'] : null,
        ['label'=>'Dashboard','icon'=>'bi bi-arrow-left','href'=>url_site('/index.php'),'class'=>'btn-ghost'],
    ]),
    'stats' => [
        ['value'=>count($soins),'label'=>'soins'],
        ['value'=>count($nour),'label'=>'nourrissages'],
        ['value'=>count(array_filter($soins, fn($s) => ($s['TYPE_SOIN'] ?? '') === 'Complexe')),'label'=>'complexes'],
        ['value'=>count($liste_pers),'label'=>'soigneurs actifs'],
    ],
];

$hero = $page_hero ?? [];
$heroImg = $hero['image'] ?? url_site('/assets/img/dashboard-hero.svg');
$heroKicker = $hero['kicker'] ?? "Zoo'land";
$heroTitle = $hero['title'] ?? ($page_title ?? "Zoo'land");
$heroDesc = $hero['desc'] ?? '';
$heroIcon = $hero['icon'] ?? 'bi bi-grid-1x2-fill';
$heroActions = $hero['actions'] ?? [];
$heroStats = $hero['stats'] ?? [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo htmlspecialchars($page_title ?? "Zoo'land"); ?> — Zoo'land</title>
  <link href="<?php echo htmlspecialchars(url_site('/assets/vendor/bootstrap/css/bootstrap.min.css')); ?>" rel="stylesheet">
  <link href="<?php echo htmlspecialchars(url_site('/assets/css/bootstrap-icons-local.css')); ?>" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400&display=swap" rel="stylesheet">
  <link href="<?php echo htmlspecialchars(url_site('/assets/css/global.css')); ?>" rel="stylesheet">
  <?php if (!empty($page_css)): ?>
  <link href="<?php echo htmlspecialchars(url_site($page_css)); ?>" rel="stylesheet">
  <?php endif; ?>
  <style>
    .hidden-block{display:none}
  </style>
</head>
<body>
<div class="d-flex app-layout">
  <div class="app-sidebar-col"><?php include '../includes/sidebar.php'; ?></div>
  <main class="app-content-col">
    <div class="page-padding">

      <section class="page-hero reveal parallax" style="--hero-img:url('<?php echo htmlspecialchars($heroImg, ENT_QUOTES); ?>')">
        <div class="hero-pill"><i class="<?php echo htmlspecialchars($heroIcon); ?>"></i> <?php echo htmlspecialchars($heroKicker); ?></div>
        <div class="hero-grid">
          <div class="hero-copy">
            <h1 class="hero-title"><?php echo htmlspecialchars($heroTitle); ?></h1>
            <?php if ($heroDesc): ?>
              <p class="hero-desc"><?php echo htmlspecialchars($heroDesc); ?></p>
            <?php endif; ?>

            <?php if ($heroActions): ?>
            <div class="hero-actions">
              <?php foreach ($heroActions as $action): ?>
                <?php if (!$action) continue; ?>
                <?php $class = $action['class'] ?? 'btn-primary'; ?>

                <?php if (!empty($action['href'])): ?>
                  <a class="btn <?php echo htmlspecialchars($class); ?>" href="<?php echo htmlspecialchars($action['href']); ?>">
                    <?php if (!empty($action['icon'])): ?><i class="<?php echo htmlspecialchars($action['icon']); ?>"></i><?php endif; ?>
                    <?php echo htmlspecialchars($action['label'] ?? ''); ?>
                  </a>
                <?php else: ?>
                  <button class="btn <?php echo htmlspecialchars($class); ?>" type="button"
                          <?php if (!empty($action['target'])): ?>data-bs-toggle="modal" data-bs-target="<?php echo htmlspecialchars($action['target']); ?>"<?php endif; ?>>
                    <?php if (!empty($action['icon'])): ?><i class="<?php echo htmlspecialchars($action['icon']); ?>"></i><?php endif; ?>
                    <?php echo htmlspecialchars($action['label'] ?? ''); ?>
                  </button>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>

          <?php if ($heroStats): ?>
          <div class="hero-stats">
            <?php foreach ($heroStats as $stat): ?>
            <div class="hero-stat <?php echo htmlspecialchars($stat['class'] ?? ''); ?>">
              <div class="hero-stat-v"><?php echo htmlspecialchars((string)($stat['value'] ?? '')); ?></div>
              <div class="hero-stat-l"><?php echo htmlspecialchars((string)($stat['label'] ?? '')); ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </section>

      <?php render_alert($message, $type); ?>

      <div class="search-toolbar reveal mb-4">
        <div class="search-box">
          <i class="bi bi-search"></i>
          <input type="search" id="rechercheSoins" class="search-input" placeholder="Rechercher un soin, un nourrissage, un animal ou un soigneur...">
        </div>
        <div>
          <select id="filtreTypeSoin" class="form-select search-select">
            <option value="">Tous les soins</option>
            <option value="simple">Simple</option>
            <option value="complexe">Complexe</option>
          </select>
        </div>
      </div>

      <div class="care-tabs">
        <button class="care-tab active" id="tabS" type="button" onclick="switchTab('s')">
          <i class="bi bi-bandaid-fill me-1"></i> Soins
          <span class="badge-soft badge-emerald ms-2" style="font-size:.68rem"><?php echo count($soins); ?></span>
        </button>
        <button class="care-tab" id="tabN" type="button" onclick="switchTab('n')">
          <i class="bi bi-egg-fried me-1"></i> Nourrissages
          <span class="badge-soft badge-amber ms-2" style="font-size:.68rem"><?php echo count($nour); ?></span>
        </button>
      </div>

      <!-- Liste soins -->
      <div class="care-grid" id="panelS" data-filter-group="cs">
        <?php foreach ($soins as $s): ?>
          <?php
            $cplx = ($s['TYPE_SOIN'] ?? '') === 'Complexe';
            $ts = strtolower($s['TYPE_SOIGNEUR'] ?? '');
            $bclass = str_contains($ts, 'vet') ? 'bsoin-vet' : (str_contains($ts, 'rem') ? 'bsoin-rem' : 'bsoin-att');
            $blabel = str_contains($ts, 'vet') ? 'Vétérinaire' : (str_contains($ts, 'rem') ? 'Remplaçant' : 'Attitré');
            $agent = trim(($s['PRENOM_PERSONNEL'] ?? '') . ' ' . ($s['NOM_PERSONNEL'] ?? '')) ?: '—';
          ?>
          <article class="care-card" data-filter-item
                   data-type="<?php echo htmlspecialchars(strtolower((string)($s['TYPE_SOIN'] ?? ''))); ?>"
                   data-search="<?php echo htmlspecialchars(strtolower(($s['NOM_SOIN'] ?? '') . ' ' . ($s['NOM_ANIMAL'] ?? '') . ' ' . $agent)); ?>">
            <div class="care-card-bar <?php echo $cplx ? 'cc-bar-complexe' : 'cc-bar-simple'; ?>"></div>
            <div class="care-card-body">
              <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.6rem">
                <div>
                  <div class="care-title"><?php echo htmlspecialchars($s['NOM_SOIN'] ?? 'Soin'); ?></div>
                  <div class="care-animal">
                    <i class="bi bi-heart-pulse-fill" style="color:var(--rose);font-size:.72rem"></i>
                    <?php echo htmlspecialchars($s['NOM_ANIMAL'] ?? '—'); ?>
                  </div>
                </div>
                <span class="badge-soft <?php echo $cplx ? 'badge-rose' : 'badge-emerald'; ?>">
                  <?php echo $cplx ? 'Complexe' : 'Simple'; ?>
                </span>
              </div>

              <div class="care-rows">
                <div class="mini-row"><span><i class="bi bi-calendar3 me-1"></i>Date</span><strong><?php echo format_date_fr($s['DATE_SOIN']); ?></strong></div>
                <div class="mini-row"><span><i class="bi bi-person-fill me-1"></i>Soigneur</span><strong><?php echo htmlspecialchars($agent); ?></strong></div>
                <div class="mini-row"><span>Type</span><strong><span class="bsoin <?php echo $bclass; ?>"><?php echo $blabel; ?></span></strong></div>
              </div>

              <div class="care-footer">
                <a class="btn btn-primary" style="font-size:.82rem;padding:.52rem .9rem"
                   href="<?php echo htmlspecialchars(url_site('/soins/detail.php?id=' . $s['ID_HISTORIQUE_SOINS'])); ?>">
                  <i class="bi bi-eye-fill"></i> Détail
                </a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>

        <?php if (empty($soins)): ?>
          <div style="grid-column:1/-1;text-align:center;padding:3rem;color:var(--txt-muted)">
            <i class="bi bi-bandaid" style="font-size:2.5rem;opacity:.3;display:block;margin-bottom:.75rem"></i>
            <div style="font-weight:700">Aucun soin enregistré</div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Liste nourrissages -->
      <div class="care-grid" id="panelN" style="display:none" data-filter-group="cn">
        <?php foreach ($nour as $n): ?>
          <?php $agent = trim(($n['PRENOM_PERSONNEL'] ?? '') . ' ' . ($n['NOM_PERSONNEL'] ?? '')) ?: '—'; ?>
          <article class="care-card" data-filter-item
                   data-search="<?php echo htmlspecialchars(strtolower(($n['NOM_ALIMENT'] ?? '') . ' ' . ($n['NOM_ANIMAL'] ?? '') . ' ' . $agent)); ?>">
            <div class="care-card-bar cc-bar-nourr"></div>
            <div class="care-card-body">
              <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.6rem">
                <div>
                  <div class="care-title"><?php echo htmlspecialchars($n['NOM_ALIMENT'] ?? 'Repas'); ?></div>
                  <div class="care-animal">
                    <i class="bi bi-heart-pulse-fill" style="color:var(--rose);font-size:.72rem"></i>
                    <?php echo htmlspecialchars($n['NOM_ANIMAL'] ?? '—'); ?>
                  </div>
                </div>
                <span class="badge-soft badge-amber"><?php echo htmlspecialchars($n['DOSE_NOURRISSAGE'] ?? ''); ?> kg</span>
              </div>

              <div class="care-rows">
                <div class="mini-row"><span><i class="bi bi-calendar3 me-1"></i>Date</span><strong><?php echo format_date_fr($n['DATE_NOURRISSAGE']); ?></strong></div>
                <div class="mini-row"><span><i class="bi bi-person-fill me-1"></i>Agent</span><strong><?php echo htmlspecialchars($agent); ?></strong></div>
                <?php if (!empty($n['REMARQUES_NOURRISSAGE'])): ?>
                  <div class="mini-row"><span>Remarques</span><strong><?php echo htmlspecialchars($n['REMARQUES_NOURRISSAGE']); ?></strong></div>
                <?php endif; ?>
              </div>

              <div class="care-footer">
                <a class="btn btn-primary" style="font-size:.82rem;padding:.52rem .9rem"
                   href="<?php echo htmlspecialchars(url_site('/soins/nourrissage_detail.php?id=' . $n['ID_NOURRISSAGE'])); ?>">
                  <i class="bi bi-eye-fill"></i> Détail
                </a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>

        <?php if (empty($nour)): ?>
          <div style="grid-column:1/-1;text-align:center;padding:3rem;color:var(--txt-muted)">
            <i class="bi bi-egg-fried" style="font-size:2.5rem;opacity:.3;display:block;margin-bottom:.75rem"></i>
            <div style="font-weight:700">Aucun nourrissage enregistré</div>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($peut_modifier || $est_soigneur): ?>
      <!-- Modal ajout soin -->
      <div class="modal fade" id="mSoin" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title fw-bold"><i class="bi bi-bandaid-fill me-2"></i>Enregistrer un soin</h5>
              <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
            </div>
            <div class="modal-body">
              <form method="POST">
                <input type="hidden" name="action" value="ajouter_soin">

                <div class="mb-3">
                  <label class="form-label">Animal</label>
                  <select class="form-select" name="rfid" id="rfid_soin" required>
                    <option value="">— Choisir —</option>
                    <?php foreach ($animaux as $a): ?>
                      <?php
                        $rfid_animal = $a['RFID'] ?? '';
                        $attitre_nom = trim(($a['PRENOM_PERSONNEL'] ?? '') . ' ' . ($a['NOM_PERSONNEL'] ?? ''));
                      ?>
                      <option
                        value="<?php echo htmlspecialchars($rfid_animal); ?>"
                        data-attitre-id="<?php echo (int)($a['ID_PERSONNEL'] ?? 0); ?>"
                        data-attitre-nom="<?php echo htmlspecialchars($attitre_nom !== '' ? $attitre_nom : 'Non assigné'); ?>"
                      >
                        <?php echo htmlspecialchars($a['NOM_ANIMAL'] ?? $rfid_animal); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

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
                    <select class="form-select" id="soigneur_attitre_affichage" disabled>
                      <option>Choisissez d'abord un animal</option>
                    </select>
                  </div>

                  <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="checkAbsent" name="soigneur_absent" value="1">
                    <label class="form-check-label" for="checkAbsent">Soigneur absent — désigner un remplaçant</label>
                  </div>

                  <div class="mb-3 hidden-block" id="blocRemplacant">
                    <label class="form-label">Soigneur remplaçant</label>
                    <select name="id_soigneur_remplacant" id="id_soigneur_remplacant" class="form-select">
                      <option value="">— Choisir —</option>
                    </select>
                  </div>
                </div>

                <div class="mb-3 hidden-block" id="blocVeterinaire">
                  <label class="form-label">Vétérinaire</label>
                  <select name="id_veterinaire" class="form-select">
                    <option value="">— Choisir —</option>
                    <?php foreach ($liste_veterinaires as $v): ?>
                      <option value="<?php echo $v['ID_PERSONNEL']; ?>">
                        Dr. <?php echo htmlspecialchars($v['PRENOM_PERSONNEL'] . ' ' . $v['NOM_PERSONNEL']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                  <button type="button" class="btn btn-light-surface" data-bs-dismiss="modal">Annuler</button>
                  <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Enregistrer</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($peut_modifier): ?>
      <!-- Modal ajout nourrissage -->
      <div class="modal fade" id="mNour" tabindex="-1">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title fw-bold"><i class="bi bi-egg-fried me-2"></i>Enregistrer un nourrissage</h5>
              <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
            </div>
            <div class="modal-body">
              <form method="POST">
                <input type="hidden" name="action" value="ajouter_nourrissage">

                <div class="grid-auto">
                  <div>
                    <label class="form-label">Animal</label>
                    <select class="form-select" name="rfid_nour" required>
                      <option value="">— Choisir —</option>
                      <?php foreach ($animaux as $a): ?>
                        <option value="<?php echo htmlspecialchars($a['RFID']); ?>">
                          <?php echo htmlspecialchars($a['NOM_ANIMAL'] ?? $a['RFID']); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div>
                    <label class="form-label">Agent</label>
                    <select class="form-select" name="id_personnel_nour" required>
                      <option value="">— Choisir —</option>
                      <?php foreach ($liste_pers as $p): ?>
                        <option value="<?php echo $p['ID_PERSONNEL']; ?>">
                          <?php echo htmlspecialchars($p['PRENOM_PERSONNEL'] . ' ' . $p['NOM_PERSONNEL']); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div>
                    <label class="form-label">Date</label>
                    <input class="form-control" type="date" name="date_nourrissage" value="<?php echo date('Y-m-d'); ?>" required>
                  </div>

                  <div>
                    <label class="form-label">Aliment</label>
                    <input class="form-control" name="nom_aliment" required>
                  </div>

                  <div>
                    <label class="form-label">Dose (kg)</label>
                    <input class="form-control" name="dose_nourrissage" placeholder="2.5">
                  </div>

                  <div style="grid-column:1/-1">
                    <label class="form-label">Remarques</label>
                    <textarea class="form-control" name="remarques_nourrissage" rows="2"></textarea>
                  </div>
                </div>

                <div class="action-row justify-content-end">
                  <button type="button" class="btn btn-light-surface" data-bs-dismiss="modal">Annuler</button>
                  <button class="btn btn-primary" type="submit">Enregistrer</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </main>
</div>

<script src="<?php echo htmlspecialchars(url_site('/assets/vendor/bootstrap/js/bootstrap.bundle.min.js')); ?>"></script>

<script>
const soigneursSpecialisesParAnimal = <?php echo json_encode($soigneurs_specialises_par_animal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

function switchTab(t) {
  var isSoin = t === 's';
  document.getElementById('panelS').style.display = isSoin ? 'grid' : 'none';
  document.getElementById('panelN').style.display = isSoin ? 'none' : 'grid';
  document.getElementById('tabS').classList.toggle('active', isSoin);
  document.getElementById('tabN').classList.toggle('active', !isSoin);
  appliquerFiltresSoins();
}

function appliquerFiltresSoins() {
  var recherche = (document.getElementById('rechercheSoins').value || '').toLowerCase();
  var type = (document.getElementById('filtreTypeSoin').value || '').toLowerCase();

  document.querySelectorAll('#panelS [data-filter-item]').forEach(function(card) {
    var texte = (card.dataset.search || '').toLowerCase();
    var typeCarte = (card.dataset.type || '').toLowerCase();
    var visible = texte.indexOf(recherche) !== -1;
    if (visible && type !== '' && typeCarte !== type) visible = false;
    card.style.display = visible ? '' : 'none';
  });

  document.querySelectorAll('#panelN [data-filter-item]').forEach(function(card) {
    var texte = (card.dataset.search || '').toLowerCase();
    var visible = texte.indexOf(recherche) !== -1;
    card.style.display = visible ? '' : 'none';
  });
}

function remplirInfosAnimalSoin() {
  const selectAnimal = document.getElementById('rfid_soin');
  const selectAffichage = document.getElementById('soigneur_attitre_affichage');
  const selectRemplacant = document.getElementById('id_soigneur_remplacant');

  if (!selectAnimal || !selectAffichage || !selectRemplacant) return;

  const option = selectAnimal.options[selectAnimal.selectedIndex];
  const rfid = selectAnimal.value;
  const attitreNom = option ? (option.getAttribute('data-attitre-nom') || 'Non assigné') : 'Non assigné';

  selectAffichage.innerHTML = '';
  const op = document.createElement('option');
  op.textContent = attitreNom;
  selectAffichage.appendChild(op);

  selectRemplacant.innerHTML = '<option value="">— Choisir —</option>';

  if (rfid && soigneursSpecialisesParAnimal[rfid]) {
    soigneursSpecialisesParAnimal[rfid].forEach(function(p) {
      const o = document.createElement('option');
      o.value = p.ID_PERSONNEL;
      o.textContent = (p.PRENOM_PERSONNEL || '') + ' ' + (p.NOM_PERSONNEL || '');
      selectRemplacant.appendChild(o);
    });
  }
}

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

  if (!isC && blocRemplacant) {
    blocRemplacant.classList.toggle('hidden-block', !isA);
  } else if (blocRemplacant) {
    blocRemplacant.classList.add('hidden-block');
  }
}

document.getElementById('rechercheSoins')?.addEventListener('input', appliquerFiltresSoins);
document.getElementById('filtreTypeSoin')?.addEventListener('change', appliquerFiltresSoins);
document.getElementById('rfid_soin')?.addEventListener('change', remplirInfosAnimalSoin);
document.getElementById('checkComplexe')?.addEventListener('change', toggleSoinUI);
document.getElementById('checkAbsent')?.addEventListener('change', toggleSoinUI);

document.querySelectorAll('.reveal').forEach(function(el, i) {
  setTimeout(function() {
    el.classList.add('visible');
  }, 80 + i * 35);
});

remplirInfosAnimalSoin();
toggleSoinUI();
</script>
</body>
</html>
