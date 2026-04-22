<?php

require_once '../includes/auth.php';
require_role(['admin','dirigeant','comptable']);

require_once '../includes/path.php';
require_once '../includes/helpers.php';
require_once '../config.php';


// Variables principales
$roleSession = get_role();
$peut_mod = in_array($roleSession, ['admin','dirigeant'], true);
$id = (int)($_GET['id'] ?? 0);
$message = '';
$type = 'success';


// Vérifie l'id
if ($id <= 0) {
    header('Location: ' . url_site('/personnel/index.php'));
    exit;
}


// Modification de la fiche
if (
    $peut_mod &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'modifier'
) {
    $nom = trim($_POST['nom_personnel'] ?? '');
    $prenom = trim($_POST['prenom_personnel'] ?? '');
    $salaire = (float)($_POST['salaire'] ?? 0);
    $date = trim($_POST['date_entree'] ?? date('Y-m-d'));
    $manager = !empty($_POST['id_manager']) ? (int)$_POST['id_manager'] : null;

    // Si un manager est choisi
    if ($manager) {
        $st = oci_parse(
            $conn,
            "UPDATE Personnel
             SET nom_personnel = :n,
                 prenom_personnel = :p,
                 salaire = :s,
                 date_entree = TO_DATE(:d,'YYYY-MM-DD'),
                 id_manager = :m
             WHERE id_personnel = :id"
        );
        oci_bind_by_name($st, ':m', $manager);
    } else {
        // Sinon on met le manager à NULL
        $st = oci_parse(
            $conn,
            "UPDATE Personnel
             SET nom_personnel = :n,
                 prenom_personnel = :p,
                 salaire = :s,
                 date_entree = TO_DATE(:d,'YYYY-MM-DD'),
                 id_manager = NULL
             WHERE id_personnel = :id"
        );
    }

    oci_bind_by_name($st, ':n', $nom);
    oci_bind_by_name($st, ':p', $prenom);
    oci_bind_by_name($st, ':s', $salaire);
    oci_bind_by_name($st, ':d', $date);
    oci_bind_by_name($st, ':id', $id);

    $ok = oci_execute($st);

    if ($st) {
        oci_free_statement($st);
    }

    $message = $ok ? 'Fiche mise à jour.' : 'Erreur lors de la mise à jour.';
    $type = $ok ? 'success' : 'danger';
}


// Récupère la fiche du personnel
$pers = null;
$st = oci_parse(
    $conn,
    "SELECT p.id_personnel,
            p.nom_personnel,
            p.prenom_personnel,
            p.date_entree,
            p.salaire,
            p.id_manager,
            pm.nom_personnel mgr_nom,
            pm.prenom_personnel mgr_prenom
     FROM Personnel p
     LEFT JOIN Personnel pm ON p.id_manager = pm.id_personnel
     WHERE p.id_personnel = :id"
);
oci_bind_by_name($st, ':id', $id);

if ($st && oci_execute($st)) {
    $pers = oci_fetch_assoc($st);
    oci_free_statement($st);
}

// Si la fiche n'existe pas
if (!$pers) {
    header('Location: ' . url_site('/personnel/index.php'));
    exit;
}


// Historique des emplois
$historique = [];
$st = oci_parse(
    $conn,
    "SELECT h.id_historique_emploi,
            h.date_debut,
            h.date_fin,
            r.nom_role
     FROM Historique_emploi h
     JOIN Role r ON h.id_role = r.id_role
     WHERE h.id_personnel = :id
     ORDER BY h.date_debut DESC"
);
oci_bind_by_name($st, ':id', $id);

if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) {
        $historique[] = $row;
    }
    oci_free_statement($st);
}


// Animaux suivis par ce personnel
$animaux = [];
$st = oci_parse(
    $conn,
    "SELECT a.rfid,
            a.nom_animal,
            e.nom_usuel,
            en.id_enclos,
            z.nom_zone
     FROM Animal a
     LEFT JOIN Espece e ON a.id_espece = e.id_espece
     LEFT JOIN Enclos en ON a.id_enclos = en.id_enclos
     LEFT JOIN Zone z ON en.id_zone = z.id_zone
     WHERE a.id_personnel = :id
     ORDER BY a.nom_animal"
);
oci_bind_by_name($st, ':id', $id);

if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) {
        $animaux[] = $row;
    }
    oci_free_statement($st);
}


// Derniers soins
$soins = [];
$st = oci_parse(
    $conn,
    "SELECT hs.id_historique_soins,
            hs.date_soin,
            s.nom_soin,
            a.nom_animal
     FROM Historique_soins hs
     LEFT JOIN Soin s ON hs.id_soin = s.id_soin
     LEFT JOIN Animal a ON hs.rfid = a.rfid
     WHERE hs.id_personnel = :id
     ORDER BY hs.date_soin DESC
     FETCH FIRST 12 ROWS ONLY"
);
oci_bind_by_name($st, ':id', $id);

if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) {
        $soins[] = $row;
    }
    oci_free_statement($st);
}


// Derniers nourrissages
$nourrissages = [];
$st = oci_parse(
    $conn,
    "SELECT id_nourrissage,
            date_nourrissage,
            nom_aliment,
            dose_nourrissage,
            a.nom_animal
     FROM Nourrissage n
     LEFT JOIN Animal a ON n.rfid = a.rfid
     WHERE n.id_personnel = :id
     ORDER BY n.date_nourrissage DESC
     FETCH FIRST 12 ROWS ONLY"
);
oci_bind_by_name($st, ':id', $id);

if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) {
        $nourrissages[] = $row;
    }
    oci_free_statement($st);
}


// Spécialités
$specialites = [];
$st = oci_parse(
    $conn,
    "SELECT e.nom_usuel
     FROM Specialiser s
     JOIN Espece e ON s.id_espece = e.id_espece
     WHERE s.id_personnel = :id
     ORDER BY e.nom_usuel"
);
oci_bind_by_name($st, ':id', $id);

if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) {
        $specialites[] = $row['NOM_USUEL'];
    }
    oci_free_statement($st);
}


// Liste des managers possibles
$managers = [];
$st = oci_parse(
    $conn,
    "SELECT id_personnel,
            nom_personnel,
            prenom_personnel
     FROM Personnel
     WHERE id_personnel <> :id
     ORDER BY nom_personnel, prenom_personnel"
);
oci_bind_by_name($st, ':id', $id);

if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) {
        $managers[] = $row;
    }
    oci_free_statement($st);
}


// Ferme la connexion Oracle
oci_close($conn);


// Nom complet
$full = trim(($pers['PRENOM_PERSONNEL'] ?? '') . ' ' . ($pers['NOM_PERSONNEL'] ?? ''));

// Trouve le rôle actuel
$currentRole = '';
foreach ($historique as $h) {
    if (empty($h['DATE_FIN'])) {
        $currentRole = $h['NOM_ROLE'];
        break;
    }
}


// Configuration de la page
$page_title = 'Fiche personnel';
$page_css = '/assets/css/detail.css';
$page_hero = [
    'kicker' => 'Parcours individuel',
    'icon' => 'bi bi-person-vcard-fill',
    'title' => htmlspecialchars($full),
    'desc' => 'Historique depuis l\'arrivée, rôle actuel, animaux suivis, soins et nourrissages réalisés.',
    'image' => url_site('/assets/img/personnel-hero.svg'),
    'actions' => array_filter([
        $peut_mod ? ['label' => 'Modifier', 'icon' => 'bi bi-pencil-fill', 'target' => '#modalEdit', 'class' => 'btn-primary'] : null,
        ['label' => 'Retour', 'icon' => 'bi bi-arrow-left', 'href' => url_site('/personnel/index.php'), 'class' => 'btn-ghost'],
    ]),
    'stats' => [
        ['value' => $currentRole ?: '—', 'label' => 'rôle actuel'],
        ['value' => count($historique), 'label' => 'étapes carrière'],
        ['value' => count($animaux), 'label' => 'animaux suivis'],
        ['value' => count($soins) + count($nourrissages), 'label' => 'actes visibles'],
    ],
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

      <!-- Message -->
      <?php render_alert($message, $type); ?>

      <!-- Contenu détail -->
      <div class="detail-shell reveal">

        <!-- Infos générales -->
        <section class="detail-panel">
          <div class="kv-grid">
            <div class="kv-card">
              <div class="kv-label">Identifiant</div>
              <div class="kv-value">#<?php echo $pers['ID_PERSONNEL']; ?></div>
            </div>

            <div class="kv-card">
              <div class="kv-label">Entrée</div>
              <div class="kv-value"><?php echo format_date_fr($pers['DATE_ENTREE']); ?></div>
            </div>

            <div class="kv-card">
              <div class="kv-label">Salaire</div>
              <div class="kv-value">
                <?php echo !empty($pers['SALAIRE']) ? number_format((float)$pers['SALAIRE'], 0, ',', ' ') . ' €' : '—'; ?>
              </div>
            </div>

            <div class="kv-card">
              <div class="kv-label">Manager</div>
              <div class="kv-value">
                <?php echo !empty($pers['MGR_NOM']) ? htmlspecialchars(($pers['MGR_PRENOM'] ?? '') . ' ' . $pers['MGR_NOM']) : '—'; ?>
              </div>
            </div>
          </div>

          <!-- Historique -->
          <div class="page-header-row mt-4">
            <div>
              <div class="overline">Parcours</div>
              <h2 class="page-title-sm">Chronologie d'emploi</h2>
            </div>
          </div>

          <div class="timeline">
            <?php foreach ($historique as $h): ?>
            <div class="timeline-item my-2">
              <div class="timeline-card">
                <div style="display:flex;justify-content:space-between;gap:.8rem;align-items:center;flex-wrap:wrap">
                  <strong><?php echo htmlspecialchars($h['NOM_ROLE']); ?></strong>
                  <?php if (empty($h['DATE_FIN'])): ?>
                    <span class="badge-soft badge-emerald">Actuel</span>
                  <?php else: ?>
                    <span class="badge-soft badge-amber">Terminé</span>
                  <?php endif; ?>
                </div>
                <div class="text-muted" style="margin-top:.25rem">
                  <?php echo format_date_fr($h['DATE_DEBUT']); ?>
                  <?php if (!empty($h['DATE_FIN'])): ?>
                    → <?php echo format_date_fr($h['DATE_FIN']); ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </section>

        <!-- Partie opérationnelle -->
        <section class="detail-panel">
          <div class="page-header-row">
            <div>
              <div class="overline">Fiche opérationnelle</div>
              <h2 class="page-title-sm">Animaux, soins et nourrissages</h2>
            </div>
          </div>

          <div class="stack-list">
            <div class="stack-item">
              <strong>Spécialités</strong>
              <div class="text-muted mt-1">
                <?php echo $specialites ? htmlspecialchars(implode(', ', $specialites)) : 'Aucune spécialité renseignée'; ?>
              </div>
            </div>

            <div class="stack-item">
              <strong>Animaux suivis</strong>
              <div class="text-muted mt-1">
                <?php if (!$animaux) { echo 'Aucun animal affecté'; } ?>
              </div>
              <?php foreach ($animaux as $a): ?>
              <div class="mini-row mt-2">
                <span>
                  <?php echo htmlspecialchars($a['NOM_ANIMAL']); ?>
                  <span class="text-muted">(<?php echo htmlspecialchars($a['NOM_USUEL'] ?? ''); ?>)</span>
                </span>
                <strong><?php echo htmlspecialchars($a['NOM_ZONE'] ?? '—'); ?></strong>
              </div>
              <?php endforeach; ?>
            </div>

            <div class="stack-item">
              <strong>Derniers soins</strong>
              <?php if (!$soins): ?>
                <div class="text-muted mt-1">Aucun soin trouvé.</div>
              <?php endif; ?>
              <?php foreach ($soins as $s): ?>
              <div class="mini-row mt-2">
                <span><?php echo htmlspecialchars($s['NOM_SOIN'] ?? 'Soin'); ?> — <?php echo htmlspecialchars($s['NOM_ANIMAL'] ?? ''); ?></span>
                <strong><?php echo format_date_fr($s['DATE_SOIN']); ?></strong>
              </div>
              <?php endforeach; ?>
            </div>

            <div class="stack-item">
              <strong>Derniers nourrissages</strong>
              <?php if (!$nourrissages): ?>
                <div class="text-muted mt-1">Aucun nourrissage trouvé.</div>
              <?php endif; ?>
              <?php foreach ($nourrissages as $n): ?>
              <div class="mini-row mt-2">
                <span><?php echo htmlspecialchars($n['NOM_ALIMENT'] ?? 'Repas'); ?> — <?php echo htmlspecialchars($n['NOM_ANIMAL'] ?? ''); ?></span>
                <strong><?php echo format_date_fr($n['DATE_NOURRISSAGE']); ?></strong>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </section>
      </div>

      <!-- Modale modification -->
      <?php if ($peut_mod): ?>
      <div class="modal fade" id="modalEdit" tabindex="-1">
        <div class="modal-dialog modal-lg">
          <div class="modal-content" id="edition">

            <div class="modal-header">
              <h5 class="modal-title fw-bold">Modifier la fiche</h5>
              <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
              <form method="POST">
                <input type="hidden" name="action" value="modifier">

                <div class="grid-auto">
                  <div>
                    <label class="form-label">Nom</label>
                    <input class="form-control" name="nom_personnel" value="<?php echo htmlspecialchars($pers['NOM_PERSONNEL']); ?>" required>
                  </div>

                  <div>
                    <label class="form-label">Prénom</label>
                    <input class="form-control" name="prenom_personnel" value="<?php echo htmlspecialchars($pers['PRENOM_PERSONNEL']); ?>" required>
                  </div>

                  <div>
                    <label class="form-label">Salaire</label>
                    <input class="form-control" type="number" step="0.01" name="salaire" value="<?php echo htmlspecialchars((string)$pers['SALAIRE']); ?>">
                  </div>

                  <div>
                    <label class="form-label">Date d'entrée</label>
                    <input class="form-control" type="date" name="date_entree" value="<?php echo format_date_input($pers['DATE_ENTREE']); ?>" required>
                  </div>

                  <div>
                    <label class="form-label">Manager</label>
                    <select class="form-select" name="id_manager">
                      <option value="">— Aucun —</option>
                      <?php foreach ($managers as $m): ?>
                      <option value="<?php echo $m['ID_PERSONNEL']; ?>" <?php echo ((int)$pers['ID_MANAGER'] === (int)$m['ID_PERSONNEL']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($m['PRENOM_PERSONNEL'] . ' ' . $m['NOM_PERSONNEL']); ?>
                      </option>
                      <?php endforeach; ?>
                    </select>
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

<script src="<?php echo htmlspecialchars(url_site('/assets/vendor/bootstrap/js/bootstrap.bundle.min.js')); ?>"></script>
<script>
// Animation apparition
document.querySelectorAll('.reveal').forEach(function(el, i){
  setTimeout(function(){
    el.classList.add('visible');
  }, 80 + i * 35);
});

// Affichage conditionnel
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
