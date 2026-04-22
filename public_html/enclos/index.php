<?php
// Vérifie la connexion et les droits
require_once '../includes/auth.php';
require_role(['admin','dirigeant','soigneur','soigneur_chef','technicien']);

// Fichiers utiles
require_once '../includes/path.php';
require_once '../includes/helpers.php';
require_once '../config.php';

// Vérifie si l'utilisateur peut ajouter ou supprimer
$peut_modifier = in_array(get_role(), ['admin','dirigeant'], true);

// Message de retour
$message = '';
$type = 'success';


/* =========================
   AJOUT D'UN ENCLOS
========================= */
if ($peut_modifier && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ajouter') {
    // Récupère les données du formulaire
    $surface = (float)($_POST['surface'] ?? 0);
    $lat     = $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null;
    $lng     = $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;
    $part    = trim($_POST['particularites'] ?? '');
    $id_zone = (int)($_POST['id_zone'] ?? 0);

    // Génère le prochain id
    $st = oci_parse($conn, "SELECT NVL(MAX(id_enclos),0)+1 FROM Enclos");
    oci_execute($st);
    $r = oci_fetch_array($st, OCI_NUM);
    $nxt = (int)($r[0] ?? 1);
    oci_free_statement($st);

    // Ajoute l'enclos en base
    $st = oci_parse($conn, "INSERT INTO Enclos(id_enclos,surface,latitude,longitude,particularites,id_zone) VALUES(:id,:s,:la,:lo,:p,:z)");
    oci_bind_by_name($st, ':id', $nxt);
    oci_bind_by_name($st, ':s', $surface);
    oci_bind_by_name($st, ':la', $lat);
    oci_bind_by_name($st, ':lo', $lng);
    oci_bind_by_name($st, ':p', $part);
    oci_bind_by_name($st, ':z', $id_zone);

    $ok = oci_execute($st);
    if ($st) oci_free_statement($st);

    // Message résultat
    $message = $ok ? 'Enclos ajouté.' : 'Erreur lors de l\'ajout.';
    $type = $ok ? 'success' : 'danger';
}


/* =========================
   SUPPRESSION D'UN ENCLOS
========================= */
if ($peut_modifier && isset($_GET['supprimer'])) {
    // Récupère l'id à supprimer
    $id = (int)$_GET['supprimer'];

    // Supprime l'enclos
    $st = oci_parse($conn, "DELETE FROM Enclos WHERE id_enclos=:id");
    oci_bind_by_name($st, ':id', $id);

    $ok = oci_execute($st);
    if ($st) oci_free_statement($st);

    // Message résultat
    $message = $ok ? 'Enclos supprimé.' : 'Erreur lors de la suppression.';
    $type = $ok ? 'success' : 'danger';
}


/* =========================
   RÉCUPÉRATION DES ZONES
========================= */
$zones = [];
$st = oci_parse($conn, "SELECT id_zone,nom_zone FROM Zone ORDER BY nom_zone");
if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) $zones[] = $row;
    oci_free_statement($st);
}


/* =========================
   RÉCUPÉRATION DES ENCLOS
========================= */
$enclos = [];
$sql = "SELECT en.id_enclos,en.surface,en.latitude,en.longitude,en.particularites,z.id_zone,z.nom_zone,
             (SELECT COUNT(*) FROM Animal a WHERE a.id_enclos=en.id_enclos) nb_animaux,
             (SELECT COUNT(*) FROM Reparation r WHERE r.id_enclos=en.id_enclos) nb_reparations,
             (SELECT COUNT(*) FROM Historique_soins hs JOIN Animal a ON hs.rfid=a.rfid WHERE a.id_enclos=en.id_enclos) nb_soins
      FROM Enclos en LEFT JOIN Zone z ON en.id_zone=z.id_zone ORDER BY z.nom_zone,en.id_enclos";

$st = oci_parse($conn, $sql);
if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) $enclos[] = $row;
    oci_free_statement($st);
}


/* =========================
   REGROUPEMENT PAR ZONE
========================= */
$byZone = [];
foreach ($enclos as $e) {
    $key = $e['NOM_ZONE'] ?: 'Sans zone';
    $byZone[$key][] = $e;
}

// Ferme la connexion Oracle
oci_close($conn);


/* =========================
   CONFIGURATION DE LA PAGE
========================= */
$page_title = 'Enclos';
$page_css = '/assets/css/enclos.css';

$page_hero = [
  'kicker' => 'Habitats du parc',
  'icon'   => 'bi bi-geo-alt-fill',
  'title'  => 'Enclos ',
  'desc'   => 'Accédez à chaque enclos via une fiche détaillée avec toutes les informations utiles et un lien direct vers les zones.',
  'image'  => url_site('/assets/img/enclosures-hero.svg'),
  'actions' => array_filter([
    $peut_modifier ? ['label'=>'Ajouter un enclos','icon'=>'bi bi-plus-lg','target'=>'#modalAjouter','class'=>'btn-primary'] : null,
    ['label'=>'Gérer les zones','icon'=>'bi bi-map-fill','href'=>url_site('/zones/index.php'),'class'=>'btn-light-surface'],
    ['label'=>'Dashboard','icon'=>'bi bi-arrow-left','href'=>url_site('/index.php'),'class'=>'btn-ghost'],
  ]),
  'stats' => [
    ['value'=>count($enclos),'label'=>'enclos'],
    ['value'=>count($zones),'label'=>'zones'],
    ['value'=>array_sum(array_map(fn($e)=>(int)$e['NB_ANIMAUX'],$enclos)),'label'=>'animaux hébergés'],
    ['value'=>array_sum(array_map(fn($e)=>(int)$e['NB_REPARATIONS'],$enclos)),'label'=>'réparations liées']
  ]
];
?>

<?php
// Prépare les données du hero
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

  <!-- Titre -->
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

<!-- Barre de recherche -->
<div class="search-toolbar reveal mb-4">
  <div class="search-box">
    <i class="bi bi-search"></i>
    <input type="search" id="rechercheEnclos" class="search-input" placeholder="Rechercher un enclos, une zone ou une particularité...">
  </div>
</div>

<!-- Affichage des enclos par zone -->
<?php foreach($byZone as $zoneName => $items): ?>
<section class="zone-block reveal" data-zone="<?php echo htmlspecialchars(strtolower($zoneName), ENT_QUOTES); ?>">
  <div class="zone-head">
    <div>
      <div class="overline">Zone</div>
      <h2 class="zone-name"><?php echo htmlspecialchars($zoneName); ?></h2>
    </div>
    <span class="map-badge">
      <i class="bi bi-grid-3x3-gap-fill"></i>
      <?php echo count($items); ?> enclos
    </span>
  </div>

  <div class="enclos-grid">
    <?php foreach($items as $enc): ?>
    <article class="enclos-card" data-search="<?php echo htmlspecialchars(strtolower(trim(($zoneName ?? '') . ' ' . ($enc['ID_ENCLOS'] ?? '') . ' ' . ($enc['PARTICULARITES'] ?? '') . ' ' . ($enc['SURFACE'] ?? '') . ' ' . ($enc['LATITUDE'] ?? '') . ' ' . ($enc['LONGITUDE'] ?? ''))), ENT_QUOTES); ?>">
      <div class="item-top">
        <div>
          <h3 class="item-title">Enclos #<?php echo $enc['ID_ENCLOS']; ?></h3>
          <div class="item-sub"><?php echo number_format((float)$enc['SURFACE'],0,',',' '); ?> m²</div>
        </div>

        <span class="badge-soft badge-emerald">
          <i class="bi bi-heart-pulse-fill"></i>
          <?php echo (int)$enc['NB_ANIMAUX']; ?> animaux
        </span>
      </div>

      <div class="item-meta">
        <span class="badge-soft badge-sky">
          <i class="bi bi-tools"></i>
          <?php echo (int)$enc['NB_REPARATIONS']; ?> réparations
        </span>

        <span class="badge-soft badge-amber">
          <i class="bi bi-bandaid-fill"></i>
          <?php echo (int)$enc['NB_SOINS']; ?> soins
        </span>
      </div>

      <div class="mini-list">
        <div class="mini-row">
          <span>Particularités</span>
          <strong><?php echo !empty($enc['PARTICULARITES']) ? htmlspecialchars($enc['PARTICULARITES']) : '—'; ?></strong>
        </div>

        <div class="mini-row">
          <span>Coordonnées</span>
          <strong><?php echo $enc['LATITUDE'] !== null ? htmlspecialchars($enc['LATITUDE'] . ' / ' . $enc['LONGITUDE']) : '—'; ?></strong>
        </div>
      </div>

      <div class="enclos-footer">
  <a class="btn btn-primary" href="<?php echo htmlspecialchars(url_site('/enclos/detail.php?id='.$enc['ID_ENCLOS'])); ?>">
    <i class="bi bi-eye-fill"></i> Détails
  </a>

  <?php if ($peut_modifier): ?>
  <a class="btn btn-light-surface" href="<?php echo htmlspecialchars(url_site('/enclos/detail.php?id='.$enc['ID_ENCLOS'])); ?>#edition">
    <i class="bi bi-pencil-fill"></i> Modifier
  </a>

  <a class="btn btn-danger"
     href="<?php echo htmlspecialchars(url_site('/enclos/index.php?supprimer=' . $enc['ID_ENCLOS'])); ?>"
     onclick="return confirm('Voulez-vous vraiment supprimer cet enclos ?');">
    <i class="bi bi-trash-fill"></i> Supprimer
  </a>
  <?php endif; ?>
</div>
    </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endforeach; ?>

<!-- Recherche JS -->
<script>
(function(){
  var input = document.getElementById('rechercheEnclos');
  var sections = Array.from(document.querySelectorAll('.zone-block'));

  if (input) {
    input.addEventListener('input', function(){
      var terme = (input.value || '').toLowerCase().trim();

      sections.forEach(function(section){
        var visibles = 0;

        section.querySelectorAll('.enclos-card').forEach(function(carte){
          var texte = (carte.dataset.search || '').toLowerCase();
          var show = terme === '' || texte.indexOf(terme) !== -1;

          carte.style.display = show ? '' : 'none';
          if (show) visibles++;
        });

        section.style.display = visibles > 0 ? '' : 'none';
      });
    });
  }
})();
</script>

<?php if($peut_modifier): ?>
<!-- Modal ajout enclos -->
<div class="modal fade" id="modalAjouter" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Ajouter un enclos</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form method="POST">
          <input type="hidden" name="action" value="ajouter">

          <div class="grid-auto">
            <div>
              <label class="form-label">Surface</label>
              <input type="number" step="0.01" class="form-control" name="surface" required>
            </div>

            <div>
              <label class="form-label">Zone</label>
              <select class="form-select" name="id_zone" required>
                <option value="">— Choisir —</option>
                <?php foreach($zones as $z): ?>
                  <option value="<?php echo $z['ID_ZONE']; ?>">
                    <?php echo htmlspecialchars($z['NOM_ZONE']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div>
              <label class="form-label">Latitude</label>
              <input class="form-control" type="number" step="0.000001" name="latitude">
            </div>

            <div>
              <label class="form-label">Longitude</label>
              <input class="form-control" type="number" step="0.000001" name="longitude">
            </div>

            <div style="grid-column:1/-1">
              <label class="form-label">Particularités</label>
              <textarea class="form-control" name="particularites" rows="3"></textarea>
            </div>
          </div>

          <div class="action-row justify-content-end">
            <button type="button" class="btn btn-light-surface" data-bs-dismiss="modal">Annuler</button>
            <button class="btn btn-primary">
              <i class="bi bi-plus-lg"></i> Ajouter
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
