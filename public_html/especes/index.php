<?php
require_once '../includes/auth.php';
require_role(['admin','dirigeant','soigneur','soigneur_chef','veterinaire']);
require_once '../includes/path.php';
require_once '../includes/helpers.php';
require_once '../config.php';

$peut_mod = in_array(get_role(), ['admin', 'dirigeant'], true);
$msg = '';
$msg_type = '';

/* =========================
   SUPPRIMER ESPECE
========================= */
if ($peut_mod && isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];

    $st = oci_parse($conn, "DELETE FROM Espece WHERE id_espece = :id");
    oci_bind_by_name($st, ':id', $id);
    $ok = oci_execute($st);

    $msg = $ok ? 'Espèce supprimée.' : 'Erreur lors de la suppression.';
    $msg_type = $ok ? 'success' : 'danger';
    oci_free_statement($st);
}

/* =========================
   AJOUTER ESPECE
========================= */
if ($peut_mod && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ajouter') {
    $nu = trim($_POST['nom_usuel'] ?? '');
    $nl = trim($_POST['nom_latin'] ?? '');
    $em = (int)($_POST['est_menacee'] ?? 0);

    if ($nu === '') {
        $msg = 'Le nom usuel est obligatoire.';
        $msg_type = 'danger';
    } else {
        $st = oci_parse($conn, "SELECT NVL(MAX(id_espece),0)+1 FROM Espece");
        oci_execute($st);
        $r = oci_fetch_array($st, OCI_NUM);
        $nid = (int)($r[0] ?? 1);
        oci_free_statement($st);

        $st = oci_parse($conn, "
            INSERT INTO Espece(id_espece, nom_usuel, nom_latin, est_menacee)
            VALUES(:id_espece, :nom_usuel, :nom_latin, :est_menacee)
        ");
        oci_bind_by_name($st, ':id_espece', $nid);
        oci_bind_by_name($st, ':nom_usuel', $nu);
        oci_bind_by_name($st, ':nom_latin', $nl);
        oci_bind_by_name($st, ':est_menacee', $em);

        $ok = oci_execute($st);
        $msg = $ok ? 'Espèce ajoutée !' : 'Erreur lors de l\'ajout.';
        $msg_type = $ok ? 'success' : 'danger';
        oci_free_statement($st);
    }
}

/* =========================
   AJOUTER COHABITATION
========================= */
if ($peut_mod && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ajouter_cohabitation') {
    $id1 = (int)($_POST['id_espece1'] ?? 0);
    $id2 = (int)($_POST['id_espece2'] ?? 0);

    if ($id1 <= 0 || $id2 <= 0) {
        $msg = 'Veuillez choisir deux espèces.';
        $msg_type = 'danger';
    } elseif ($id1 === $id2) {
        $msg = 'Une espèce ne peut pas cohabiter avec elle-même.';
        $msg_type = 'danger';
    } else {
        $a = min($id1, $id2);
        $b = max($id1, $id2);

        $st = oci_parse($conn, "
            SELECT COUNT(*)
            FROM Cohabiter
            WHERE id_espece1 = :id_espece1
              AND id_espece2 = :id_espece2
        ");
        oci_bind_by_name($st, ':id_espece1', $a);
        oci_bind_by_name($st, ':id_espece2', $b);
        oci_execute($st);
        $r = oci_fetch_array($st, OCI_NUM);
        $existe = (int)($r[0] ?? 0) > 0;
        oci_free_statement($st);

        if ($existe) {
            $msg = 'Cette cohabitation existe déjà.';
            $msg_type = 'danger';
        } else {
            $st = oci_parse($conn, "
                INSERT INTO Cohabiter(id_espece1, id_espece2)
                VALUES(:id_espece1, :id_espece2)
            ");
            oci_bind_by_name($st, ':id_espece1', $a);
            oci_bind_by_name($st, ':id_espece2', $b);
            $ok = oci_execute($st);

            $msg = $ok ? 'Cohabitation ajoutée.' : 'Erreur lors de l\'ajout de la cohabitation.';
            $msg_type = $ok ? 'success' : 'danger';
            oci_free_statement($st);
        }
    }
}

/* =========================
   SUPPRIMER COHABITATION
========================= */
if ($peut_mod && isset($_GET['supprimer_cohab1'], $_GET['supprimer_cohab2'])) {
    $id1 = (int)$_GET['supprimer_cohab1'];
    $id2 = (int)$_GET['supprimer_cohab2'];

    if ($id1 > 0 && $id2 > 0) {
        $a = min($id1, $id2);
        $b = max($id1, $id2);

        $st = oci_parse($conn, "
            DELETE FROM Cohabiter
            WHERE id_espece1 = :id_espece1
              AND id_espece2 = :id_espece2
        ");
        oci_bind_by_name($st, ':id_espece1', $a);
        oci_bind_by_name($st, ':id_espece2', $b);
        $ok = oci_execute($st);

        $msg = $ok ? 'Cohabitation supprimée.' : 'Erreur lors de la suppression de la cohabitation.';
        $msg_type = $ok ? 'success' : 'danger';
        oci_free_statement($st);
    }
}

/* =========================
   DONNEES ESPECES
========================= */
$especes = [];
$st = oci_parse($conn, "
    SELECT e.id_espece,
           e.nom_usuel,
           e.nom_latin,
           e.est_menacee,
           (SELECT COUNT(*) FROM Animal a WHERE a.id_espece = e.id_espece) nb_animaux
    FROM Espece e
    ORDER BY e.nom_usuel
");
if ($st && oci_execute($st)) {
    while ($l = oci_fetch_assoc($st)) {
        $especes[] = $l;
    }
    oci_free_statement($st);
}

/* =========================
   DONNEES COHABITATIONS
========================= */
$cohab = [];
$st = oci_parse($conn, "
    SELECT c.id_espece1,
           c.id_espece2,
           e1.nom_usuel AS n1,
           e2.nom_usuel AS n2
    FROM Cohabiter c
    JOIN Espece e1 ON c.id_espece1 = e1.id_espece
    JOIN Espece e2 ON c.id_espece2 = e2.id_espece
    ORDER BY e1.nom_usuel, e2.nom_usuel
");
if ($st && oci_execute($st)) {
    while ($l = oci_fetch_assoc($st)) {
        $cohab[] = $l;
    }
    oci_free_statement($st);
}

$cmap = [];
foreach ($cohab as $c) {
    $cmap[$c['ID_ESPECE1']][] = $c['N2'];
    $cmap[$c['ID_ESPECE2']][] = $c['N1'];
}

oci_close($conn);

$nb_menacees = count(array_filter($especes, fn($e) => (int)$e['EST_MENACEE'] === 1));
$nb_total = count($especes);

/* Image unique pour toutes les espèces */
function espece_img(): string {
    return url_site('/assets/img/detail-hero.svg');
}

$page_title = 'Espèces';
$page_css = '/assets/css/especes.css';
$page_hero = [
    'kicker' => 'Catalogue faune',
    'icon'   => 'bi bi-diagram-3-fill',
    'title'  => 'Espèces du Zoo\'land',
    'desc'   => 'Catalogue des espèces, statuts de conservation et cohabitations autorisées.',
    'image'  => url_site('/assets/img/species-hero.svg'),
    'actions'=> array_filter([
        $peut_mod ? ['label' => 'Nouvelle espèce', 'icon' => 'bi bi-plus-lg', 'target' => '#modalAjouter', 'class' => 'btn-primary'] : null,
        $peut_mod ? ['label' => 'Nouvelle cohabitation', 'icon' => 'bi bi-link-45deg', 'target' => '#modalAjouterCohab', 'class' => 'btn-light-surface'] : null,
        ['label' => 'Dashboard', 'icon' => 'bi bi-arrow-left', 'href' => url_site('/index.php'), 'class' => 'btn-ghost'],
    ]),
    'stats' => [
        ['value' => $nb_total, 'label' => 'espèces cataloguées'],
        ['value' => $nb_menacees, 'label' => 'espèces menacées'],
        ['value' => count($cohab), 'label' => 'paires cohabitation'],
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
            <h1 class="hero-title"><?php echo $heroTitle; ?></h1>
            <?php if ($heroDesc): ?><p class="hero-desc"><?php echo htmlspecialchars($heroDesc); ?></p><?php endif; ?>
            <?php if ($heroActions): ?>
            <div class="hero-actions">
              <?php foreach ($heroActions as $action): if (!$action) continue; $class = $action['class'] ?? 'btn-primary'; ?>
                <?php if (!empty($action['href'])): ?>
                  <a class="btn <?php echo htmlspecialchars($class); ?>" href="<?php echo htmlspecialchars($action['href']); ?>">
                    <?php if (!empty($action['icon'])): ?><i class="<?php echo htmlspecialchars($action['icon']); ?>"></i><?php endif; ?>
                    <?php echo htmlspecialchars($action['label'] ?? ''); ?>
                  </a>
                <?php else: ?>
                  <button class="btn <?php echo htmlspecialchars($class); ?>" type="button"<?php if (!empty($action['target'])): ?> data-bs-toggle="modal" data-bs-target="<?php echo htmlspecialchars($action['target']); ?>"<?php endif; ?>>
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

      <?php render_alert($msg, $msg_type); ?>

      <div class="search-toolbar reveal mb-4">
        <div class="search-box">
          <i class="bi bi-search"></i>
          <input type="search" id="rechercheEspeces" class="search-input" placeholder="Rechercher une espèce, un nom latin ou une cohabitation...">
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <button class="filter-btn active" data-f="all">
            <i class="bi bi-grid-3x3-gap me-1"></i> Toutes (<?php echo $nb_total; ?>)
          </button>
          <button class="filter-btn" data-f="menace">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> Menacées (<?php echo $nb_menacees; ?>)
          </button>
        </div>
      </div>

      <div class="grid-especes" id="especesGrid">
      <?php if (empty($especes)): ?>
        <div style="grid-column:1/-1;text-align:center;padding:3rem;color:var(--txt-muted)">
          <i class="bi bi-diagram-3" style="font-size:2.5rem;opacity:.3;display:block;margin-bottom:.75rem"></i>
          Aucune espèce enregistrée
        </div>
      <?php else: foreach ($especes as $e):
          $menace = (int)$e['EST_MENACEE'];
          $nb = (int)$e['NB_ANIMAUX'];
          $img = espece_img();
          $cohs = $cmap[$e['ID_ESPECE']] ?? [];
      ?>
        <div class="espece-card reveal" data-m="<?php echo $menace; ?>" data-search="<?php echo htmlspecialchars(strtolower(trim(($e['NOM_USUEL'] ?? '') . ' ' . ($e['NOM_LATIN'] ?? '') . ' ' . implode(' ', $cohs))), ENT_QUOTES); ?>">
          <div class="espece-img-wrap">
            <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($e['NOM_USUEL']); ?>" loading="lazy">
            <div class="espece-img-overlay"></div>
            <?php if ($menace): ?>
            <div class="espece-img-badge">
              <span class="bdg bdg-r"><i class="bi bi-exclamation-triangle-fill"></i> Menacée</span>
            </div>
            <?php endif; ?>
          </div>

          <div class="espece-body">
            <div class="espece-name"><?php echo htmlspecialchars($e['NOM_USUEL']); ?></div>
            <div class="espece-latin"><?php echo htmlspecialchars($e['NOM_LATIN'] ?? '—'); ?></div>

            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
              <?php if ($menace): ?>
              <span class="bdg bdg-r"><i class="bi bi-exclamation-triangle-fill"></i> En danger</span>
              <?php endif; ?>
              <span class="bdg bdg-t"><i class="bi bi-heart-pulse-fill"></i> <?php echo $nb; ?> animal<?php echo $nb !== 1 ? 's' : ''; ?></span>
            </div>

            <?php if (!empty($cohs)): ?>
            <div class="overline mb-1" style="font-size:.62rem">Cohabite avec</div>
            <div class="d-flex flex-wrap gap-1 mb-2">
              <?php foreach ($cohs as $c): ?>
              <span class="cohab-chip"><i class="bi bi-link"></i><?php echo htmlspecialchars($c); ?></span>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="d-flex gap-2 mt-2 pt-2 flex-wrap" style="border-top:1px solid var(--border)">
              <a href="<?php echo htmlspecialchars(url_site('/especes/detail.php?id=' . $e['ID_ESPECE'])); ?>" class="btn btn-light-surface" style="font-size:.78rem;padding:.38rem .8rem">
                <i class="bi bi-eye-fill"></i> Détails
              </a>
              <?php if ($peut_mod): ?>
              <a href="?supprimer=<?php echo $e['ID_ESPECE']; ?>"
                 onclick="return confirm('Supprimer <?php echo htmlspecialchars(addslashes($e['NOM_USUEL'])); ?> ?')"
                 class="btn btn-light-surface" style="font-size:.78rem;padding:.38rem .8rem;color:var(--rose)">
                <i class="bi bi-trash-fill"></i> Supprimer
              </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; endif; ?>
      </div>

      <div class="section-card reveal mt-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
          <div>
            <div class="page-title-sm">Gestion des cohabitations</div>
            <div class="text-muted" style="font-size:.84rem"><?php echo count($cohab); ?> paire<?php echo count($cohab) > 1 ? 's' : ''; ?> enregistrée<?php echo count($cohab) > 1 ? 's' : ''; ?></div>
          </div>
          <?php if ($peut_mod): ?>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAjouterCohab">
            <i class="bi bi-link-45deg"></i> Ajouter une cohabitation
          </button>
          <?php endif; ?>
        </div>

        <?php if (empty($cohab)): ?>
          <div class="text-muted">Aucune cohabitation enregistrée.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-lite" style="margin:0">
              <thead>
                <tr>
                  <th>Espèce 1</th>
                  <th>Espèce 2</th>
                  <?php if ($peut_mod): ?><th></th><?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($cohab as $c): ?>
                <tr>
                  <td><?php echo htmlspecialchars($c['N1']); ?></td>
                  <td><?php echo htmlspecialchars($c['N2']); ?></td>
                  <?php if ($peut_mod): ?>
                  <td class="text-end">
                    <a href="?supprimer_cohab1=<?php echo (int)$c['ID_ESPECE1']; ?>&supprimer_cohab2=<?php echo (int)$c['ID_ESPECE2']; ?>"
                       class="btn btn-light-surface"
                       style="font-size:.78rem;padding:.38rem .8rem;color:var(--rose)"
                       onclick="return confirm('Supprimer la cohabitation entre <?php echo htmlspecialchars(addslashes($c['N1'])); ?> et <?php echo htmlspecialchars(addslashes($c['N2'])); ?> ?')">
                      <i class="bi bi-trash-fill"></i> Supprimer
                    </a>
                  </td>
                  <?php endif; ?>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($peut_mod): ?>
      <div class="modal fade" id="modalAjouter" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Nouvelle espèce</h5>
              <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <form method="POST">
                <input type="hidden" name="action" value="ajouter">
                <div class="mb-3">
                  <label class="form-label">Nom usuel *</label>
                  <input type="text" name="nom_usuel" class="form-control" placeholder="Ex: Lion" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">Nom scientifique</label>
                  <input type="text" name="nom_latin" class="form-control" placeholder="Ex: Panthera leo">
                </div>
                <div class="mb-3">
                  <label class="form-label">Statut de conservation</label>
                  <select name="est_menacee" class="form-select">
                    <option value="1">⚠️ Espèce menacée</option>
                    <option value="0">Non menacée</option>
                  </select>
                </div>
                <div class="d-flex justify-content-end gap-2">
                  <button type="button" class="btn btn-light-surface" data-bs-dismiss="modal">Annuler</button>
                  <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Ajouter</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>

      <div class="modal fade" id="modalAjouterCohab" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title fw-bold"><i class="bi bi-link-45deg me-2"></i>Nouvelle cohabitation</h5>
              <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <form method="POST">
                <input type="hidden" name="action" value="ajouter_cohabitation">

                <div class="mb-3">
                  <label class="form-label">Espèce 1</label>
                  <select name="id_espece1" class="form-select" required>
                    <option value="">— Choisir —</option>
                    <?php foreach ($especes as $e): ?>
                    <option value="<?php echo (int)$e['ID_ESPECE']; ?>">
                      <?php echo htmlspecialchars($e['NOM_USUEL']); ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="mb-3">
                  <label class="form-label">Espèce 2</label>
                  <select name="id_espece2" class="form-select" required>
                    <option value="">— Choisir —</option>
                    <?php foreach ($especes as $e): ?>
                    <option value="<?php echo (int)$e['ID_ESPECE']; ?>">
                      <?php echo htmlspecialchars($e['NOM_USUEL']); ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="d-flex justify-content-end gap-2">
                  <button type="button" class="btn btn-light-surface" data-bs-dismiss="modal">Annuler</button>
                  <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Enregistrer
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

<script>
(function(){
  var filtreActif = 'all';
  var input = document.getElementById('rechercheEspeces');
  var cartes = Array.from(document.querySelectorAll('.espece-card'));

  function appliquerFiltres(){
    var terme = (input.value || '').toLowerCase().trim();
    cartes.forEach(function(carte){
      var menace = carte.dataset.m;
      var texte = (carte.dataset.search || '').toLowerCase();
      var okFiltre = filtreActif === 'all' || (filtreActif === 'menace' && menace === '1');
      var okRecherche = terme === '' || texte.indexOf(terme) !== -1;
      carte.style.display = (okFiltre && okRecherche) ? '' : 'none';
    });
  }

  document.querySelectorAll('.filter-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      document.querySelectorAll('.filter-btn').forEach(function(b){ b.classList.remove('active'); });
      btn.classList.add('active');
      filtreActif = btn.dataset.f;
      appliquerFiltres();
    });
  });

  if (input) input.addEventListener('input', appliquerFiltres);
})();
</script>
    </div>
  </main>
</div>

<script src="<?php echo htmlspecialchars(url_site('/assets/vendor/bootstrap/js/bootstrap.bundle.min.js')); ?>"></script>
<script>
document.querySelectorAll('.reveal').forEach(function(el,i){
  setTimeout(function(){ el.classList.add('visible'); }, 80 + i * 35);
});

document.querySelectorAll('[data-toggle-extern]').forEach(function(box){
  function sync(){
    var t = document.getElementById(box.dataset.toggleExtern);
    if(!t) return;
    document.querySelectorAll(box.dataset.target).forEach(function(el){ el.style.display = t.checked ? '' : 'none'; });
    document.querySelectorAll(box.dataset.altTarget).forEach(function(el){ el.style.display = t.checked ? 'none' : ''; });
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
