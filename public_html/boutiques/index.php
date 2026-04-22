<?php
// Sécurité + accès
require_once '../includes/auth.php';
require_role(['admin','dirigeant','boutique','vendeur','comptable']);

// Fichiers utiles
require_once '../includes/path.php';
require_once '../includes/helpers.php';
require_once '../config.php';

// Vérifie si l'utilisateur peut modifier
$peut_modifier = in_array(get_role(), ['admin','dirigeant'], true);

// Message affiché à l'utilisateur
$message = '';
$type_message = 'success';


/* ======================
   AJOUT BOUTIQUE
====================== */
if (
    $peut_modifier &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'ajouter'
) {
    // Données du formulaire
    $type    = trim($_POST['type_boutique'] ?? 'Autres');
    $id_pers = !empty($_POST['id_personnel']) ? (int)$_POST['id_personnel'] : null;
    $id_zone = !empty($_POST['id_zone']) ? (int)$_POST['id_zone'] : null;

    // Générer nouvel ID
    $st = oci_parse($conn, "SELECT NVL(MAX(id_boutique),0)+1 FROM Boutique");
    oci_execute($st);
    $r   = oci_fetch_array($st, OCI_NUM);
    $nxt = (int)($r[0] ?? 1);
    oci_free_statement($st);

    // Insert en base
    $st = oci_parse($conn, "
        INSERT INTO Boutique(id_boutique, type_boutique, id_personnel, id_zone)
        VALUES(:id, :t, :p, :z)
    ");
    oci_bind_by_name($st, ':id', $nxt);
    oci_bind_by_name($st, ':t', $type);
    oci_bind_by_name($st, ':p', $id_pers);
    oci_bind_by_name($st, ':z', $id_zone);

    $ok = oci_execute($st);
    if ($st) oci_free_statement($st);

    // Message résultat
    $message = $ok ? 'Boutique ajoutée.' : 'Erreur.';
    $type_message = $ok ? 'success' : 'danger';
}


/* ======================
   RÉCUPÉRATION DONNÉES
====================== */

// Liste boutiques
$boutiques = [];
$st = oci_parse($conn, "
    SELECT b.id_boutique, b.type_boutique, z.nom_zone,
           p.prenom_personnel, p.nom_personnel
    FROM Boutique b
    LEFT JOIN Zone z ON b.id_zone = z.id_zone
    LEFT JOIN Personnel p ON b.id_personnel = p.id_personnel
    ORDER BY b.id_boutique
");
if ($st && oci_execute($st)) {
    while ($r = oci_fetch_assoc($st)) $boutiques[] = $r;
    oci_free_statement($st);
}


// Chiffre d'affaires
$ca_data = [];
$st = oci_parse($conn, "
    SELECT id_boutique, date_ca, montant
    FROM Chiffre_affaires
    ORDER BY id_boutique, date_ca DESC
");
if ($st && oci_execute($st)) {
    while ($r = oci_fetch_assoc($st)) $ca_data[] = $r;
    oci_free_statement($st);
}

// Regroupement CA par boutique
$ca_by = [];
foreach ($ca_data as $c) {
    $ca_by[$c['ID_BOUTIQUE']][] = $c;
}


// Employés par boutique
$staff_data = [];
$st = oci_parse($conn, "
    SELECT t.id_boutique, p.prenom_personnel, p.nom_personnel
    FROM Travailler t
    JOIN Personnel p ON t.id_personnel = p.id_personnel
    ORDER BY t.id_boutique, p.nom_personnel
");
if ($st && oci_execute($st)) {
    while ($r = oci_fetch_assoc($st)) $staff_data[] = $r;
    oci_free_statement($st);
}

// Regroupement staff
$staff_by = [];
foreach ($staff_data as $s) {
    $staff_by[$s['ID_BOUTIQUE']][] =
        trim(($s['PRENOM_PERSONNEL'] ?? '') . ' ' . ($s['NOM_PERSONNEL'] ?? ''));
}


// Liste personnel (formulaire)
$liste_pers = [];
$st = oci_parse($conn, "
    SELECT id_personnel, nom_personnel, prenom_personnel
    FROM Personnel
    ORDER BY nom_personnel
");
if ($st && oci_execute($st)) {
    while ($r = oci_fetch_assoc($st)) $liste_pers[] = $r;
    oci_free_statement($st);
}


// Liste zones
$liste_zones = [];
$st = oci_parse($conn, "
    SELECT id_zone, nom_zone
    FROM Zone
    ORDER BY nom_zone
");
if ($st && oci_execute($st)) {
    while ($r = oci_fetch_assoc($st)) $liste_zones[] = $r;
    oci_free_statement($st);
}


// Total CA global
$total_ca = array_sum(array_map(fn($c) => (float)$c['MONTANT'], $ca_data));

// Fermeture connexion
oci_close($conn);


/* ======================
   FONCTIONS UTILES
====================== */

// Format euro
function feur(float $v): string {
    return number_format($v, 0, ',', ' ') . ' €';
}

// Format date CA
function fdate_ca($d): string {
    if (empty($d)) return '—';
    $t = strtotime((string)$d);
    return $t ? date('m/Y', $t) : '—';
}

// Max CA
function ca_max_b(array $l): float {
    return $l ? max(array_map(fn($c) => (float)$c['MONTANT'], $l)) : 1;
}


/* ======================
   CONFIG PAGE
====================== */
$page_title = 'Boutiques';
$page_css = '/assets/css/boutiques.css';

// Données du hero (haut de page)
$page_hero = [
    'kicker' => 'Retail & Revenus',
    'icon'   => 'bi bi-shop-window',
    'title'  => 'Boutiques du parc',
    'desc'   => 'Vue claire de chaque boutique : équipe, CA récent et accès fiche détaillée.',
    'image'  => url_site('/assets/img/shops-hero.svg'),

    // Boutons
    'actions' => array_filter([
        $peut_modifier ? [
            'label'  => 'Ajouter une boutique',
            'icon'   => 'bi bi-plus-lg',
            'target' => '#mAjout',
            'class'  => 'btn-primary'
        ] : null,

        [
            'label' => 'Dashboard',
            'icon'  => 'bi bi-arrow-left',
            'href'  => url_site('/index.php'),
            'class' => 'btn-ghost'
        ],
    ]),

    // Statistiques
    'stats' => [
        ['value' => count($boutiques), 'label' => 'boutiques'],
        ['value' => feur($total_ca),   'label' => 'CA total'],
        ['value' => count($staff_data),'label' => 'affectations'],
        ['value' => count($liste_zones),'label' => 'zones'],
    ],
];
?>
<?php
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
                    <?php if (!empty($action['icon'])): ?><i class="<?php echo htmlspecialchars($action['icon']); ?>"></i> <?php endif; ?>
                    <?php echo htmlspecialchars($action['label'] ?? ''); ?>
                  </a>
                <?php else: ?>
                  <button class="btn <?php echo htmlspecialchars($class); ?>" type="button"<?php if (!empty($action['target'])): ?> data-bs-toggle="modal" data-bs-target="<?php echo htmlspecialchars($action['target']); ?>"<?php endif; ?>>
                    <?php if (!empty($action['icon'])): ?><i class="<?php echo htmlspecialchars($action['icon']); ?>"></i> <?php endif; ?>
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
render_alert($message,$type_message);
?>

<div class="search-toolbar reveal mb-4">
  <div class="search-box">
    <i class="bi bi-search"></i>
    <input type="search" id="rechercheBoutiques" class="search-input" placeholder="Rechercher une boutique, une zone ou un responsable...">
  </div>
  <div>
    <select id="filtreTypeBoutique" class="form-select search-select">
      <option value="">Tous les types</option>
      <?php foreach(array_values(array_unique(array_filter(array_map(fn($b)=>$b['TYPE_BOUTIQUE']??'',$boutiques)))) as $tb): ?>
      <option value="<?php echo htmlspecialchars(strtolower($tb)); ?>"><?php echo htmlspecialchars($tb); ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<div class="section-card reveal" style="padding:1.1rem">
<div class="boutique-grid" data-filter-group="shops">
<?php foreach($boutiques as $b):
    $bid    = $b['ID_BOUTIQUE'];
    $caList = $ca_by[$bid] ?? [];
    $caTotal= array_sum(array_map(fn($c)=>(float)$c['MONTANT'],$caList));
    $staff  = $staff_by[$bid] ?? [];
    $type_b = $b['TYPE_BOUTIQUE'] ?? 'Autres';
    $mgr    = trim(($b['PRENOM_PERSONNEL']??'').' '.($b['NOM_PERSONNEL']??'')) ?: '—';
    $search = strtolower('boutique '.$bid.' '.$type_b.' '.($b['NOM_ZONE']??'').' '.$mgr.' '.implode(' ',$staff));
    $barClass = match(strtolower($type_b)){
        'souvenirs'=>'bc-bar-souvenirs',
        'snack'    =>'bc-bar-snack',
        'photo'    =>'bc-bar-photo',
        default    =>'bc-bar-autres'
    };
?>
<article class="b-card" data-filter-item
         data-type="<?php echo htmlspecialchars(strtolower($type_b));?>"
         data-search="<?php echo htmlspecialchars($search);?>">
  <div class="b-card-bar <?php echo $barClass;?>"></div>
  <div class="b-card-inner">
    <!-- En-tête -->
    <div class="b-card-head">
      <div>
        <div class="b-card-title"><?php echo htmlspecialchars($type_b);?> <span style="color:var(--txt-muted);font-weight:600;font-size:.82rem">#<?php echo (int)$bid;?></span></div>
        <div class="b-card-sub"><i class="bi bi-geo-alt-fill" style="font-size:.72rem;color:var(--amber)"></i><?php echo htmlspecialchars($b['NOM_ZONE']??'Zone ?');?></div>
      </div>
      <span class="badge-soft badge-amber"><?php echo feur($caTotal);?></span>
    </div>

    <!-- Stats CA + Employés -->
    <div class="b-stats">
      <div class="b-stat">
        <div class="b-stat-v"><?php echo count($caList);?></div>
        <div class="b-stat-l">Entrées CA</div>
      </div>
      <div class="b-stat">
        <div class="b-stat-v"><?php echo count($staff);?></div>
        <div class="b-stat-l">Employés</div>
      </div>
    </div>

    <!-- Dernières lignes de CA -->
    <?php if(!empty($caList)):?>
    <div class="b-ca-mini">
      <?php foreach(array_slice($caList,0,3) as $c): ?>
      <div class="b-ca-row">
        <div>
          <div class="b-ca-date"><?php echo fdate_ca($c['DATE_CA']);?></div>
          <div class="b-ca-note">Entrée de chiffre d'affaires</div>
        </div>
        <div class="b-ca-amt"><?php echo feur((float)$c['MONTANT']);?></div>
      </div>
      <?php endforeach;?>
    </div>
    <?php else:?>
    <div style="font-size:.82rem;color:var(--txt-muted);font-style:italic">Aucun CA saisi</div>
    <?php endif;?>

    <!-- Responsable + Staff -->
    <div class="b-staff">
      <?php if($mgr!=='—'):?>
      <span class="b-chip"><i class="bi bi-person-badge-fill" style="color:var(--amber)"></i><?php echo htmlspecialchars($mgr);?></span>
      <?php endif;?>
      <?php foreach(array_slice($staff,0,3) as $s):?>
      <span class="b-chip"><i class="bi bi-person"></i><?php echo htmlspecialchars($s);?></span>
      <?php endforeach;?>
      <?php if(count($staff)>3):?><span class="b-chip">+<?php echo count($staff)-3;?></span><?php endif;?>
    </div>

    <!-- Footer -->
    <div class="b-card-footer">
      <a class="btn btn-primary" style="font-size:.82rem;padding:.52rem .9rem"
         href="<?php echo htmlspecialchars(url_site('/boutiques/detail.php?id='.$bid));?>">
        <i class="bi bi-eye-fill"></i> Voir la fiche
      </a>
    </div>
  </div>
</article>
<?php endforeach; if(empty($boutiques)):?>
<div style="grid-column:1/-1;text-align:center;padding:3rem;color:var(--txt-muted)">
  <i class="bi bi-shop" style="font-size:2.5rem;opacity:.3;display:block;margin-bottom:.75rem"></i>
  <div style="font-weight:700">Aucune boutique enregistrée</div>
</div>
<?php endif;?>
</div>
</div><!-- /section-card -->

<?php if($peut_modifier):?>
<div class="modal fade" id="mAjout" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title fw-bold"><i class="bi bi-plus-lg me-2"></i>Ajouter une boutique</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><form method="POST"><input type="hidden" name="action" value="ajouter">
    <div class="grid-auto">
      <div><label class="form-label">Type</label><select name="type_boutique" class="form-select"><option value="Souvenirs">Souvenirs</option><option value="Snack">Snack</option><option value="Photo">Photo</option><option value="Autres">Autres</option></select></div>
      <div><label class="form-label">Zone</label><select name="id_zone" class="form-select"><option value="">— Choisir —</option><?php foreach($liste_zones as $z):?><option value="<?php echo $z['ID_ZONE'];?>"><?php echo htmlspecialchars($z['NOM_ZONE']);?></option><?php endforeach;?></select></div>
      <div><label class="form-label">Responsable</label><select name="id_personnel" class="form-select"><option value="">— Choisir —</option><?php foreach($liste_pers as $p):?><option value="<?php echo $p['ID_PERSONNEL'];?>"><?php echo htmlspecialchars($p['PRENOM_PERSONNEL'].' '.$p['NOM_PERSONNEL']);?></option><?php endforeach;?></select></div>
    </div>
    <div class="action-row justify-content-end"><button type="button" class="btn btn-light-surface" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Ajouter</button></div>
  </form></div>
</div></div></div>
<?php endif;?>

<script>
(function(){
  var recherche=document.getElementById('rechercheBoutiques');
  var filtre=document.getElementById('filtreTypeBoutique');
  function appliquer(){
    var q=(recherche.value||'').toLowerCase();
    var type=(filtre.value||'').toLowerCase();
    document.querySelectorAll('.boutique-grid [data-filter-item]').forEach(function(card){
      var texte=(card.dataset.search||'').toLowerCase();
      var typeCarte=(card.dataset.type||'').toLowerCase();
      var visible=texte.indexOf(q)!==-1;
      if(visible && type!=='' && typeCarte!==type) visible=false;
      card.style.display=visible?'':'none';
    });
  }
  recherche.addEventListener('input', appliquer);
  filtre.addEventListener('change', appliquer);
})();
</script>
    </div>
  </main>
</div>
<script src="<?php echo htmlspecialchars(url_site('/assets/vendor/bootstrap/js/bootstrap.bundle.min.js')); ?>"></script>
<script>
document.querySelectorAll('.reveal').forEach(function(el,i){setTimeout(function(){el.classList.add('visible');},80+i*35);});
document.querySelectorAll('[data-toggle-extern]').forEach(function(box){
  function sync(){
    var t=document.getElementById(box.dataset.toggleExtern); if(!t) return;
    document.querySelectorAll(box.dataset.target).forEach(function(el){el.style.display=t.checked?'':'none';});
    document.querySelectorAll(box.dataset.altTarget).forEach(function(el){el.style.display=t.checked?'none':'';});
  }
  var target=document.getElementById(box.dataset.toggleExtern); if(target){target.addEventListener('change',sync); sync();}
});
</script>
</body>
</html>

