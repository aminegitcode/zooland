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

// Si on modifie le soin
if (
    $peut_modifier &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'modifier'
) {
    $date = trim($_POST['date_soin'] ?? date('Y-m-d'));
    $typeSoigneur = trim($_POST['type_soigneur'] ?? '');
    $idPers = (int)($_POST['id_personnel'] ?? 0);

    $st = oci_parse(
        $conn,
        "UPDATE Historique_soins
         SET type_soigneur = :ts,
             date_soin = TO_DATE(:d,'YYYY-MM-DD'),
             id_personnel = :p
         WHERE id_historique_soins = :id"
    );

    oci_bind_by_name($st, ':ts', $typeSoigneur);
    oci_bind_by_name($st, ':d', $date);
    oci_bind_by_name($st, ':p', $idPers);
    oci_bind_by_name($st, ':id', $id);

    $ok = oci_execute($st);

    if ($st) {
        oci_free_statement($st);
    }

    $message = $ok ? 'Soin mis à jour.' : 'Erreur.';
    $type = $ok ? 'success' : 'danger';
}

// Récupère le soin
$st = oci_parse(
    $conn,
    "SELECT hs.*,
            s.nom_soin,
            s.type_soin,
            a.nom_animal,
            a.rfid,
            e.nom_usuel,
            p.prenom_personnel,
            p.nom_personnel
     FROM Historique_soins hs
     LEFT JOIN Soin s ON hs.id_soin = s.id_soin
     LEFT JOIN Animal a ON hs.rfid = a.rfid
     LEFT JOIN Espece e ON a.id_espece = e.id_espece
     LEFT JOIN Personnel p ON hs.id_personnel = p.id_personnel
     WHERE hs.id_historique_soins = :id"
);

oci_bind_by_name($st, ':id', $id);
oci_execute($st);

$soin = oci_fetch_assoc($st);

if ($st) {
    oci_free_statement($st);
}

// Si le soin n'existe pas
if (!$soin) {
    header('Location: ' . url_site('/soins/index.php'));
    exit;
}

// Liste du personnel pour le formulaire
$liste_pers = [];

$st = oci_parse(
    $conn,
    "SELECT p.id_personnel,
            p.prenom_personnel,
            p.nom_personnel
     FROM Personnel p,
          Historique_emploi h,
          Role r
     WHERE p.id_personnel = h.id_personnel
       AND h.id_role = r.id_role
       AND h.date_fin IS NULL
       AND LOWER(r.nom_role) IN ('soigneur','soigneur chef','veterinaire')
     ORDER BY p.nom_personnel"
);

if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) {
        $liste_pers[] = $row;
    }
    oci_free_statement($st);
}

// Ferme la connexion
oci_close($conn);

// Configuration de la page
$page_title = 'Détail soin';
$page_css = '/assets/css/detail.css';

$page_hero = [
    'kicker' => 'Intervention',
    'icon'   => 'bi bi-bandaid-fill',
    'title'  => htmlspecialchars($soin['NOM_SOIN'] ?? ('Soin #' . $id)),
    'desc'   => 'Page détail cohérente avec les autres fiches : qui, pour qui, quand et comment.',
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
    'stats'  => [
        ['value' => format_date_fr($soin['DATE_SOIN']), 'label' => 'date'],
        ['value' => $soin['TYPE_SOIN'] ?? '—', 'label' => 'type soin'],
        ['value' => $soin['TYPE_SOIGNEUR'] ?? '—', 'label' => 'type soigneur'],
        ['value' => $soin['NOM_ANIMAL'] ?? '—', 'label' => 'animal']
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

        <!-- Infos principales -->
        <section class="detail-panel">
          <div class="kv-grid">
            <div class="kv-card">
              <div class="kv-label">Animal</div>
              <div class="kv-value"><?php echo htmlspecialchars($soin['NOM_ANIMAL'] ?? '—'); ?></div>
            </div>

            <div class="kv-card">
              <div class="kv-label">Espèce</div>
              <div class="kv-value"><?php echo htmlspecialchars($soin['NOM_USUEL'] ?? '—'); ?></div>
            </div>

            <div class="kv-card">
              <div class="kv-label">Réalisé par</div>
              <div class="kv-value">
                <?php echo htmlspecialchars(trim(($soin['PRENOM_PERSONNEL'] ?? '') . ' ' . ($soin['NOM_PERSONNEL'] ?? '')) ?: '—'); ?>
              </div>
            </div>

            <div class="kv-card">
              <div class="kv-label">RFID</div>
              <div class="kv-value"><?php echo htmlspecialchars($soin['RFID'] ?? '—'); ?></div>
            </div>
          </div>
        </section>

        <!-- Résumé -->
        <section class="detail-panel">
          <div class="stack-list">
            <div class="stack-item">
              <strong>Résumé</strong>
              <div class="text-muted mt-1">
                Intervention enregistrée le <?php echo format_date_fr($soin['DATE_SOIN']); ?>
                pour <?php echo htmlspecialchars($soin['NOM_ANIMAL'] ?? ''); ?>.
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
                <h5 class="modal-title fw-bold">Modifier le soin</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
              </div>

              <div class="modal-body">
                <form method="POST">
                  <input type="hidden" name="action" value="modifier">

                  <div class="grid-auto">
                    <div>
                      <label class="form-label">Date</label>
                      <input
                        class="form-control"
                        type="date"
                        name="date_soin"
                        value="<?php echo format_date_input($soin['DATE_SOIN']); ?>"
                        required
                      >
                    </div>

                    <div>
                      <label class="form-label">Type soigneur</label>
                      <select class="form-select" name="type_soigneur">
                        <option <?php echo (($soin['TYPE_SOIGNEUR'] ?? '') === 'Soigneur attitré') ? 'selected' : ''; ?>>
                          Soigneur attitré
                        </option>
                        <option <?php echo (($soin['TYPE_SOIGNEUR'] ?? '') === 'Soigneur remplaçant') ? 'selected' : ''; ?>>
                          Soigneur remplaçant
                        </option>
                        <option <?php echo (($soin['TYPE_SOIGNEUR'] ?? '') === 'Vétérinaire') ? 'selected' : ''; ?>>
                          Vétérinaire
                        </option>
                      </select>
                    </div>

                    <div>
                      <label class="form-label">Personnel</label>
                      <select class="form-select" name="id_personnel" required>
                        <?php foreach ($liste_pers as $p): ?>
                          <option
                            value="<?php echo $p['ID_PERSONNEL']; ?>"
                            <?php echo ((int)$p['ID_PERSONNEL'] === (int)$soin['ID_PERSONNEL']) ? 'selected' : ''; ?>
                          >
                            <?php echo htmlspecialchars($p['PRENOM_PERSONNEL'] . ' ' . $p['NOM_PERSONNEL']); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
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
