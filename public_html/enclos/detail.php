<?php
// Vérifie la connexion et les droits
require_once '../includes/auth.php';
require_role(['admin','dirigeant','soigneur','soigneur_chef','technicien']);

// Fichiers utiles
require_once '../includes/path.php';
require_once '../includes/helpers.php';
require_once '../config.php';

// Vérifie si l'utilisateur peut modifier
$peut_modifier = in_array(get_role(), ['admin','dirigeant'], true);

// Récupère l'id de l'enclos
$id = (int)($_GET['id'] ?? 0);

// Redirection si id invalide
if ($id <= 0) {
    header('Location: ' . url_site('/enclos/index.php'));
    exit;
}

// Message de retour
$message = '';
$type = 'success';


/* =========================
   MODIFICATION DE L'ENCLOS
========================= */
if ($peut_modifier && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'modifier') {
    // Récupère les données du formulaire
    $surface = (float)($_POST['surface'] ?? 0);
    $lat     = $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null;
    $lng     = $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;
    $part    = trim($_POST['particularites'] ?? '');
    $id_zone = (int)($_POST['id_zone'] ?? 0);

    // Met à jour l'enclos
    $st = oci_parse($conn, "UPDATE Enclos SET surface=:s, latitude=:la, longitude=:lo, particularites=:p, id_zone=:z WHERE id_enclos=:id");
    oci_bind_by_name($st, ':s', $surface);
    oci_bind_by_name($st, ':la', $lat);
    oci_bind_by_name($st, ':lo', $lng);
    oci_bind_by_name($st, ':p', $part);
    oci_bind_by_name($st, ':z', $id_zone);
    oci_bind_by_name($st, ':id', $id);

    $ok = oci_execute($st);
    if ($st) oci_free_statement($st);

    // Message résultat
    $message = $ok ? 'Enclos mis à jour.' : 'Erreur de mise à jour.';
    $type = $ok ? 'success' : 'danger';
}


/* =========================
   LISTE DES ZONES
========================= */
$zones = [];
$st = oci_parse($conn, "SELECT id_zone,nom_zone FROM Zone ORDER BY nom_zone");
if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) $zones[] = $row;
    oci_free_statement($st);
}


/* =========================
   INFOS DE L'ENCLOS
========================= */
$st = oci_parse($conn, "SELECT en.*, z.nom_zone FROM Enclos en LEFT JOIN Zone z ON en.id_zone=z.id_zone WHERE en.id_enclos=:id");
oci_bind_by_name($st, ':id', $id);
oci_execute($st);
$enc = oci_fetch_assoc($st);
if ($st) oci_free_statement($st);

// Redirection si l'enclos n'existe pas
if (!$enc) {
    header('Location: ' . url_site('/enclos/index.php'));
    exit;
}


/* =========================
   ANIMAUX DE L'ENCLOS
========================= */
$animaux = [];
$st = oci_parse($conn, "SELECT a.rfid,a.nom_animal,e.nom_usuel,p.prenom_personnel,p.nom_personnel
                        FROM Animal a
                        LEFT JOIN Espece e ON a.id_espece=e.id_espece
                        LEFT JOIN Personnel p ON a.id_personnel=p.id_personnel
                        WHERE a.id_enclos=:id
                        ORDER BY a.nom_animal");
oci_bind_by_name($st, ':id', $id);
if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) $animaux[] = $row;
    oci_free_statement($st);
}


/* =========================
   RÉPARATIONS DE L'ENCLOS
========================= */
$reparations = [];
$st = oci_parse($conn, "SELECT id_reparation,date_reparation,nature
                        FROM Reparation
                        WHERE id_enclos=:id
                        ORDER BY date_reparation DESC
                        FETCH FIRST 10 ROWS ONLY");
oci_bind_by_name($st, ':id', $id);
if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) $reparations[] = $row;
    oci_free_statement($st);
}


/* =========================
   SOINS LIÉS À L'ENCLOS
========================= */
$soins = [];
$st = oci_parse($conn, "SELECT hs.id_soin, hs.date_soin, s.nom_soin, a.nom_animal, a.rfid
                        FROM Historique_soins hs
                        JOIN Animal a ON hs.rfid = a.rfid
                        LEFT JOIN Soin s ON hs.id_soin = s.id_soin
                        WHERE a.id_enclos = :id
                        ORDER BY hs.date_soin DESC
                        FETCH FIRST 10 ROWS ONLY");
oci_bind_by_name($st, ':id', $id);
if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) $soins[] = $row;
    oci_free_statement($st);
}

// Ferme la connexion Oracle
oci_close($conn);


/* =========================
   CONFIG PAGE
========================= */
$page_title = 'Détail enclos';
$page_css = '/assets/css/detail.css';

// Données du hero
$page_hero = [
    'kicker' => 'Fiche habitat',
    'icon'   => 'bi bi-geo-alt-fill',
    'title'  => 'Enclos #' . $id,
    'desc'   => 'Vue complète de l\'habitat, des animaux présents, des soins liés et des réparations.',
    'image'  => url_site('/assets/img/enclosures-hero.svg'),
    'actions' => array_filter([
        $peut_modifier ? ['label'=>'Modifier','icon'=>'bi bi-pencil-fill','target'=>'#modalEdit','class'=>'btn-primary'] : null,
        ['label'=>'Retour','icon'=>'bi bi-arrow-left','href'=>url_site('/enclos/index.php'),'class'=>'btn-ghost']
    ]),
    'stats' => [
        ['value'=>number_format((float)$enc['SURFACE'],0,',',' ') . ' m²','label'=>'surface'],
        ['value'=>count($animaux),'label'=>'animaux'],
        ['value'=>count($soins),'label'=>'soins récents'],
        ['value'=>count($reparations),'label'=>'réparations']
    ]
];
?>

<?php
// Prépare les variables du hero
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

  <!-- Responsive -->
  <meta name="viewport" content="width=device-width,initial-scale=1">

  <!-- Titre page -->
  <title><?php echo htmlspecialchars($page_title ?? "Zoo'land"); ?> — Zoo'land</title>

  <!-- CSS -->
  <link href="<?php echo htmlspecialchars(url_site('/assets/vendor/bootstrap/css/bootstrap.min.css')); ?>" rel="stylesheet">
  <link href="<?php echo htmlspecialchars(url_site('/assets/css/bootstrap-icons-local.css')); ?>" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400&display=swap" rel="stylesheet">
  <link href="<?php echo htmlspecialchars(url_site('/assets/css/global.css')); ?>" rel="stylesheet">

  <?php if (!empty($page_css)): ?>
  <link href="<?php echo htmlspecialchars(url_site($page_css)); ?>" rel="stylesheet">
  <?php endif; ?>
</head>
<body>

<div class="d-flex app-layout">
  <!-- Sidebar -->
  <div class="app-sidebar-col"><?php include '../includes/sidebar.php'; ?></div>

  <!-- Contenu principal -->
  <main class="app-content-col">
    <div class="page-padding">

      <!-- Hero -->
      <section class="page-hero reveal parallax" style="--hero-img:url('<?php echo htmlspecialchars($heroImg, ENT_QUOTES); ?>')">
        <div class="hero-pill">
          <i class="<?php echo htmlspecialchars($heroIcon); ?>"></i>
          <?php echo htmlspecialchars($heroKicker); ?>
        </div>

        <div class="hero-grid">
          <div class="hero-copy">
            <h1 class="hero-title"><?php echo $heroTitle; ?></h1>

            <?php if ($heroDesc): ?>
              <p class="hero-desc"><?php echo htmlspecialchars($heroDesc); ?></p>
            <?php endif; ?>

            <?php if ($heroActions): ?>
            <div class="hero-actions">
              <?php foreach ($heroActions as $action): if (!$action) continue; $class = $action['class'] ?? 'btn-primary'; ?>
                <?php if (!empty($action['href'])): ?>
                  <a class="btn <?php echo htmlspecialchars($class); ?>" href="<?php echo htmlspecialchars($action['href']); ?>">
                    <?php if (!empty($action['icon'])): ?>
                      <i class="<?php echo htmlspecialchars($action['icon']); ?>"></i>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($action['label'] ?? ''); ?>
                  </a>
                <?php else: ?>
                  <button class="btn <?php echo htmlspecialchars($class); ?>" type="button"<?php if (!empty($action['target'])): ?> data-bs-toggle="modal" data-bs-target="<?php echo htmlspecialchars($action['target']); ?>"<?php endif; ?>>
                    <?php if (!empty($action['icon'])): ?>
                      <i class="<?php echo htmlspecialchars($action['icon']); ?>"></i>
                    <?php endif; ?>
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

<?php
// Alerte succès / erreur
render_alert($message, $type);
?>

<div class="detail-shell reveal">
  <!-- Bloc principal -->
  <section class="detail-panel">
    <!-- Informations de l'enclos -->
    <div class="kv-grid">
      <div class="kv-card">
        <div class="kv-label">Zone</div>
        <div class="kv-value"><?php echo htmlspecialchars($enc['NOM_ZONE'] ?? '—'); ?></div>
      </div>

      <div class="kv-card">
        <div class="kv-label">Latitude</div>
        <div class="kv-value"><?php echo $enc['LATITUDE'] !== null ? htmlspecialchars((string)$enc['LATITUDE']) : '—'; ?></div>
      </div>

      <div class="kv-card">
        <div class="kv-label">Longitude</div>
        <div class="kv-value"><?php echo $enc['LONGITUDE'] !== null ? htmlspecialchars((string)$enc['LONGITUDE']) : '—'; ?></div>
      </div>

      <div class="kv-card">
        <div class="kv-label">Particularités</div>
        <div class="kv-value"><?php echo !empty($enc['PARTICULARITES']) ? htmlspecialchars($enc['PARTICULARITES']) : '—'; ?></div>
      </div>
    </div>

    <!-- Liste des animaux -->
    <div class="page-header-row mt-4">
      <div>
        <div class="overline">Animaux</div>
        <h2 class="page-title-sm">Résidents de cet enclos</h2>
      </div>
    </div>

    <div class="stack-list">
      <?php if(!$animaux): ?>
        <div class="stack-item text-muted">Aucun animal dans cet enclos.</div>
      <?php endif; ?>

      <?php foreach($animaux as $a): ?>
        <div class="stack-item">
          <strong>
            <a href="<?php echo htmlspecialchars(url_site('/animaux/detail.php?rfid=' . urlencode($a['RFID']))); ?>" class="text-decoration-none">
              <?php echo htmlspecialchars($a['NOM_ANIMAL']); ?>
            </a>
          </strong>
          <div class="text-muted mt-1">
            <?php echo htmlspecialchars($a['NOM_USUEL'] ?? ''); ?>
            — Soigneur:
            <?php echo htmlspecialchars(trim(($a['PRENOM_PERSONNEL'] ?? '') . ' ' . ($a['NOM_PERSONNEL'] ?? '')) ?: '—'); ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Bloc suivi -->
  <section class="detail-panel">
    <div class="page-header-row">
      <div>
        <div class="overline">Suivi</div>
        <h2 class="page-title-sm">Soins et réparations</h2>
      </div>
    </div>

    <div class="stack-list">
      <!-- Soins -->
      <div class="stack-item">
        <strong>Derniers soins</strong>

        <?php if(!$soins): ?>
          <div class="text-muted mt-1">Aucun soin lié.</div>
        <?php endif; ?>

        <?php foreach($soins as $s): ?>
          <div class="mini-row mt-2">
            <span>
              <a href="<?php echo htmlspecialchars(url_site('/soins/detail.php?id=' . (int)$s['ID_SOIN'])); ?>" class="text-decoration-none">
                <?php echo htmlspecialchars($s['NOM_SOIN'] ?? 'Soin'); ?>
              </a>
              —
              <a href="<?php echo htmlspecialchars(url_site('/animaux/detail.php?rfid=' . urlencode($s['RFID']))); ?>" class="text-decoration-none">
                <?php echo htmlspecialchars($s['NOM_ANIMAL'] ?? ''); ?>
              </a>
            </span>
            <strong><?php echo format_date_fr($s['DATE_SOIN']); ?></strong>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Réparations -->
      <div class="stack-item">
        <strong>Dernières réparations</strong>

        <?php if(!$reparations): ?>
          <div class="text-muted mt-1">Aucune réparation liée.</div>
        <?php endif; ?>

        <?php foreach($reparations as $r): ?>
          <div class="mini-row mt-2">
            <span>
              <a href="<?php echo htmlspecialchars(url_site('/reparations/detail.php?id=' . (int)$r['ID_REPARATION'])); ?>" class="text-decoration-none">
                <?php echo htmlspecialchars($r['NATURE']); ?>
              </a>
            </span>
            <strong><?php echo format_date_fr($r['DATE_REPARATION']); ?></strong>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</div>

<?php if($peut_modifier): ?>
<!-- Modal modification -->
<div class="modal fade" id="modalEdit" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Modifier l'enclos</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body" id="edition">
        <form method="POST">
          <input type="hidden" name="action" value="modifier">

          <div class="grid-auto">
            <div>
              <label class="form-label">Surface</label>
              <input class="form-control" type="number" step="0.01" name="surface" value="<?php echo htmlspecialchars((string)$enc['SURFACE']); ?>" required>
            </div>

            <div>
              <label class="form-label">Zone</label>
              <select class="form-select" name="id_zone" required>
                <?php foreach($zones as $z): ?>
                  <option value="<?php echo $z['ID_ZONE']; ?>" <?php echo ((int)$z['ID_ZONE'] === (int)$enc['ID_ZONE']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($z['NOM_ZONE']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div>
              <label class="form-label">Latitude</label>
              <input class="form-control" type="number" step="0.000001" name="latitude" value="<?php echo htmlspecialchars((string)$enc['LATITUDE']); ?>">
            </div>

            <div>
              <label class="form-label">Longitude</label>
              <input class="form-control" type="number" step="0.000001" name="longitude" value="<?php echo htmlspecialchars((string)$enc['LONGITUDE']); ?>">
            </div>

            <div style="grid-column:1/-1">
              <label class="form-label">Particularités</label>
              <textarea class="form-control" name="particularites" rows="3"><?php echo htmlspecialchars($enc['PARTICULARITES'] ?? ''); ?></textarea>
            </div>
          </div>

          <div class="action-row justify-content-end">
            <button type="button" class="btn btn-light-surface" data-bs-dismiss="modal">Annuler</button>
            <button class="btn btn-primary">
              <i class="bi bi-check2-circle"></i> Enregistrer
            </button>
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

<!-- Bootstrap JS -->
<script src="<?php echo htmlspecialchars(url_site('/assets/vendor/bootstrap/js/bootstrap.bundle.min.js')); ?>"></script>

<!-- Animations -->
<script>
document.querySelectorAll('.reveal').forEach(function(el,i){
  setTimeout(function(){
    el.classList.add('visible');
  },80+i*35);
});

document.querySelectorAll('[data-toggle-extern]').forEach(function(box){
  function sync(){
    var t = document.getElementById(box.dataset.toggleExtern);
    if(!t) return;

    document.querySelectorAll(box.dataset.target).forEach(function(el){
      el.style.display = t.checked ? '' : 'none';
    });

    document.querySelectorAll(box.dataset.altTarget).forEach(function(el){
      el.style.display = t.checked ? 'none' : '';
    });
  }

  var target = document.getElementById(box.dataset.toggleExtern);
  if(target){
    target.addEventListener('change', sync);
    sync();
  }
});
</script>
</body>
</html>
