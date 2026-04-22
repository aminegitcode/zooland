<?php
require_once '../includes/auth.php';
require_role(['admin','dirigeant','boutique','vendeur','comptable']);
require_once '../includes/path.php';
require_once '../includes/helpers.php';
require_once '../config.php';

$role_actuel = get_role();
$peut_ca  = in_array($role_actuel,['admin','dirigeant','comptable']);
$peut_mod = in_array($role_actuel,['admin','dirigeant']);
$msg = $msg_type = '';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location:'.url_site('/boutiques/index.php')); exit; }

/* ── Ajouter CA ── */
if ($peut_ca && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='add_ca') {
    $date = trim($_POST['date_ca']); $montant = (float)$_POST['montant'];
    if ($montant > 0 && $date) {
        $st=oci_parse($conn,"SELECT NVL(MAX(id_ca),0)+1 FROM Chiffre_affaires"); oci_execute($st); $r=oci_fetch_array($st,OCI_NUM); $nxt=(int)$r[0]; oci_free_statement($st);
        $st=oci_parse($conn,"INSERT INTO Chiffre_affaires(id_ca,id_boutique,date_ca,montant) VALUES(:id,:bid,TO_DATE(:d,'YYYY-MM-DD'),:m)");
        oci_bind_by_name($st,':id',$nxt); oci_bind_by_name($st,':bid',$id); oci_bind_by_name($st,':d',$date); oci_bind_by_name($st,':m',$montant);
        $ok=oci_execute($st); $msg=$ok?'CA ajouté !':'Erreur.'; $msg_type=$ok?'success':'danger'; oci_free_statement($st);
    } else { $msg='Montant et date requis.'; $msg_type='danger'; }
}

/* ── Ajouter employé (Travailler) ── */
if ($peut_mod && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='add_emp') {
    $id_p = (int)$_POST['id_personnel_emp'];
    if ($id_p) {
        $st=oci_parse($conn,"INSERT INTO Travailler(id_personnel,id_boutique) VALUES(:p,:b)");
        oci_bind_by_name($st,':p',$id_p); oci_bind_by_name($st,':b',$id);
        $ok=oci_execute($st); $msg=$ok?'Employé ajouté !':'Erreur (déjà assigné ?).'; $msg_type=$ok?'success':'danger'; oci_free_statement($st);
    }
}

/* ── Retirer employé ── */
if ($peut_mod && isset($_GET['retirer_emp'])) {
    $id_p=(int)$_GET['retirer_emp'];
    $st=oci_parse($conn,"DELETE FROM Travailler WHERE id_personnel=:p AND id_boutique=:b");
    oci_bind_by_name($st,':p',$id_p); oci_bind_by_name($st,':b',$id);
    $ok=oci_execute($st); $msg=$ok?'Employé retiré.':'Erreur.'; $msg_type=$ok?'success':'danger'; oci_free_statement($st);
}

/* ── Charger boutique ── */
$boutique=null;
$st=oci_parse($conn,"SELECT b.id_boutique,b.type_boutique,b.id_personnel,b.id_zone,z.nom_zone,p.prenom_personnel,p.nom_personnel FROM Boutique b LEFT JOIN Zone z ON b.id_zone=z.id_zone LEFT JOIN Personnel p ON b.id_personnel=p.id_personnel WHERE b.id_boutique=:id");
oci_bind_by_name($st,':id',$id);
if($st&&oci_execute($st)){$boutique=oci_fetch_assoc($st);oci_free_statement($st);}
if(!$boutique){header('Location:'.url_site('/boutiques/index.php'));exit;}

/* ── CA ── */
$ca_list=[];
$st=oci_parse($conn,"SELECT id_ca,date_ca,montant FROM Chiffre_affaires WHERE id_boutique=:id ORDER BY date_ca DESC");
oci_bind_by_name($st,':id',$id);
if($st&&oci_execute($st)){while($l=oci_fetch_assoc($st))$ca_list[]=$l;oci_free_statement($st);}
$ca_total=array_sum(array_map(fn($c)=>(float)$c['MONTANT'],$ca_list));
$ca_max=0; foreach($ca_list as $c){if((float)$c['MONTANT']>$ca_max)$ca_max=(float)$c['MONTANT'];}
$ca_moy=count($ca_list)?$ca_total/count($ca_list):0;

/* ── Employés ── */
$employes=[];
$st=oci_parse($conn,"SELECT p.id_personnel,p.nom_personnel,p.prenom_personnel,(SELECT r.nom_role FROM Historique_emploi h JOIN Role r ON h.id_role=r.id_role WHERE h.id_personnel=p.id_personnel AND h.date_fin IS NULL AND ROWNUM=1) role_actuel FROM Travailler t JOIN Personnel p ON t.id_personnel=p.id_personnel WHERE t.id_boutique=:id ORDER BY p.nom_personnel");
oci_bind_by_name($st,':id',$id);
if($st&&oci_execute($st)){while($l=oci_fetch_assoc($st))$employes[]=$l;oci_free_statement($st);}

/* ── Personnel disponible à ajouter ── */
$ids_emp=array_map(fn($e)=>(int)$e['ID_PERSONNEL'],$employes);
$all_pers=[];
$st=oci_parse($conn,"SELECT DISTINCT p.id_personnel,p.nom_personnel,p.prenom_personnel FROM Personnel p,Historique_emploi h,Role r WHERE p.id_personnel=h.id_personnel AND h.id_role=r.id_role AND h.date_fin IS NULL AND LOWER(r.nom_role)='vendeur' ORDER BY p.nom_personnel");
if($st&&oci_execute($st)){while($l=oci_fetch_assoc($st))$all_pers[]=$l;oci_free_statement($st);}
$pers_dispo=array_values(array_filter($all_pers,fn($p)=>!in_array((int)$p['ID_PERSONNEL'],$ids_emp)));

oci_close($conn);

function fd($d){if(empty($d))return'—';$t=strtotime((string)$d);return $t?date('d/m/Y',$t):'—';}
function feur(float $v){return number_format($v,2,',',' ').' €';}

$colors=['#16a34a','#0284c7','#d97706','#e11d48','#7c3aed','#0d9488'];

$page_title = 'Boutique #'.$id;
$page_css = '/assets/css/boutiques.css';
$page_hero = [
    'kicker'=>'Fiche boutique','icon'=>'bi bi-shop',
    'title'=>htmlspecialchars(($boutique['TYPE_BOUTIQUE']??'—').' — Zone '.($boutique['NOM_ZONE']??'—')),
    'desc'=>'Détail complet : informations, chiffres d\'affaires et équipe.',
    'image'=>url_site('/assets/img/shops-hero.svg'),
    'actions'=>array_filter([
        $peut_ca?['label'=>'Saisir un CA','icon'=>'bi bi-graph-up-arrow','target'=>'#mCA','class'=>'btn-primary']:null,
        $peut_mod?['label'=>'Ajouter un employé','icon'=>'bi bi-person-plus-fill','target'=>'#mEmp','class'=>'btn-light-surface']:null,
        ['label'=>'Retour boutiques','icon'=>'bi bi-arrow-left','href'=>url_site('/boutiques/index.php'),'class'=>'btn-ghost'],
    ]),
    'stats'=>[
        ['value'=>feur($ca_total),'label'=>'CA Total'],
        ['value'=>count($ca_list),'label'=>'Entrées CA'],
        ['value'=>count($employes),'label'=>'Employés'],
        ['value'=>feur($ca_moy),'label'=>'CA Moyen'],
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

<div class="row g-4">

  <!-- Colonne gauche -->
  <div class="col-lg-4">

    <!-- Infos boutique -->
    <div class="section-card reveal mb-4">
      <div class="overline mb-3"><i class="bi bi-info-circle-fill me-1" style="color:var(--amber)"></i>Informations</div>
      <div class="mini-row" style="padding:.5rem 0;border-bottom:1px solid var(--border)"><span class="text-muted">Type</span><strong><?php echo htmlspecialchars($boutique['TYPE_BOUTIQUE']??'—');?></strong></div>
      <div class="mini-row" style="padding:.5rem 0;border-bottom:1px solid var(--border)"><span class="text-muted">Zone</span><strong><?php echo htmlspecialchars($boutique['NOM_ZONE']??'—');?></strong></div>
      <?php if(!empty($boutique['NOM_PERSONNEL'])):?>
      <div class="mini-row" style="padding:.5rem 0;border-bottom:1px solid var(--border)"><span class="text-muted">Responsable</span><strong><?php echo htmlspecialchars($boutique['PRENOM_PERSONNEL'].' '.$boutique['NOM_PERSONNEL']);?></strong></div>
      <?php endif;?>
      <div class="mini-row" style="padding:.5rem 0;border-bottom:1px solid var(--border)"><span class="text-muted">CA Total</span><strong style="color:var(--amber)"><?php echo feur($ca_total);?></strong></div>
      <div class="mini-row" style="padding:.5rem 0"><span class="text-muted">CA Moyen</span><strong><?php echo feur($ca_moy);?></strong></div>
    </div>

    <!-- Employés -->
    <div class="section-card reveal">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;gap:.75rem;flex-wrap:wrap">
        <div>
          <div class="overline"><i class="bi bi-people-fill me-1" style="color:var(--green)"></i>Équipe</div>
        </div>
        <div style="display:flex;align-items:center;gap:.5rem">
          <span class="badge-soft badge-emerald"><?php echo count($employes);?> employé<?php echo count($employes)>1?'s':'';?></span>
          <?php if($peut_mod&&!empty($pers_dispo)):?>
          <button class="btn btn-light-surface" style="font-size:.8rem;padding:.4rem .8rem;color:var(--txt)" data-bs-toggle="modal" data-bs-target="#mEmp">
            <i class="bi bi-person-plus-fill"></i>
          </button>
          <?php endif;?>
        </div>
      </div>

      <?php if(empty($employes)):?>
      <div style="text-align:center;padding:1.5rem;color:var(--txt-muted)">
        <i class="bi bi-people" style="font-size:1.8rem;opacity:.3;display:block;margin-bottom:.5rem"></i>
        Aucun employé assigné<br>
        <?php if($peut_mod):?>
        <a href="<?php echo htmlspecialchars(url_site('/personnel/index.php'));?>" class="btn btn-primary mt-3" style="font-size:.82rem">
          <i class="bi bi-person-plus-fill"></i> Gérer le personnel
        </a>
        <?php endif;?>
      </div>
      <?php else: foreach($employes as $i=>$e):
        $col=$colors[$i%count($colors)];
        $ini=strtoupper(mb_substr($e['PRENOM_PERSONNEL'],0,1).mb_substr($e['NOM_PERSONNEL'],0,1));
      ?>
      <div class="emp-row">
        <div class="emp-av" style="background:<?php echo $col;?>"><?php echo htmlspecialchars($ini);?></div>
        <div style="flex:1;min-width:0">
          <div style="font-weight:800;font-size:.875rem"><?php echo htmlspecialchars($e['PRENOM_PERSONNEL'].' '.$e['NOM_PERSONNEL']);?></div>
          <?php if(!empty($e['ROLE_ACTUEL'])):?><div style="font-size:.72rem;color:var(--txt-muted)"><?php echo htmlspecialchars($e['ROLE_ACTUEL']);?></div><?php endif;?>
        </div>
        <div style="display:flex;gap:.38rem;align-items:center">
          <a href="<?php echo htmlspecialchars(url_site('/personnel/detail.php?id='.$e['ID_PERSONNEL']));?>"
             style="color:var(--sky);font-size:.82rem;padding:.25rem .5rem;border-radius:8px;background:rgba(47,124,213,.1)" title="Voir fiche">
            <i class="bi bi-box-arrow-up-right"></i>
          </a>
          <?php if($peut_mod):?>
          <a href="?retirer_emp=<?php echo $e['ID_PERSONNEL'];?>&id=<?php echo $id;?>"
             onclick="return confirm('Retirer cet employé de la boutique ?')"
             style="color:var(--rose);font-size:.82rem;padding:.25rem .5rem;border-radius:8px;background:rgba(217,79,112,.1)" title="Retirer">
            <i class="bi bi-x-lg"></i>
          </a>
          <?php endif;?>
        </div>
      </div>
      <?php endforeach; endif;?>

      <?php if($peut_mod):?>
      <div style="margin-top:1rem;padding-top:.85rem;border-top:1px solid var(--border)">
        <a href="<?php echo htmlspecialchars(url_site('/personnel/index.php'));?>"
           class="btn btn-light-surface" style="font-size:.82rem;padding:.52rem .9rem;color:var(--txt);width:100%;justify-content:center">
          <i class="bi bi-people-fill me-1"></i> Gérer tout le personnel →
        </a>
      </div>
      <?php endif;?>
    </div>
  </div>

  <!-- Colonne droite : CA -->
  <div class="col-lg-8">
    <div class="section-card reveal">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.15rem;gap:.75rem;flex-wrap:wrap">
        <div>
          <div class="overline"><i class="bi bi-graph-up-arrow me-1" style="color:var(--green)"></i>Chiffres d'affaires</div>
          <h2 class="page-title-sm">Historique CA</h2>
        </div>
        <span class="badge-soft badge-emerald"><?php echo feur($ca_total);?> total</span>
      </div>

      <?php if(!$peut_ca):?>
      <div class="alert-danger mb-3" style="font-size:.84rem;display:flex;align-items:center;gap:.65rem">
        <i class="bi bi-lock-fill" style="font-size:1.2rem"></i>
        <div>Votre rôle (<strong><?php echo htmlspecialchars(get_role_affiche());?></strong>) ne permet pas de saisir des chiffres d'affaires.</div>
      </div>
      <?php endif;?>

      <?php if(empty($ca_list)):?>
      <div style="text-align:center;padding:3rem;color:var(--txt-muted)">
        <i class="bi bi-graph-up" style="font-size:2.5rem;opacity:.3;display:block;margin-bottom:.75rem"></i>
        Aucun chiffre d'affaires enregistré
      </div>
      <?php else:?>
      <div>
        <?php foreach($ca_list as $ca):?>
        <div class="ca-row">
          <div>
            <div class="ca-date"><?php echo fd($ca['DATE_CA']);?></div>
            <div class="ca-note">Chiffre d'affaires enregistré</div>
          </div>
          <span class="badge-soft badge-amber ca-badge"><i class="bi bi-cash-stack"></i> <?php echo feur((float)$ca['MONTANT']);?></span>
        </div>
        <?php endforeach;?>
      </div>
      <!-- Résumé -->
      <?php $mts=array_map(fn($c)=>(float)$c['MONTANT'],$ca_list); $maxv=max($mts); $minv=min($mts);?>
      <div class="row g-3 mt-3 pt-3" style="border-top:1px solid var(--border)">
        <div class="col-4 text-center"><div style="font-weight:900;font-size:1rem;color:var(--amber)"><?php echo feur($ca_total);?></div><div class="overline">Total</div></div>
        <div class="col-4 text-center"><div style="font-weight:900;font-size:1rem;color:var(--teal)"><?php echo feur($ca_moy);?></div><div class="overline">Moyenne</div></div>
        <div class="col-4 text-center"><div style="font-weight:900;font-size:1rem;color:var(--green)"><?php echo feur($maxv);?></div><div class="overline">Record</div></div>
      </div>
      <?php endif;?>
    </div>
  </div>

</div>

<!-- Modal CA -->
<?php if($peut_ca):?>
<div class="modal fade" id="mCA" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title fw-bold"><i class="bi bi-graph-up-arrow me-2"></i>Saisir un CA</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><form method="POST"><input type="hidden" name="action" value="add_ca">
    <div class="mb-3"><label class="form-label">Date *</label><input type="date" name="date_ca" class="form-control" value="<?php echo date('Y-m-d');?>" required></div>
    <div class="mb-3"><label class="form-label">Montant (€) *</label><input type="number" step="0.01" min="0.01" name="montant" class="form-control" placeholder="0.00" required></div>
    <div class="action-row justify-content-end"><button type="button" class="btn btn-light-surface" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Enregistrer</button></div>
  </form></div>
</div></div></div>
<?php endif;?>

<!-- Modal ajouter employé -->
<?php if($peut_mod&&!empty($pers_dispo)):?>
<div class="modal fade" id="mEmp" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i>Ajouter un employé</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <p style="font-size:.84rem;color:var(--txt-muted);margin-bottom:1rem">Choisissez un membre du personnel à affecter à cette boutique. Pour créer un nouveau vendeur, <a href="<?php echo htmlspecialchars(url_site('/personnel/index.php'));?>" style="color:var(--sky);font-weight:700">gérez le personnel →</a></p>
    <form method="POST"><input type="hidden" name="action" value="add_emp">
      <div class="mb-3"><label class="form-label">Personnel *</label>
        <select name="id_personnel_emp" class="form-select" required>
          <option value="">— Choisir —</option>
          <?php foreach($pers_dispo as $p):?>
          <option value="<?php echo $p['ID_PERSONNEL'];?>"><?php echo htmlspecialchars($p['PRENOM_PERSONNEL'].' '.$p['NOM_PERSONNEL']);?></option>
          <?php endforeach;?>
        </select>
      </div>
      <div class="action-row justify-content-end"><button type="button" class="btn btn-light-surface" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Ajouter</button></div>
    </form>
  </div>
</div></div></div>
<?php endif;?>
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

