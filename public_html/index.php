<?php
require_once 'includes/auth.php'; verifier_connexion();
require_once 'includes/path.php';
require_once 'config.php';

$prenom     = $_SESSION['prenom'] ?? '';
$nom        = $_SESSION['nom']    ?? '';
$role_label = get_role_affiche();
$role       = get_role();

function lire_nb($conn,$sql){$st=oci_parse($conn,$sql);if(!$st||!oci_execute($st))return 0;$l=oci_fetch_array($st,OCI_NUM+OCI_RETURN_NULLS);oci_free_statement($st);return(int)($l[0]??0);}
function lire_tab($conn,$sql){$st=oci_parse($conn,$sql);if(!$st||!oci_execute($st))return[];$r=[];while($l=oci_fetch_assoc($st))$r[]=$l;oci_free_statement($st);return $r;}
function fmt_date($d){if(empty($d))return'';$t=strtotime((string)$d);return $t?date('d/m/Y',$t):'';}

$nb_animaux     = lire_nb($conn,"SELECT COUNT(*) FROM Animal");
$nb_especes     = lire_nb($conn,"SELECT COUNT(DISTINCT id_espece) FROM Animal");
$nb_enclos      = lire_nb($conn,"SELECT COUNT(*) FROM Enclos");
$nb_zones       = lire_nb($conn,"SELECT COUNT(*) FROM Zone");
$nb_personnel   = lire_nb($conn,"SELECT COUNT(*) FROM Personnel");
$nb_parrainages = lire_nb($conn,"SELECT COUNT(*) FROM Parrainage");
$nb_soigneurs   = lire_nb($conn,"SELECT COUNT(*) FROM Historique_emploi h,Role r WHERE h.id_role=r.id_role AND h.date_fin IS NULL AND LOWER(r.nom_role) IN ('soigneur','soigneur chef','veterinaire')");
$nb_menaces     = lire_nb($conn,"SELECT COUNT(*) FROM Animal a,Espece e WHERE a.id_espece=e.id_espece AND e.est_menacee=1");
$nb_boutiques   = lire_nb($conn,"SELECT COUNT(*) FROM Boutique");
$nb_reparations = lire_nb($conn,"SELECT COUNT(*) FROM Reparation");
$ca_row         = lire_tab($conn,"SELECT NVL(SUM(montant),0) tot FROM Chiffre_affaires");
$ca_total       = (float)($ca_row[0]['TOT']??0);
$animaux_recents= array_slice(lire_tab($conn,"SELECT a.rfid,a.nom_animal,e.nom_usuel,e.est_menacee FROM Animal a,Espece e WHERE a.id_espece=e.id_espece ORDER BY a.rfid"),0,5);

if(in_array($role,['soigneur','soigneur_chef','veterinaire','admin','dirigeant']))
    $activites=array_slice(lire_tab($conn,"SELECT hs.date_soin d,a.nom_animal l1,s.nom_soin l2,'soin' t FROM Historique_soins hs,Animal a,Soin s WHERE hs.rfid=a.rfid AND hs.id_soin=s.id_soin ORDER BY hs.date_soin DESC"),0,7);
elseif(in_array($role,['technicien','entretien']))
    $activites=array_slice(lire_tab($conn,"SELECT r.date_reparation d,r.nature l1,'Réparation' l2,'rep' t FROM Reparation r ORDER BY r.date_reparation DESC"),0,7);
elseif(in_array($role,['boutique','vendeur','comptable']))
    $activites=array_slice(lire_tab($conn,"SELECT ca.date_ca d,b.type_boutique l1,ca.montant||' EUR' l2,'ca' t FROM Chiffre_affaires ca,Boutique b WHERE ca.id_boutique=b.id_boutique ORDER BY ca.date_ca DESC"),0,7);
else $activites=[];

oci_close($conn);

$tous_modules=[
  ['label'=>'Animaux',     'url'=>'/animaux/index.php',    'icon'=>'bi-heart-pulse-fill','color'=>'icon-green', 'count'=>$nb_animaux,     'roles'=>['admin','dirigeant','soigneur','soigneur_chef','veterinaire']],
  ['label'=>'Espèces',     'url'=>'/especes/index.php',    'icon'=>'bi-diagram-3-fill',  'color'=>'icon-teal',  'count'=>$nb_especes,     'roles'=>['admin','dirigeant','soigneur','soigneur_chef','veterinaire']],
  ['label'=>'Enclos',      'url'=>'/enclos/index.php',     'icon'=>'bi-geo-alt-fill',    'color'=>'icon-sky',   'count'=>$nb_enclos,      'roles'=>['admin','dirigeant','soigneur','soigneur_chef','technicien']],
  ['label'=>'Zones',       'url'=>'/zones/index.php',      'icon'=>'bi-map-fill',        'color'=>'icon-teal',  'count'=>$nb_zones,       'roles'=>['admin','dirigeant','soigneur','soigneur_chef','technicien']],
  ['label'=>'Soins',       'url'=>'/soins/index.php',      'icon'=>'bi-bandaid-fill',    'color'=>'icon-sky',   'count'=>null,            'roles'=>['admin','dirigeant','soigneur','soigneur_chef','veterinaire']],
  ['label'=>'Personnel',   'url'=>'/personnel/index.php',  'icon'=>'bi-people-fill',     'color'=>'icon-amber', 'count'=>$nb_personnel,   'roles'=>['admin','dirigeant','comptable']],
  ['label'=>'Boutiques',   'url'=>'/boutiques/index.php',  'icon'=>'bi-shop',            'color'=>'icon-rose',  'count'=>$nb_boutiques,   'roles'=>['admin','dirigeant','boutique','vendeur','comptable']],
  ['label'=>'Parrainages', 'url'=>'/parrainages/index.php','icon'=>'bi-heart-fill',      'color'=>'icon-violet','count'=>$nb_parrainages, 'roles'=>['admin','dirigeant','comptable']],
  ['label'=>'Réparations', 'url'=>'/reparations/index.php','icon'=>'bi-tools',           'color'=>'icon-violet','count'=>$nb_reparations, 'roles'=>['admin','dirigeant','technicien']],
];
$modules=array_values(array_filter($tous_modules,fn($m)=>in_array($role,$m['roles'])));
$dot_colors=['soin'=>'dot-green','rep'=>'dot-violet','ca'=>'dot-amber'];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Tableau de bord — Zoo'land</title>
  <link href="<?php echo htmlspecialchars(url_site('/assets/vendor/bootstrap/css/bootstrap.min.css'));?>"
    rel="stylesheet">
  <link href="<?php echo htmlspecialchars(url_site('/assets/css/bootstrap-icons-local.css'));?>" rel="stylesheet">
  <link
    href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400&display=swap"
    rel="stylesheet">
  <link href="<?php echo htmlspecialchars(url_site('/assets/css/global.css'));?>" rel="stylesheet">
  <link href="<?php echo htmlspecialchars(url_site('/assets/css/dashboard.css'));?>" rel="stylesheet">
</head>

<body>
  <div class="d-flex app-layout">
    <div class="app-sidebar-col"><?php include 'includes/sidebar.php';?></div>
    <main class="app-content-col">

      <div class="page-padding">

        <!-- Hero -->
        <div class="hero hero-dashboard parallax reveal"
          style="--hero-bg:url('<?php echo htmlspecialchars(url_site('/assets/img/dashboard-hero.svg'));?>')">
          <div class="hero-tag"><i class="bi bi-grid-1x2-fill"></i> Zoo'land</div>
          <h2 class="hero-title">Bienvenue, <?php echo htmlspecialchars($prenom ?: "sur Zoo'land");?>&nbsp;!</h2>
          <p class="hero-text">Vue consolidée du parc — animaux, équipes, habitats et performances.</p>
          <div class="hero-stats">
            <div class="hero-stat">
              <div class="hero-stat-val"><?php echo $nb_animaux;?></div>
              <div class="hero-stat-lbl">Animaux</div>
            </div>
            <div class="hero-stat">
              <div class="hero-stat-val"><?php echo $nb_especes;?></div>
              <div class="hero-stat-lbl">Espèces</div>
            </div>
            <div class="hero-stat">
              <div class="hero-stat-val"><?php echo $nb_personnel;?></div>
              <div class="hero-stat-lbl">Personnel</div>
            </div>
            <?php if($nb_menaces>0):?>
            <div class="hero-stat" style="background:rgba(217,79,112,.20);border-color:rgba(217,79,112,.30)">
              <div class="hero-stat-val" style="color:#fca5a5"><?php echo $nb_menaces;?></div>
              <div class="hero-stat-lbl">⚠ Menacés</div>
            </div>
            <?php endif;?>
          </div>
          <div class="hero-float">
            <div class="hero-float-lbl">Votre rôle</div>
            <div class="hero-float-val"><?php echo htmlspecialchars($role_label);?></div>
            <div style="font-size:.68rem;color:rgba(255,255,255,.38);margin-top:.18rem">
              <?php echo count($modules);?> module<?php echo count($modules)>1?'s':'';?>
              actif<?php echo count($modules)>1?'s':'';?>
            </div>
          </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid reveal mb-4">
          <div class="stat-card">
            <div class="stat-card-bar" style="background:linear-gradient(90deg,var(--green),#68d391)"></div>
            <div class="stat-label">Zones</div>
            <div class="stat-value"><?php echo $nb_zones;?></div>
            <div class="stat-sub"><?php echo $nb_enclos;?> enclos au total</div>
          </div>

          <div class="stat-card">
            <div class="stat-card-bar" style="background:linear-gradient(90deg,var(--sky),#60a5fa)"></div>
            <div class="stat-label">Espèces</div>
            <div class="stat-value"><?php echo $nb_especes;?></div>
            <div class="stat-sub"><?php echo $nb_animaux;?> animaux enregistrés</div>
          </div>

          <div class="stat-card">
            <div class="stat-card-bar" style="background:linear-gradient(90deg,var(--amber),#f59e0b)"></div>
            <div class="stat-label">Soigneurs actifs</div>
            <div class="stat-value"><?php echo $nb_soigneurs;?></div>
            <div class="stat-sub">vétérinaires inclus</div>
          </div>

          <div class="stat-card">
            <div class="stat-card-bar" style="background:linear-gradient(90deg,var(--teal),#0ea5e9)"></div>
            <div class="stat-label">Parrainages</div>
            <div class="stat-value"><?php echo $nb_parrainages;?></div>
            <div class="stat-sub">engagements actifs</div>
          </div>

          <div class="stat-card">
            <div class="stat-card-bar" style="background:linear-gradient(90deg,var(--violet),#8b5cf6)"></div>
            <div class="stat-label">Personnel</div>
            <div class="stat-value"><?php echo $nb_personnel;?></div>
            <div class="stat-sub"><?php echo $nb_reparations;?> réparations suivies</div>
          </div>

          <div class="stat-card">
            <div class="stat-card-bar" style="background:linear-gradient(90deg,var(--rose),#f43f5e)"></div>
            <div class="stat-label"><?php echo $ca_total>0 ? 'CA Boutiques' : 'Boutiques';?></div>
            <?php if($ca_total>0):?>
            <div class="stat-value" style="font-size:1.5rem"><?php echo number_format($ca_total,0,',',' ');?> €</div>
            <div class="stat-sub"><?php echo $nb_boutiques;?> boutiques suivies</div>
            <?php else:?>
            <div class="stat-value"><?php echo $nb_boutiques;?></div>
            <div class="stat-sub">boutiques suivies</div>
            <?php endif;?>
          </div>
        </div>

        <!-- Alerte menacés -->
        <?php if($nb_menaces>0):?>
        <div class="alert-warn reveal mb-4">
          <div class="alert-warn-title">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <?php echo $nb_menaces;?> animal<?php echo $nb_menaces>1?'aux':'';?> d'espèces menacées
          </div>
          <p style="font-size:.875rem;font-weight:600;margin:0;color:var(--amber)">
            Surveillance renforcée requise.
            <a href="<?php echo htmlspecialchars(url_site('/animaux/index.php'));?>"
              style="color:var(--green);font-weight:800">Voir les animaux →</a>
          </p>
        </div>
        <?php endif;?>

        <!-- Contenu principal -->
        <div class="row g-4">

          <!-- Modules + Animaux -->
          <div class="col-xl-7">

            <!-- Modules -->
            <div class="section-block reveal mb-4">
              <div class="section-block-head">
                <div>
                  <div class="overline mb-1">Accès rapide</div>
                  <div class="section-block-title">Vos modules</div>
                </div>
                <span class="badge badge-neutral"><?php echo count($modules);?> sections</span>
              </div>
              <div style="padding:1.1rem">
                <div class="module-grid">
                  <?php foreach($modules as $m):?>
                  <a href="<?php echo htmlspecialchars(url_site($m['url']));?>" class="module-card">
                    <div class="module-card-icon <?php echo $m['color'];?>">
                      <i class="<?php echo $m['icon'];?>"></i>
                    </div>
                    <div class="module-card-name"><?php echo htmlspecialchars($m['label']);?></div>
                    <?php if($m['count']!==null):?>
                    <div class="module-card-count"><?php echo $m['count'];?></div>
                    <?php endif;?>
                  </a>
                  <?php endforeach;?>
                </div>
              </div>
            </div>

            <!-- Aperçu animaux -->
            <?php if(!empty($animaux_recents)&&in_array($role,['admin','dirigeant','soigneur','soigneur_chef','veterinaire'])):?>
            <div class="section-block reveal">
              <div class="section-block-head">
                <div>
                  <div class="overline mb-1">Registre</div>
                  <div class="section-block-title">Aperçu des animaux</div>
                </div>
                <a href="<?php echo htmlspecialchars(url_site('/animaux/index.php'));?>" class="btn btn-light-surface"
                  style="font-size:.82rem;padding:.45rem .88rem;color:var(--txt)">
                  Voir tous →
                </a>
              </div>
              <div style="padding:.6rem .9rem .9rem">
                <?php foreach($animaux_recents as $a):?>
                <a href="<?php echo htmlspecialchars(url_site('/animaux/detail.php?rfid='.urlencode($a['RFID'])));?>"
                  class="animal-preview-item">
                  <div class="animal-preview-icon">
                    <i class="bi bi-heart-pulse-fill" style="font-size:.82rem"></i>
                  </div>
                  <div style="flex:1;min-width:0">
                    <div class="animal-preview-name"><?php echo htmlspecialchars($a['NOM_ANIMAL']??'—');?></div>
                    <div class="animal-preview-esp"><?php echo htmlspecialchars($a['NOM_USUEL']??'');?></div>
                  </div>
                  <?php if((int)($a['EST_MENACEE']??0)===1):?>
                  <span class="badge badge-menace"><i class="bi bi-exclamation-triangle-fill"></i> Menacé</span>

                  <?php endif;?>
                </a>
                <?php endforeach;?>
              </div>
            </div>
            <?php endif;?>

          </div>

          <!-- Activités -->
          <div class="col-xl-5">
            <div class="section-block reveal" style="height:100%">
              <div class="section-block-head">
                <div>
                  <div class="overline mb-1">Flux</div>
                  <div class="section-block-title">Activités récentes</div>
                </div>
              </div>
              <div style="padding:.65rem .9rem .9rem">
                <div class="activity-feed">
                  <?php if(!empty($activites)):
                  foreach($activites as $act):
                    $dot=$dot_colors[$act['T']??'soin']??'dot-green';?>
                  <div class="activity-entry">
                    <div class="activity-dot <?php echo $dot;?>"></div>
                    <div>
                      <div class="activity-entry-text">
                        <strong><?php echo htmlspecialchars($act['L1']??'');?></strong>
                        <?php if(!empty($act['L2'])):?> — <?php echo htmlspecialchars($act['L2']);?><?php endif;?>
                      </div>
                      <div class="activity-entry-time">
                        <i class="bi bi-calendar3 me-1"></i><?php echo fmt_date($act['D']??'');?>
                      </div>
                    </div>
                  </div>
                  <?php endforeach;
                else:?>
                  <div style="text-align:center;padding:2.5rem;color:var(--txt-muted)">
                    <i class="bi bi-clock-history"
                      style="font-size:2rem;opacity:.35;display:block;margin-bottom:.6rem"></i>
                    <div style="font-weight:600">Aucune activité récente</div>
                  </div>
                  <?php endif;?>
                </div>
              </div>
            </div>
          </div>

        </div><!-- /row -->
      </div><!-- /page-padding -->
    </main>
  </div>

  <script src="<?php echo htmlspecialchars(url_site('/assets/vendor/bootstrap/js/bootstrap.bundle.min.js'));?>">
  </script>
  <script>
    document.querySelectorAll('.reveal').forEach(function(el, i) {
      setTimeout(function() {
        el.classList.add('visible');
      }, 80 + i * 35);
    });
  </script>
</body>

</html>