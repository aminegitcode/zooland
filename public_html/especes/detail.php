<?php
// Accès + fichiers nécessaires
require_once '../includes/auth.php';
require_role(['admin','dirigeant','soigneur','soigneur_chef','veterinaire']);

require_once '../includes/path.php';
require_once '../includes/helpers.php';
require_once '../config.php';


// Récupère l'id de l'espèce
$id_espece = (int)($_GET['id'] ?? 0);

// Vérifie l'id
if ($id_espece <= 0) {
    header('Location: ' . url_site('/especes/index.php'));
    exit;
}


// Récupère la fiche de l'espèce
$espece = null;
$st = oci_parse($conn, "
    SELECT e.id_espece,
           e.nom_usuel,
           e.nom_latin,
           e.est_menacee,
           (SELECT COUNT(*) FROM Animal a WHERE a.id_espece = e.id_espece) nb_animaux
    FROM Espece e
    WHERE e.id_espece = :id
");
oci_bind_by_name($st, ':id', $id_espece);

if ($st && oci_execute($st)) {
    $espece = oci_fetch_assoc($st);
    oci_free_statement($st);
}

// Si l'espèce n'existe pas
if (!$espece) {
    oci_close($conn);
    header('Location: ' . url_site('/especes/index.php'));
    exit;
}


// Liste des animaux de l'espèce
$animaux = [];
$sql_animaux = "
    SELECT a.rfid,
           a.nom_animal,
           a.date_naissance,
           a.poids,
           a.regime_alimentaire,
           a.zoo,
           en.id_enclos,
           z.nom_zone,
           p.prenom_personnel,
           p.nom_personnel
    FROM Animal a
    LEFT JOIN Enclos en ON a.id_enclos = en.id_enclos
    LEFT JOIN Zone z ON en.id_zone = z.id_zone
    LEFT JOIN Personnel p ON a.id_personnel = p.id_personnel
    WHERE a.id_espece = :id
    ORDER BY a.nom_animal, a.rfid
";
$st = oci_parse($conn, $sql_animaux);
oci_bind_by_name($st, ':id', $id_espece);

if ($st && oci_execute($st)) {
    while ($r = oci_fetch_assoc($st)) {
        $animaux[] = $r;
    }
    oci_free_statement($st);
}


// Liste des espèces compatibles en cohabitation
$cohab = [];
$sql_cohab = "
    SELECT DISTINCT
           CASE
               WHEN c.id_espece1 = :id THEN e2.nom_usuel
               ELSE e1.nom_usuel
           END AS NOM_COHAB
    FROM Cohabiter c
    JOIN Espece e1 ON c.id_espece1 = e1.id_espece
    JOIN Espece e2 ON c.id_espece2 = e2.id_espece
    WHERE c.id_espece1 = :id OR c.id_espece2 = :id
    ORDER BY NOM_COHAB
";
$st = oci_parse($conn, $sql_cohab);
oci_bind_by_name($st, ':id', $id_espece);

if ($st && oci_execute($st)) {
    while ($r = oci_fetch_assoc($st)) {
        $cohab[] = $r['NOM_COHAB'];
    }
    oci_free_statement($st);
}


// Ferme la connexion Oracle
oci_close($conn);


// Formate une date
function fmt_date_espece($d): string
{
    if (empty($d)) return '—';

    $t = strtotime((string)$d);
    return $t ? date('d/m/Y', $t) : '—';
}


// Nombre d'animaux
$nb_animaux = (int)($espece['NB_ANIMAUX'] ?? 0);



$hero_img = url_site('/assets/img/animals-hero.svg');


// Configuration de la page
$page_title = 'Détail espèce';
$page_css = '/assets/css/especes.css';

$page_hero = [
    'kicker' => 'Fiche espèce',
    'icon'   => 'bi bi-diagram-3-fill',
    'title'  => (string)($espece['NOM_USUEL'] ?? 'Espèce'),
    'desc'   => 'Tous les animaux rattachés à cette espèce, avec leur enclos, leur zone et leur soigneur.',
    'image'  => $hero_img,
    'actions'=> [
        [
            'label' => 'Retour aux espèces',
            'icon'  => 'bi bi-arrow-left',
            'href'  => url_site('/especes/index.php'),
            'class' => 'btn-ghost'
        ],
        [
            'label' => 'Voir les animaux',
            'icon'  => 'bi bi-heart-pulse-fill',
            'href'  => '#liste-animaux',
            'class' => 'btn-light-surface'
        ],
    ],
    'stats' => [
        ['value' => $nb_animaux, 'label' => 'animaux de cette espèce'],
        ['value' => ((int)($espece['EST_MENACEE'] ?? 0) === 1 ? 'Oui' : 'Non'), 'label' => 'espèce menacée'],
        ['value' => count($cohab), 'label' => 'cohabitations'],
        ['value' => !empty($espece['NOM_LATIN']) ? $espece['NOM_LATIN'] : '—', 'label' => 'nom latin'],
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

      <!-- Bloc identité -->
      <div class="grid-auto reveal mb-4">
        <div class="section-card">
          <div class="overline mb-2">Identité</div>

          <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
            <span class="badge-soft <?php echo ((int)($espece['EST_MENACEE'] ?? 0) === 1) ? 'badge-rose' : ''; ?>">
              <?php echo ((int)($espece['EST_MENACEE'] ?? 0) === 1) ? 'Menacée' : ''; ?>
            </span>

            <?php if (!empty($espece['NOM_LATIN'])): ?>
              <span class="meta-item">
                <i class="bi bi-flower1"></i>
                <?php echo htmlspecialchars($espece['NOM_LATIN']); ?>
              </span>
            <?php endif; ?>

            <span class="meta-item">
              <i class="bi bi-heart-pulse-fill"></i>
              <?php echo $nb_animaux; ?> animal<?php echo $nb_animaux > 1 ? 'ux' : ''; ?>
            </span>
          </div>

          <?php if (!empty($cohab)): ?>
            <div class="overline mb-2 mt-3">Cohabite avec</div>
            <div class="d-flex flex-wrap gap-2">
              <?php foreach ($cohab as $nom): ?>
                <span class="cohab-chip">
                  <i class="bi bi-link"></i>
                  <?php echo htmlspecialchars($nom); ?>
                </span>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-muted mb-0">Aucune cohabitation enregistrée pour cette espèce.</p>
          <?php endif; ?>
        </div>

        <!-- Bloc résumé -->
        <div class="section-card">
          <div class="overline mb-2">Résumé</div>
          <p class="mb-2" style="color:var(--txt-muted)">
            Cette page regroupe tous les animaux de l'espèce
            <strong style="color:var(--txt)"><?php echo htmlspecialchars($espece['NOM_USUEL']); ?></strong>.
          </p>
          <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo htmlspecialchars(url_site('/animaux/index.php')); ?>" class="btn btn-light-surface">
              <i class="bi bi-grid-1x2-fill"></i> Registre animaux
            </a>
          </div>
        </div>
      </div>

      <!-- Tableau des animaux -->
      <div class="section-card reveal" id="liste-animaux" style="padding:0;overflow:hidden">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.95rem 1.15rem;border-bottom:1px solid var(--border);flex-wrap:wrap">
          <div>
            <div class="page-title-sm">Animaux de l'espèce</div>
            <div class="text-muted" style="font-size:.84rem">
              <?php echo $nb_animaux; ?> résultat<?php echo $nb_animaux > 1 ? 's' : ''; ?>
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-lite" style="margin:0">
            <thead>
              <tr>
                <th>Animal</th>
                <th>RFID</th>
                <th>Naissance</th>
                <th>Zone</th>
                <th>Enclos</th>
                <th>Soigneur</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($animaux as $animal):
                $nom_soigneur = trim(($animal['PRENOM_PERSONNEL'] ?? '') . ' ' . ($animal['NOM_PERSONNEL'] ?? ''));
              ?>
              <tr>
                <td>
                  <div style="font-weight:800;color:var(--txt)"><?php echo htmlspecialchars($animal['NOM_ANIMAL'] ?? '—'); ?></div>
                  <div style="font-size:.76rem;color:var(--txt-muted)"><?php echo htmlspecialchars($animal['REGIME_ALIMENTAIRE'] ?? '—'); ?></div>
                </td>
                <td style="font-family:monospace;font-size:.84rem"><?php echo htmlspecialchars($animal['RFID'] ?? '—'); ?></td>
                <td><?php echo fmt_date_espece($animal['DATE_NAISSANCE'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($animal['NOM_ZONE'] ?? '—'); ?></td>
                <td><?php echo !empty($animal['ID_ENCLOS']) ? 'Enclos ' . (int)$animal['ID_ENCLOS'] : '—'; ?></td>
                <td><?php echo $nom_soigneur !== '' ? htmlspecialchars($nom_soigneur) : '—'; ?></td>
                <td>
                  <a href="<?php echo htmlspecialchars(url_site('/animaux/detail.php?rfid=' . urlencode((string)($animal['RFID'] ?? '')))); ?>" class="btn btn-light-surface" style="padding:.5rem .85rem;font-size:.8rem">
                    <i class="bi bi-eye-fill"></i> Voir
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>

              <?php if (empty($animaux)): ?>
              <tr>
                <td colspan="7" style="text-align:center;padding:3rem;color:var(--txt-muted)">
                  <i class="bi bi-heart" style="font-size:2rem;opacity:.3;display:block;margin-bottom:.6rem"></i>
                  Aucun animal n'est encore rattaché à cette espèce.
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>

<script src="<?php echo htmlspecialchars(url_site('/assets/vendor/bootstrap/js/bootstrap.bundle.min.js')); ?>"></script>
<script>
// Animation apparition
document.querySelectorAll('.reveal').forEach(function(el,i){
  setTimeout(function(){ el.classList.add('visible'); }, 80 + i * 35);
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

// Filtres génériques si présents
document.querySelectorAll('[data-filter-scope]').forEach(function(panel){
  const scope = panel.dataset.filterScope;
  const search = panel.querySelector('[data-filter-search]');
  const selects = [...panel.querySelectorAll('[data-filter-attr]')];
  const items = [...document.querySelectorAll('[data-filter-group="'+scope+'"] [data-filter-item]')];

  function apply(){
    const query = (search?.value || '').trim().toLowerCase();

    items.forEach(function(item){
      const hay = (item.dataset.search || item.textContent || '').toLowerCase();
      let visible = !query || hay.includes(query);

      selects.forEach(function(sel){
        const attr = sel.dataset.filterAttr;
        const wanted = (sel.value || '').toLowerCase();
        const current = (item.dataset[attr] || '').toLowerCase();

        if (visible && wanted && current !== wanted) {
          visible = false;
        }
      });

      item.style.display = visible ? '' : 'none';
    });
  }

  search?.addEventListener('input', apply);
  selects.forEach(function(sel){
    sel.addEventListener('change', apply);
  });

  apply();
});
</script>
</body>
</html>
