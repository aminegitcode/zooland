<?php
// Accès + fichiers nécessaires
require_once '../includes/auth.php';
require_role(['admin','dirigeant','technicien']);

require_once '../includes/path.php';
require_once '../includes/helpers.php';
require_once '../config.php';

// Variables principales
$peut_modifier = in_array(get_role(), ['admin','dirigeant','technicien'], true);
$id = (int)($_GET['id'] ?? 0);
$message = '';
$type = 'success';

// Vérifie l'id
if ($id <= 0) {
    header('Location: ' . url_site('/reparations/index.php'));
    exit;
}

// Si on modifie la réparation
if (
    $peut_modifier &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'modifier'
) {
    $date = trim($_POST['date_reparation'] ?? date('Y-m-d'));
    $nature = trim($_POST['nature'] ?? '');

    $st = oci_parse(
        $conn,
        "UPDATE Reparation
         SET date_reparation = TO_DATE(:d,'YYYY-MM-DD'),
             nature = :n
         WHERE id_reparation = :id"
    );

    oci_bind_by_name($st, ':d', $date);
    oci_bind_by_name($st, ':n', $nature);
    oci_bind_by_name($st, ':id', $id);

    $ok = oci_execute($st);

    if ($st) {
        oci_free_statement($st);
    }

    $message = $ok ? 'Réparation modifiée.' : 'Erreur.';
    $type = $ok ? 'success' : 'danger';
}

// Récupère la réparation
$st = oci_parse(
    $conn,
    "SELECT r.*,
            e.id_zone,
            e.id_enclos,
            p.prenom_personnel,
            p.nom_personnel,
            pr.nom_prestataire,
            z.nom_zone
     FROM Reparation r
     LEFT JOIN Enclos e ON r.id_enclos = e.id_enclos
     LEFT JOIN Zone z ON e.id_zone = z.id_zone
     LEFT JOIN Personnel p ON r.id_personnel = p.id_personnel
     LEFT JOIN Prestataire pr ON r.id_prestataire = pr.id_prestataire
     WHERE r.id_reparation = :id"
);

oci_bind_by_name($st, ':id', $id);
oci_execute($st);

$r = oci_fetch_assoc($st);

if ($st) {
    oci_free_statement($st);
}

// Si la réparation n'existe pas
if (!$r) {
    header('Location: ' . url_site('/reparations/index.php'));
    exit;
}

// Ferme la connexion
oci_close($conn);

// Configuration de la page
$page_title = 'Détail réparation';
$page_css = '/assets/css/detail.css';

$page_hero = [
    'kicker' => 'Maintenance detail',
    'icon'   => 'bi bi-tools',
    'title'  => htmlspecialchars($r['NATURE'] ?? ('Réparation #' . $id)),
    'desc'   => 'Fiche réparation moderne, cohérente avec les autres pages détails.',
    'image'  => url_site('/assets/img/detail-hero.svg'),
    'actions'=> array_filter([
        $peut_modifier
            ? [
                'label' => 'Modifier',
                'icon'  => 'bi bi-pencil-fill',
                'target'=> '#modalEdit',
                'class' => 'btn-primary'
            ]
            : null,
        [
            'label' => 'Retour',
            'icon'  => 'bi bi-arrow-left',
            'href'  => url_site('/reparations/index.php'),
            'class' => 'btn-ghost'
        ]
    ]),
    'stats' => [
        ['value' => format_date_fr($r['DATE_REPARATION']), 'label' => 'date'],
        ['value' => $r['ID_ENCLOS'] ?? '—', 'label' => 'enclos'],
        ['value' => !empty($r['NOM_PRESTATAIRE']) ? 'Externe' : 'Interne', 'label' => 'mode'],
        ['value' => $r['NOM_ZONE'] ?? '—', 'label' => 'zone']
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
                <?php foreach ($heroActions as $action): ?>
                  <?php if (!$action) continue; ?>
                  <?php $class = $action['class'] ?? 'btn-primary'; ?>

                  <?php if (!empty($action['href'])): ?>
                    <a class="btn <?php echo htmlspecialchars($class); ?>" href="<?php echo htmlspecialchars($action['href']); ?>">
                      <?php if (!empty($action['icon'])): ?>
                        <i class="<?php echo htmlspecialchars($action['icon']); ?>"></i>
                      <?php endif; ?>
                      <?php echo htmlspecialchars($action['label'] ?? ''); ?>
                    </a>
                  <?php else: ?>
                    <button
                      class="btn <?php echo htmlspecialchars($class); ?>"
                      type="button"
                      <?php if (!empty($action['target'])): ?>
                        data-bs-toggle="modal" data-bs-target="<?php echo htmlspecialchars($action['target']); ?>"
                      <?php endif; ?>
                    >
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

      <!-- Détail -->
      <div class="detail-shell reveal">
        <section class="detail-panel">
          <div class="kv-grid">

            <div class="kv-card">
              <div class="kv-label">Nature</div>
              <div class="kv-value"><?php echo htmlspecialchars($r['NATURE']); ?></div>
            </div>

            <div class="kv-card">
              <div class="kv-label">Zone</div>
              <div class="kv-value"><?php echo htmlspecialchars($r['NOM_ZONE'] ?? '—'); ?></div>
            </div>

            <div class="kv-card">
              <div class="kv-label">Intervenant</div>
              <div class="kv-value">
                <?php
                echo !empty($r['NOM_PRESTATAIRE'])
                    ? htmlspecialchars($r['NOM_PRESTATAIRE'])
                    : htmlspecialchars(trim(($r['PRENOM_PERSONNEL'] ?? '') . ' ' . ($r['NOM_PERSONNEL'] ?? '')) ?: '—');
                ?>
              </div>
            </div>

            <div class="kv-card">
              <div class="kv-label">Type</div>
              <div class="kv-value">
                <?php echo !empty($r['NOM_PRESTATAIRE']) ? 'Prestataire externe' : 'Personnel technique'; ?>
              </div>
            </div>

          </div>
        </section>
      </div>

      <!-- Modale modification -->
      <?php if ($peut_modifier): ?>
        <div class="modal fade" id="modalEdit" tabindex="-1">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">

              <div class="modal-header">
                <h5 class="modal-title fw-bold">Modifier la réparation</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
              </div>

              <div class="modal-body">
                <form method="POST">
                  <input type="hidden" name="action" value="modifier">

                  <div class="grid-auto">
                    <div>
                      <label class="form-label">Date</label>
                      <input class="form-control" type="date" name="date_reparation" value="<?php echo format_date_input($r['DATE_REPARATION']); ?>" required>
                    </div>

                    <div>
                      <label class="form-label">Nature</label>
                      <input class="form-control" name="nature" value="<?php echo htmlspecialchars($r['NATURE']); ?>" required>
                    </div>
                  </div>

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
