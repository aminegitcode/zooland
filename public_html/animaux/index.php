<?php
require_once '../includes/auth.php'; verifier_connexion();
require_once '../includes/path.php';
require_once '../includes/helpers.php';
require_once '../config.php';

$role       = get_role();
$peut_mod   = in_array($role,['admin','dirigeant']);
$msg = ''; $msg_type = 'success';

/* Suppression */
if ($peut_mod && isset($_GET['supprimer'])) {
    $rfid = trim($_GET['supprimer']);
    $st = oci_parse($conn,"DELETE FROM Animal WHERE rfid=:rfid");
    oci_bind_by_name($st,':rfid',$rfid);
    $ok = oci_execute($st); oci_free_statement($st);
    $msg = $ok?'Animal supprimé.':'Erreur de suppression.'; $msg_type=$ok?'success':'danger';
}

/* Ajout */
if ($peut_mod && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='ajouter') {
    $rfid=$_POST['rfid']; $nom=trim($_POST['nom_animal']); $dn=trim($_POST['date_naissance']);
    $pds=trim($_POST['poids']); $reg=trim($_POST['regime_alimentaire']); $zoo='Babentruk';
    $ie=(int)$_POST['id_espece']; $ien=(int)$_POST['id_enclos']; $ip=(int)$_POST['id_personnel'];
    $st=oci_parse($conn,"INSERT INTO Animal(rfid,nom_animal,date_naissance,poids,regime_alimentaire,zoo,id_espece,id_enclos,id_personnel) VALUES(:rfid,:nom,TO_DATE(:dn,'YYYY-MM-DD'),:pds,:reg,:zoo,:ie,:ien,:ip)");
    oci_bind_by_name($st,':rfid',$rfid); oci_bind_by_name($st,':nom',$nom); oci_bind_by_name($st,':dn',$dn);
    oci_bind_by_name($st,':pds',$pds); oci_bind_by_name($st,':reg',$reg); oci_bind_by_name($st,':zoo',$zoo);
    oci_bind_by_name($st,':ie',$ie); oci_bind_by_name($st,':ien',$ien); oci_bind_by_name($st,':ip',$ip);
    $ok=oci_execute($st); oci_free_statement($st);
    $msg=$ok?'Animal ajouté !':'Erreur lors de l\'ajout.'; $msg_type=$ok?'success':'danger';
}

/* Données */
$animaux=[];
$st=oci_parse($conn,"SELECT a.rfid,a.nom_animal,a.poids,a.date_naissance,a.regime_alimentaire,a.zoo,e.nom_usuel,e.est_menacee,z.nom_zone,en.id_enclos,p.prenom_personnel,p.nom_personnel,a.id_espece FROM Animal a,Espece e,Enclos en,Zone z,Personnel p WHERE a.id_espece=e.id_espece AND a.id_enclos=en.id_enclos AND en.id_zone=z.id_zone AND a.id_personnel=p.id_personnel ORDER BY a.rfid");
if($st&&oci_execute($st)){while($l=oci_fetch_assoc($st))$animaux[]=$l;oci_free_statement($st);}

$liste_especes=[];
$st=oci_parse($conn,"SELECT id_espece,nom_usuel FROM Espece ORDER BY nom_usuel");
if($st&&oci_execute($st)){while($l=oci_fetch_assoc($st))$liste_especes[]=$l;oci_free_statement($st);}

$liste_enclos=[];
$st=oci_parse($conn,"SELECT en.id_enclos,z.nom_zone FROM Enclos en,Zone z WHERE en.id_zone=z.id_zone ORDER BY en.id_enclos");
if($st&&oci_execute($st)){while($l=oci_fetch_assoc($st))$liste_enclos[]=$l;oci_free_statement($st);}

$liste_pers=[];
$st=oci_parse($conn,"SELECT DISTINCT p.id_personnel,p.prenom_personnel,p.nom_personnel,s.id_espece FROM Personnel p,Historique_emploi h,Role r,Specialiser s WHERE p.id_personnel=h.id_personnel AND h.id_role=r.id_role AND s.id_personnel=p.id_personnel AND h.date_fin IS NULL AND LOWER(r.nom_role) IN ('soigneur','soigneur chef') ORDER BY p.nom_personnel");
if($st&&oci_execute($st)){while($l=oci_fetch_assoc($st))$liste_pers[]=$l;oci_free_statement($st);}

/* Prochain RFID proposé automatiquement */
$prochain_rfid = 'RFID001';
$max_num_rfid = 0;
$st=oci_parse($conn,"SELECT rfid FROM Animal");
if($st&&oci_execute($st)){
    while($l=oci_fetch_assoc($st)){
        $rfid_actuel = (string)($l['RFID'] ?? '');
        if (preg_match('/^(.*?)(\d+)$/', $rfid_actuel, $m)) {
            $num = (int)$m[2];
            if ($num > $max_num_rfid) {
                $max_num_rfid = $num;
                $prochain_rfid = $m[1] . str_pad((string)($num + 1), strlen($m[2]), '0', STR_PAD_LEFT);
            }
        }
    }
    oci_free_statement($st);
}

oci_close($conn);

$nb_total   = count($animaux);
$nb_menaces = count(array_filter($animaux,fn($a)=>(int)($a['EST_MENACEE']??0)===1));
$nb_zones   = count(array_unique(array_column($animaux,'NOM_ZONE')));

function fmt_date($d){if(empty($d))return'—';$t=strtotime((string)$d);return $t?date('d/m/Y',$t):'—';}

$page_title = 'Animaux';
$page_css = '/assets/css/animaux.css';
$page_hero = [
    'kicker'=>'Registre des animaux','icon'=>'bi bi-heart-pulse-fill',
    'title'=>'Animaux du parc',
    'desc'=>'Suivi complet des animaux, espèces, zones et soigneurs référents.',
    'image'=>url_site('/assets/img/animals-hero.svg'),
    'actions'=>array_filter([
        $peut_mod?['label'=>'Ajouter un animal','icon'=>'bi bi-plus-lg','target'=>'#modalAjouter','class'=>'btn-primary']:null,
        ['label'=>'Dashboard','icon'=>'bi bi-arrow-left','href'=>url_site('/index.php'),'class'=>'btn-ghost'],
    ]),
    'stats'=>[
        ['value'=>$nb_total,'label'=>'animaux'],
        ['value'=>$nb_zones,'label'=>'zones'],
        ['value'=>$nb_menaces,'label'=>'espèces menacées'],
        ['value'=>count($liste_especes),'label'=>'espèces distinctes'],
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
render_alert($msg,$msg_type);
?>

<!-- Filtres -->
<div class="section-card reveal filter-panel mb-0">
  <div class="filter-grid">
    <div class="filter-search">
      <i class="bi bi-search"></i>
      <input type="search" id="fRecherche" class="form-control" placeholder="Nom, espèce, soigneur, zone…">
    </div>
    <select id="fEspece" class="form-select">
      <option value="">Toutes les espèces</option>
      <?php foreach($liste_especes as $e):?>
      <option value="<?php echo htmlspecialchars($e['NOM_USUEL']);?>"><?php echo htmlspecialchars($e['NOM_USUEL']);?></option>
      <?php endforeach;?>
    </select>
    <select id="fMenace" class="form-select">
      <option value="">Tous les statuts</option>
      <option value="1">⚠ Espèces menacées</option>
      <option value="0">✓ Non menacées</option>
    </select>
  </div>
</div>

<!-- Table animaux — pleine largeur -->
<div class="section-card reveal" style="margin-top:.75rem;padding:0;overflow:hidden">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.92rem 1.15rem;border-bottom:1px solid var(--border);flex-wrap:wrap">
    <div style="display:flex;align-items:center;gap:.75rem">
      <span style="font-size:.95rem;font-weight:900;color:var(--txt)">Registre des animaux</span>
      <span class="badge-soft badge-emerald" id="compteur"><?php echo $nb_total;?> animal<?php echo $nb_total>1?'s':'';?></span>
    </div>
    <?php if($nb_menaces>0):?>
    <span class="badge-soft badge-rose">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <?php echo $nb_menaces;?> menacé<?php echo $nb_menaces>1?'s':'';?>
    </span>
    <?php endif;?>
  </div>
  <div class="table-responsive">
    <table class="table table-lite" id="tableAnimaux" style="margin:0;border-radius:0">
      <thead>
        <tr>
          <th>RFID</th>
          <th>Nom</th>
          <th>Espèce</th>
          <th>Zone</th>
          <th>Soigneur</th>
          <th>Poids</th>
          <th>Régime</th>
          <th></th>
          <?php if($peut_mod):?><th style="width:80px">Actions</th><?php endif;?>
        </tr>
      </thead>
      <tbody>
        <?php foreach($animaux as $a):
          $menace = (int)($a['EST_MENACEE']??0)===1;
          $agent  = trim(($a['PRENOM_PERSONNEL']??'').' '.($a['NOM_PERSONNEL']??''));
          $regime = strtolower($a['REGIME_ALIMENTAIRE']??'');
        ?>
        <tr class="ligne-animal"
            style="cursor:pointer;<?php echo $menace?'background:rgba(217,79,112,.04)':'';?>"
            data-nom="<?php echo strtolower($a['NOM_ANIMAL']??'');?>"
            data-espece="<?php echo htmlspecialchars($a['NOM_USUEL']??'');?>"
            data-soigneur="<?php echo strtolower($agent);?>"
            data-zone="<?php echo strtolower($a['NOM_ZONE']??'');?>"
            data-menace="<?php echo (int)($a['EST_MENACEE']??0);?>"
            onclick="window.location.href='<?php echo htmlspecialchars(url_site('/animaux/detail.php?rfid='.urlencode($a['RFID'])));?>'">
          <td>
            <code style="font-size:.75rem;background:rgba(47,133,90,.1);color:var(--green);padding:.2rem .5rem;border-radius:8px"><?php echo htmlspecialchars($a['RFID']);?></code>
          </td>
          <td>
            <div style="display:flex;align-items:center;gap:.6rem">
              <div style="width:28px;height:28px;border-radius:8px;background:rgba(47,133,90,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-heart-pulse-fill" style="font-size:.72rem;color:var(--green)"></i>
              </div>
              <span style="font-weight:700"><?php echo htmlspecialchars($a['NOM_ANIMAL']??'—');?></span>
            </div>
          </td>
          <td><span style="font-size:.84rem"><?php echo htmlspecialchars($a['NOM_USUEL']??'—');?></span></td>
          <td><span class="badge-soft badge-sky" style="font-size:.72rem"><?php echo htmlspecialchars($a['NOM_ZONE']??'—');?></span></td>
          <td style="font-size:.84rem"><?php echo htmlspecialchars($agent?:'—');?></td>
          <td style="font-size:.84rem">
            <?php if(!empty($a['POIDS'])):?>
            <strong><?php echo number_format((float)$a['POIDS'],1,',',' ');?> kg</strong>
            <?php else:?>—<?php endif;?>
          </td>
          <td>
            <?php if(str_contains($regime,'carnivore')):?>
            <span class="badge-soft badge-sky" style="font-size:.70rem"><i class="bi bi-lightning-fill"></i> Carnivore</span>
            <?php elseif(str_contains($regime,'herbivore')):?>
            <span class="badge-soft badge-sky" style="font-size:.70rem"><i class="bi bi-flower1"></i> Herbivore</span>
            <?php elseif(str_contains($regime,'omnivore')):?>
            <span class="badge-soft badge-sky" style="font-size:.70rem"><i class="bi bi-circle-half"></i> Omnivore</span>
            <?php else:?><span class="badge-soft badge-sky" style="font-size:.70rem"><?php echo htmlspecialchars($a['REGIME_ALIMENTAIRE']??'—');?></span><?php endif;?>
          </td>
          <td>
            <?php if($menace):?>
            <span class="badge-soft badge-rose" style="font-size:.70rem;font-weight:900"><i class="bi bi-exclamation-triangle-fill"></i> Menacé</span>
            <?php endif;?>
          </td>
          <?php if($peut_mod):?>
          <td onclick="event.stopPropagation()">
            <div style="display:flex;gap:.38rem">
              <a href="<?php echo htmlspecialchars(url_site('/animaux/detail.php?rfid='.urlencode($a['RFID'])));?>"
                 style="width:28px;height:28px;border-radius:7px;background:rgba(47,124,213,.12);color:var(--sky);display:flex;align-items:center;justify-content:center;font-size:.78rem;text-decoration:none">
                <i class="bi bi-eye-fill"></i>
              </a>
              <a href="?supprimer=<?php echo urlencode($a['RFID']);?>"
                 onclick="return confirm('Supprimer <?php echo htmlspecialchars($a['NOM_ANIMAL']??'');?> ?')"
                 style="width:28px;height:28px;border-radius:7px;background:rgba(217,79,112,.12);color:var(--rose);display:flex;align-items:center;justify-content:center;font-size:.78rem;text-decoration:none">
                <i class="bi bi-trash-fill"></i>
              </a>
            </div>
          </td>
          <?php endif;?>
        </tr>
        <?php endforeach;?>
        <?php if(empty($animaux)):?>
        <tr><td colspan="<?php echo $peut_mod?9:8;?>" style="text-align:center;padding:3rem;color:var(--txt-muted)">
          <i class="bi bi-heart-pulse" style="font-size:2rem;opacity:.3;display:block;margin-bottom:.6rem"></i>
          Aucun animal enregistré
        </td></tr>
        <?php endif;?>
      </tbody>
    </table>
  </div>
</div>

<?php if($peut_mod):?>
<!-- Modal ajouter -->
<div class="modal fade" id="modalAjouter" tabindex="-1">
  <div class="modal-dialog modal-xl"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title fw-bold"><i class="bi bi-plus-lg me-2"></i>Ajouter un animal</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <form method="POST"><input type="hidden" name="action" value="ajouter">
        <div class="grid-auto mb-3">
          <div><label class="form-label">RFID *</label><input class="form-control" name="rfid" required value="<?php echo htmlspecialchars($prochain_rfid);?>" placeholder="RFID001"><div class="form-text">Prérempli avec le prochain RFID disponible.</div></div>
          <div><label class="form-label">Nom *</label><input class="form-control" name="nom_animal" required></div>
          <div><label class="form-label">Poids (kg)</label><input class="form-control" name="poids" type="number" step="0.01"></div>
          <div><label class="form-label">Date de naissance</label><input class="form-control" type="date" name="date_naissance"></div>
          <div><label class="form-label">Régime alimentaire</label><select class="form-select" name="regime_alimentaire"><option>Carnivore</option><option>Herbivore</option><option>Omnivore</option></select></div>
          <div><label class="form-label">Zoo d'origine</label><input class="form-control" name="zoo" value="Babentruk" readonly></div>
        </div>
        <div class="grid-auto">
          <div><label class="form-label">Espèce *</label>
            <select id="selEsp" class="form-select" name="id_espece" required>
              <option value="">— Choisir —</option>
              <?php foreach($liste_especes as $e):?><option value="<?php echo $e['ID_ESPECE'];?>"><?php echo htmlspecialchars($e['NOM_USUEL']);?></option><?php endforeach;?>
            </select>
          </div>
          <div><label class="form-label">Enclos *</label>
            <select class="form-select" name="id_enclos" required>
              <option value="">— Choisir —</option>
              <?php foreach($liste_enclos as $e):?><option value="<?php echo $e['ID_ENCLOS'];?>">Enclos <?php echo $e['ID_ENCLOS'];?> — <?php echo htmlspecialchars($e['NOM_ZONE']);?></option><?php endforeach;?>
            </select>
          </div>
          <div><label class="form-label">Soigneur *</label>
            <select id="selSoig" class="form-select" name="id_personnel" required>
              <option value="">— Choisir d'abord une espèce —</option>
              <?php foreach($liste_pers as $p):?>
              <option value="<?php echo $p['ID_PERSONNEL'];?>" data-espece="<?php echo $p['ID_ESPECE'];?>"><?php echo htmlspecialchars($p['PRENOM_PERSONNEL'].' '.$p['NOM_PERSONNEL']);?></option>
              <?php endforeach;?>
            </select>
          </div>
        </div>
        <div class="action-row justify-content-end mt-3">
          <button type="button" class="btn btn-light-surface" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Ajouter</button>
        </div>
      </form>
    </div>
  </div></div>
</div>
<?php endif;?>

<script>
/* Filtrage tableau */
function filtrer(){
  var r=(document.getElementById('fRecherche').value||'').toLowerCase();
  var e=(document.getElementById('fEspece').value||'').toLowerCase();
  var m=document.getElementById('fMenace').value||'';
  var n=0;
  document.querySelectorAll('.ligne-animal').forEach(function(tr){
    var ok=(tr.dataset.nom.includes(r)||tr.dataset.espece.toLowerCase().includes(r)||tr.dataset.soigneur.includes(r)||tr.dataset.zone.includes(r))
         &&(e===''||tr.dataset.espece.toLowerCase()===e)
         &&(m===''||tr.dataset.menace===m);
    tr.style.display=ok?'':'none';
    if(ok)n++;
  });
  var el=document.getElementById('compteur');
  if(el)el.textContent=n+' animal'+(n>1?'s':'');
}
document.getElementById('fRecherche').addEventListener('input',filtrer);
document.getElementById('fEspece').addEventListener('change',filtrer);
document.getElementById('fMenace').addEventListener('change',filtrer);

/* Filtre soigneur par espèce dans la modal */
document.getElementById('selEsp')?.addEventListener('change',function(){
  var id=this.value;
  var sel=document.getElementById('selSoig');
  sel.querySelectorAll('option').forEach(function(o){
    o.style.display=(o.value===''||o.dataset.espece===id)?'':'none';
  });
  sel.value='';
});
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

