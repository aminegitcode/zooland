<?php
// Accès + fichiers nécessaires
require_once '../includes/auth.php';
require_role(['admin','dirigeant','soigneur','soigneur_chef','technicien']);

require_once '../includes/path.php';
require_once '../includes/helpers.php';
require_once '../config.php';

// Variables principales
$peut_modifier = in_array(get_role(), ['admin','dirigeant'], true);
$message = '';
$type = 'success';

/* =========================
   AJOUT D'UNE ZONE
========================= */
if (
    $peut_modifier &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'ajouter'
) {
    $nom = trim($_POST['nom_zone'] ?? '');

    if ($nom === '') {
        $message = 'Le nom de la zone est obligatoire.';
        $type = 'danger';
    } else {
        // Génère un nouvel id_zone
        $st = oci_parse($conn, "SELECT NVL(MAX(id_zone),0)+1 FROM Zone");
        oci_execute($st);
        $r = oci_fetch_array($st, OCI_NUM);
        $id = (int)($r[0] ?? 1);
        oci_free_statement($st);

        // Insère la zone
        $st = oci_parse($conn, "INSERT INTO Zone(id_zone,nom_zone) VALUES(:id,:nom)");
        oci_bind_by_name($st, ':id', $id);
        oci_bind_by_name($st, ':nom', $nom);

        $ok = oci_execute($st, OCI_NO_AUTO_COMMIT);

        if ($ok) {
            oci_commit($conn);
            $message = 'Zone ajoutée.';
            $type = 'success';
        } else {
            $e = oci_error($st);
            oci_rollback($conn);
            $message = 'Erreur lors de l\'ajout : ' . ($e['message'] ?? 'erreur inconnue');
            $type = 'danger';
        }

        if ($st) {
            oci_free_statement($st);
        }
    }
}

/* =========================
   SUPPRESSION D'UNE ZONE
========================= */
if (
    $peut_modifier &&
    isset($_GET['supprimer'])
) {
    $id = (int)$_GET['supprimer'];

    // Vérifie si la zone contient encore des enclos
    $nb_enclos = 0;
    $st = oci_parse($conn, "SELECT COUNT(*) FROM Enclos WHERE id_zone = :id");
    oci_bind_by_name($st, ':id', $id);
    oci_execute($st);
    $r = oci_fetch_array($st, OCI_NUM);
    $nb_enclos = (int)($r[0] ?? 0);
    oci_free_statement($st);

    if ($nb_enclos > 0) {
        $message = "Impossible de supprimer cette zone : elle contient encore $nb_enclos enclos.";
        $type = 'danger';
    } else {
        $st = oci_parse($conn, "DELETE FROM Zone WHERE id_zone = :id");
        oci_bind_by_name($st, ':id', $id);

        $ok = oci_execute($st, OCI_NO_AUTO_COMMIT);

        if ($ok) {
            oci_commit($conn);
            $message = 'Zone supprimée.';
            $type = 'success';
        } else {
            $e = oci_error($st);
            oci_rollback($conn);
            $message = 'Erreur lors de la suppression : ' . ($e['message'] ?? 'erreur inconnue');
            $type = 'danger';
        }

        if ($st) {
            oci_free_statement($st);
        }
    }
}

/* =========================
   RÉCUPÈRE LES ZONES AVEC STATISTIQUES
========================= */
$zones = [];
$st = oci_parse(
    $conn,
    "SELECT z.id_zone,
            z.nom_zone,
            (SELECT COUNT(*) FROM Enclos e WHERE e.id_zone = z.id_zone) nb_enclos,
            (SELECT COUNT(*)
               FROM Animal a
               JOIN Enclos e ON a.id_enclos = e.id_enclos
              WHERE e.id_zone = z.id_zone) nb_animaux
     FROM Zone z
     ORDER BY z.nom_zone"
);

if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) {
        $zones[] = $row;
    }
    oci_free_statement($st);
}

// Ferme la connexion Oracle
oci_close($conn);

// Configuration de la page
$page_title = 'Zones';
$page_css = '/assets/css/zones.css';

$page_hero = [
    'kicker' => 'Cartographie',
    'icon'   => 'bi bi-map-fill',
    'title'  => 'Gestion des zones du parc',
    'desc'   => 'Créez, renommez et consultez les zones avec leurs enclos et animaux associés.',
    'image'  => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1600&q=80',
    'actions'=> array_filter([
        $peut_modifier ? [
            'label' => 'Ajouter une zone',
            'icon'  => 'bi bi-plus-lg',
            'target'=> '#modalZone',
            'class' => 'btn-primary'
        ] : null,
        [
            'label' => 'Retour enclos',
            'icon'  => 'bi bi-arrow-left',
            'href'  => url_site('/enclos/index.php'),
            'class' => 'btn-ghost'
        ]
    ]),
    'stats' => [
        ['value' => count($zones), 'label' => 'zones'],
        ['value' => array_sum(array_map(fn($z) => (int)$z['NB_ENCLOS'], $zones)), 'label' => 'enclos'],
        ['value' => array_sum(array_map(fn($z) => (int)$z['NB_ANIMAUX'], $zones)), 'label' => 'animaux'],
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

  <link href="<?php echo htmlspecialchars(url_site('/assets/vendor/bootstrap/css/bootstrap.min.css')); ?>" rel="stylesheet">
  <link href="<?php echo htmlspecialchars(url_site('/assets/css/bootstrap-icons-local.css')); ?>" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400&display=swap" rel="stylesheet">
  <link href="<?php echo htmlspecialchars(url_site('/assets/css/global.css')); ?>" rel="stylesheet">
  <?php if (!empty($page_css)): ?>
  <link href="<?php echo htmlspecialchars(url_site($page_css)); ?>" rel="stylesheet">
  <?php endif; ?>

  <style>
/* Bouton supprimer soft */
.btn-soft-danger {
  background: rgba(220, 53, 69, 0.08);
  color: #dc3545;
  border: 1px solid rgba(220, 53, 69, 0.18);
  backdrop-filter: blur(6px);
  transition: all 0.2s ease;
}

.btn-soft-danger:hover {
  background: rgba(220, 53, 69, 0.15);
  color: #b02a37;
  border-color: rgba(220, 53, 69, 0.3);
  transform: translateY(-1px);
}
  </style>
</head>

<body>
  <div class="d-flex app-layout">

    <div class="app-sidebar-col">
      <?php include '../includes/sidebar.php'; ?>
    </div>

    <main class="app-content-col">
      <div class="page-padding">

        <section class="page-hero reveal parallax" style="--hero-img:url('<?php echo htmlspecialchars($heroImg, ENT_QUOTES); ?>')">
          <div class="hero-pill">
            <i class="<?php echo htmlspecialchars($heroIcon); ?>"></i>
            <?php echo htmlspecialchars($heroKicker); ?>
          </div>

          <div class="hero-grid">
            <div class="hero-copy">
              <h1 class="hero-title"><?php echo htmlspecialchars($heroTitle); ?></h1>

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

        <?php render_alert($message, $type); ?>

        <div class="search-toolbar reveal mb-4">
          <div class="search-box">
            <i class="bi bi-search"></i>
            <input type="search" id="rechercheZones" class="search-input" placeholder="Rechercher une zone...">
          </div>
        </div>

        <div class="zone-grid reveal">
          <?php foreach ($zones as $z): ?>
          <article class="zone-card"
            data-search="<?php echo htmlspecialchars(strtolower(trim(($z['NOM_ZONE'] ?? '') . ' zone ' . ($z['NB_ENCLOS'] ?? '') . ' ' . ($z['NB_ANIMAUX'] ?? ''))), ENT_QUOTES); ?>">
            <div class="item-top">
              <div>
                <h3 class="item-title"><?php echo htmlspecialchars($z['NOM_ZONE']); ?></h3>
                <div class="item-sub">Zone #<?php echo $z['ID_ZONE']; ?></div>
              </div>

              <span class="badge-soft badge-amber">
                <i class="bi bi-grid-3x3-gap-fill"></i>
                <?php echo (int)$z['NB_ENCLOS']; ?> enclos
              </span>
            </div>

            <div class="mini-list">
              <div class="mini-row">
                <span>Animaux présents</span>
                <strong><?php echo (int)$z['NB_ANIMAUX']; ?></strong>
              </div>
            </div>

            <div class="zone-footer">
              <a class="btn btn-primary" href="<?php echo htmlspecialchars(url_site('/zones/detail.php?id=' . $z['ID_ZONE'])); ?>">
                <i class="bi bi-eye-fill"></i> Détails
              </a>

              <?php if ($peut_modifier): ?>
              <a class="btn btn-soft-danger"
                 href="<?php echo htmlspecialchars(url_site('/zones/index.php?supprimer=' . $z['ID_ZONE'])); ?>"
                 onclick="return confirm('Voulez-vous vraiment supprimer cette zone ?');">
                <i class="bi bi-trash-fill"></i> Supprimer
              </a>
              <?php endif; ?>
            </div>
          </article>
          <?php endforeach; ?>

          <?php if (empty($zones)): ?>
          <div style="grid-column:1/-1;text-align:center;padding:3rem;color:var(--txt-muted)">
            <i class="bi bi-map" style="font-size:2.5rem;opacity:.3;display:block;margin-bottom:.75rem"></i>
            <div style="font-weight:700">Aucune zone trouvée</div>
          </div>
          <?php endif; ?>
        </div>

        <script>
          (function() {
            var input = document.getElementById('rechercheZones');
            var cartes = Array.from(document.querySelectorAll('.zone-card'));

            if (input) {
              input.addEventListener('input', function() {
                var terme = (input.value || '').toLowerCase().trim();

                cartes.forEach(function(carte) {
                  var texte = (carte.dataset.search || '').toLowerCase();
                  carte.style.display = terme === '' || texte.indexOf(terme) !== -1 ? '' : 'none';
                });
              });
            }
          })();
        </script>

        <?php if ($peut_modifier): ?>
        <div class="modal fade" id="modalZone" tabindex="-1">
          <div class="modal-dialog">
            <div class="modal-content">

              <div class="modal-header">
                <h5 class="modal-title fw-bold">Ajouter une zone</h5>
                <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
              </div>

              <div class="modal-body">
                <form method="POST">
                  <input type="hidden" name="action" value="ajouter">

                  <label class="form-label">Nom de zone</label>
                  <input class="form-control" name="nom_zone" required>

                  <div class="action-row justify-content-end mt-3">
                    <button type="button" class="btn btn-light-surface" data-bs-dismiss="modal">Annuler</button>
                    <button class="btn btn-primary" type="submit">Créer</button>
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
    document.querySelectorAll('.reveal').forEach(function(el, i) {
      setTimeout(function() {
        el.classList.add('visible');
      }, 80 + i * 35);
    });

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
