<?php

require_once '../includes/auth.php';
require_role(['admin','dirigeant','soigneur','soigneur_chef','technicien']);

require_once '../includes/path.php';
require_once '../includes/helpers.php';
require_once '../config.php';


// Variables principales
$peut_modifier = in_array(get_role(), ['admin','dirigeant'], true);
$id = (int)($_GET['id'] ?? 0);
$message = '';
$type = 'success';


// Vérifie l'id
if ($id <= 0) {
    header('Location: ' . url_site('/zones/index.php'));
    exit;
}


// Modification du nom de la zone
if (
    $peut_modifier &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'modifier'
) {
    $nom = trim($_POST['nom_zone'] ?? '');

    $st = oci_parse($conn, "UPDATE Zone SET nom_zone=:n WHERE id_zone=:id");
    oci_bind_by_name($st, ':n', $nom);
    oci_bind_by_name($st, ':id', $id);

    $ok = oci_execute($st);

    if ($st) {
        oci_free_statement($st);
    }

    $message = $ok ? 'Zone mise à jour.' : 'Erreur.';
    $type = $ok ? 'success' : 'danger';
}


// Récupère la zone
$st = oci_parse($conn, "SELECT id_zone,nom_zone FROM Zone WHERE id_zone=:id");
oci_bind_by_name($st, ':id', $id);
oci_execute($st);

$zone = oci_fetch_assoc($st);

if ($st) {
    oci_free_statement($st);
}

// Si la zone n'existe pas
if (!$zone) {
    header('Location: ' . url_site('/zones/index.php'));
    exit;
}


// Récupère les enclos de la zone
$enclos = [];
$st = oci_parse(
    $conn,
    "SELECT e.id_enclos,
            e.surface,
            e.particularites,
            (SELECT COUNT(*) FROM Animal a WHERE a.id_enclos=e.id_enclos) nb_animaux
     FROM Enclos e
     WHERE e.id_zone=:id
     ORDER BY e.id_enclos"
);
oci_bind_by_name($st, ':id', $id);

if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) {
        $enclos[] = $row;
    }
    oci_free_statement($st);
}


// Ferme la connexion Oracle
oci_close($conn);


// Configuration de la page
$page_title = 'Détail zone';
$page_css = '/assets/css/detail.css';

$page_hero = [
    'kicker' => 'Zone',
    'icon'   => 'bi bi-map-fill',
    'title'  => htmlspecialchars($zone['NOM_ZONE']),
    'desc'   => 'Consultez les enclos et les volumes d\'animaux de cette zone.',
    'image'  => 'https://images.unsplash.com/photo-1500534623283-312aade485b7?auto=format&fit=crop&w=1600&q=80',
    'actions'=> array_filter([
        $peut_modifier
            ? [
                'label' => 'Renommer',
                'icon'  => 'bi bi-pencil-fill',
                'target'=> '#modalEdit',
                'class' => 'btn-primary'
            ]
            : null,
        [
            'label' => 'Retour',
            'icon'  => 'bi bi-arrow-left',
            'href'  => url_site('/zones/index.php'),
            'class' => 'btn-ghost'
        ]
    ]),
    'stats' => [
        ['value' => $zone['ID_ZONE'], 'label' => 'identifiant'],
        ['value' => count($enclos), 'label' => 'enclos'],
        ['value' => array_sum(array_map(fn($e) => (int)$e['NB_ANIMAUX'], $enclos)), 'label' => 'animaux'],
        ['value' => 'Safari', 'label' => 'ambiance']
    ]
];


// Variables du hero
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
    <div class="app-sidebar-col">
      <?php include '../includes/sidebar.php'; ?>
    </div>

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
                <button class="btn <?php echo htmlspecialchars($class); ?>" type="button"
                  <?php if (!empty($action['target'])): ?>
                  data-bs-toggle="modal" data-bs-target="<?php echo htmlspecialchars($action['target']); ?>"
                  <?php endif; ?>>
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

        <!-- Message -->
        <?php render_alert($message, $type); ?>

        <!-- Liste des enclos -->
        <div class="detail-panel reveal">
          <div class="stack-list">
            <?php foreach ($enclos as $e): ?>
            <div class="stack-item">
              <strong>Enclos #<?php echo $e['ID_ENCLOS']; ?></strong>

              <div class="text-muted mt-1">
                <?php echo number_format((float)$e['SURFACE'], 0, ',', ' '); ?> m² —
                <?php echo (int)$e['NB_ANIMAUX']; ?> animaux
              </div>

              <div class="text-muted mt-1">
                <?php echo !empty($e['PARTICULARITES']) ? htmlspecialchars($e['PARTICULARITES']) : 'Aucune particularité'; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Modale modification -->
        <?php if ($peut_modifier): ?>
        <div class="modal fade" id="modalEdit" tabindex="-1">
          <div class="modal-dialog">
            <div class="modal-content">

              <div class="modal-header">
                <h5 class="modal-title fw-bold">Modifier la zone</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
              </div>

              <div class="modal-body">
                <form method="POST">
                  <input type="hidden" name="action" value="modifier">

                  <label class="form-label">Nom</label>
                  <input class="form-control" name="nom_zone" value="<?php echo htmlspecialchars($zone['NOM_ZONE']); ?>" required>

                  <div class="action-row justify-content-end">
                    <button type="button" class="btn btn-light-surface" data-bs-dismiss="modal">Annuler</button>
                    <button class="btn btn-primary">Enregistrer</button>
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
    // Animation apparition
    document.querySelectorAll('.reveal').forEach(function(el, i) {
      setTimeout(function() {
        el.classList.add('visible');
      }, 80 + i * 35);
    });

    // Affichage conditionnel
    document.querySelectorAll('[data-toggle-extern]').forEach(function(box) {
      function sync() {
        var t = document.getElementById(box.dataset.toggleExtern);
        if (!t) return;

        document.querySelectorAll(box.dataset.target).forEach(function(el) {
          el.style.display = t.checked ? '' : 'none';
        });

        document.querySelectorAll(box.dataset.altTarget).forEach(function(el) {
          el.style.display = t.checked ? 'none' : '';
        });
      }

      var target = document.getElementById(box.dataset.toggleExtern);
      if (target) {
        target.addEventListener('change', sync);
        sync();
      }
    });
  </script>
</body>

</html>
