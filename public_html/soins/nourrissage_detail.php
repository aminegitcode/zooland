<?php
// Accès + fichiers nécessaires
require_once '../includes/auth.php';
require_role(['admin','dirigeant','soigneur','soigneur_chef','veterinaire']);

require_once '../includes/path.php';
require_once '../includes/helpers.php';
require_once '../config.php';

// Variables principales
$peut_modifier = in_array(get_role(), ['admin','dirigeant','soigneur_chef','veterinaire'], true);
$id = (int)($_GET['id'] ?? 0);
$message = '';
$type = 'success';

// Vérifie l'id
if ($id <= 0) {
    header('Location: ' . url_site('/soins/index.php'));
    exit;
}

// Si on modifie le nourrissage
if (
    $peut_modifier &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'modifier'
) {
    $date = trim($_POST['date_nourrissage'] ?? date('Y-m-d'));
    $aliment = trim($_POST['nom_aliment'] ?? '');
    $dose = trim($_POST['dose_nourrissage'] ?? '');
    $rem = trim($_POST['remarques_nourrissage'] ?? '');
    $idPers = (int)($_POST['id_personnel'] ?? 0);

    $st = oci_parse(
        $conn,
        "UPDATE Nourrissage
         SET date_nourrissage = TO_DATE(:d,'YYYY-MM-DD'),
             nom_aliment = :al,
             dose_nourrissage = :do,
             remarques_nourrissage = :re,
             id_personnel = :p
         WHERE id_nourrissage = :id"
    );

    oci_bind_by_name($st, ':d', $date);
    oci_bind_by_name($st, ':al', $aliment);
    oci_bind_by_name($st, ':do', $dose);
    oci_bind_by_name($st, ':re', $rem);
    oci_bind_by_name($st, ':p', $idPers);
    oci_bind_by_name($st, ':id', $id);

    $ok = oci_execute($st);

    if ($st) {
        oci_free_statement($st);
    }

    $message = $ok ? 'Nourrissage mis à jour.' : 'Erreur.';
    $type = $ok ? 'success' : 'danger';
}

// Récupère le nourrissage
$st = oci_parse(
    $conn,
    "SELECT n.*,
            a.nom_animal,
            a.rfid,
            e.nom_usuel,
            p.prenom_personnel,
            p.nom_personnel
     FROM Nourrissage n
     LEFT JOIN Animal a ON n.rfid = a.rfid
     LEFT JOIN Espece e ON a.id_espece = e.id_espece
     LEFT JOIN Personnel p ON n.id_personnel = p.id_personnel
     WHERE n.id_nourrissage = :id"
);

oci_bind_by_name($st, ':id', $id);
oci_execute($st);

$n = oci_fetch_assoc($st);

if ($st) {
    oci_free_statement($st);
}

// Si le nourrissage n'existe pas
if (!$n) {
    header('Location: ' . url_site('/soins/index.php'));
    exit;
}

// Liste du personnel
$liste_pers = [];
$st = oci_parse($conn, "
    SELECT id_personnel, prenom_personnel, nom_personnel
    FROM Personnel
    ORDER BY nom_personnel
");

if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) {
        $liste_pers[] = $row;
    }
    oci_free_statement($st);
}

// Ferme la connexion
oci_close($conn);

// Configuration de la page
$page_title = 'Détail nourrissage';
$page_css = '/assets/css/detail.css';

$page_hero = [
    'kicker' => 'Feeding log',
    'icon'   => 'bi bi-egg-fried',
    'title'  => htmlspecialchars($n['NOM_ALIMENT'] ?? 'Nourrissage'),
    'desc'   => 'Fiche détaillée du repas : par qui, pour qui, dose, remarques et date.',
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
            'href'  => url_site('/soins/index.php'),
            'class' => 'btn-ghost'
        ]
    ]),
    'stats' => [
        ['value' => format_date_fr($n['DATE_NOURRISSAGE']), 'label' => 'date'],
        ['value' => $n['NOM_ANIMAL'] ?? '—', 'label' => 'animal'],
        ['value' => $n['DOSE_NOURRISSAGE'] ?? '—', 'label' => 'dose'],
        ['value' => $n['NOM_USUEL'] ?? '—', 'label' => 'espèce']
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

      <!-- Message -->
      <?php render_alert($message, $type); ?>

      <!-- Détail -->
      <div class="detail-shell reveal">

        <!-- Infos principales -->
        <section class="detail-panel">
          <div class="kv-grid">
            <div class="kv-card">
              <div class="kv-label">Animal</div>
              <div class="kv-value"><?php echo htmlspecialchars($n['NOM_ANIMAL'] ?? '—'); ?></div>
            </div>

            <div class="kv-card">
              <div class="kv-label">Espèce</div>
              <div class="kv-value"><?php echo htmlspecialchars($n['NOM_USUEL'] ?? '—'); ?></div>
            </div>

            <div class="kv-card">
              <div class="kv-label">Dose</div>
              <div class="kv-value"><?php echo htmlspecialchars($n['DOSE_NOURRISSAGE'] ?? '—'); ?></div>
            </div>

            <div class="kv-card">
              <div class="kv-label">Réalisé par</div>
              <div class="kv-value">
                <?php echo htmlspecialchars(trim(($n['PRENOM_PERSONNEL'] ?? '') . ' ' . ($n['NOM_PERSONNEL'] ?? '')) ?: '—'); ?>
              </div>
            </div>
          </div>
        </section>

        <!-- Remarques -->
        <section class="detail-panel">
          <div class="stack-item">
            <strong>Remarques</strong>
            <div class="text-muted mt-1">
              <?php echo !empty($n['REMARQUES_NOURRISSAGE']) ? htmlspecialchars($n['REMARQUES_NOURRISSAGE']) : 'Aucune remarque'; ?>
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
                <h5 class="modal-title fw-bold">Modifier le nourrissage</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
              </div>

              <div class="modal-body">
                <form method="POST">
                  <input type="hidden" name="action" value="modifier">

                  <div class="grid-auto">
                    <div>
                      <label class="form-label">Date</label>
                      <input class="form-control" type="date" name="date_nourrissage" value="<?php echo format_date_input($n['DATE_NOURRISSAGE']); ?>" required>
                    </div>

                    <div>
                      <label class="form-label">Aliment</label>
                      <input class="form-control" name="nom_aliment" value="<?php echo htmlspecialchars($n['NOM_ALIMENT'] ?? ''); ?>" required>
                    </div>

                    <div>
                      <label class="form-label">Dose</label>
                      <input class="form-control" name="dose_nourrissage" value="<?php echo htmlspecialchars($n['DOSE_NOURRISSAGE'] ?? ''); ?>">
                    </div>

                    <div>
                      <label class="form-label">Personnel</label>
                      <select class="form-select" name="id_personnel" required>
                        <?php foreach ($liste_pers as $p): ?>
                          <option
                            value="<?php echo $p['ID_PERSONNEL']; ?>"
                            <?php echo ((int)$p['ID_PERSONNEL'] === (int)$n['ID_PERSONNEL']) ? 'selected' : ''; ?>
                          >
                            <?php echo htmlspecialchars($p['PRENOM_PERSONNEL'] . ' ' . $p['NOM_PERSONNEL']); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <div style="grid-column:1/-1">
                      <label class="form-label">Remarques</label>
                      <textarea class="form-control" name="remarques_nourrissage" rows="3"><?php echo htmlspecialchars($n['REMARQUES_NOURRISSAGE'] ?? ''); ?></textarea>
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
