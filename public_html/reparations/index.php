<?php

require_once '../includes/auth.php';
require_role(['admin','dirigeant','technicien']);

require_once '../includes/path.php';
require_once '../includes/helpers.php';
require_once '../config.php';


// Variables principales
$peut_modifier = in_array(get_role(), ['admin','dirigeant','technicien'], true);
$message = '';
$type = 'success';


// Traitement des formulaires
if ($peut_modifier && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Ajouter une réparation
    if ($action === 'ajouter') {
        $date = trim($_POST['date_reparation'] ?? date('Y-m-d'));
        $nature = trim($_POST['nature'] ?? '');
        $idEnclos = (int)($_POST['id_enclos'] ?? 0);

        // Si gros travaux => prestataire, sinon personnel interne
        $gros = !empty($_POST['gros_travaux']);
        $idPrest = $gros ? (int)($_POST['id_prestataire'] ?? 0) : null;
        $idPers = $gros ? null : (int)($_POST['id_personnel'] ?? 0);

        // Génère un nouvel id
        $st = oci_parse($conn, "SELECT NVL(MAX(id_reparation),0)+1 FROM Reparation");
        oci_execute($st);
        $r = oci_fetch_array($st, OCI_NUM);
        $id = (int)($r[0] ?? 1);
        oci_free_statement($st);

        // Insertion de la réparation
        $st = oci_parse($conn, "
            INSERT INTO Reparation(
                id_reparation,
                date_reparation,
                nature,
                id_enclos,
                id_prestataire,
                id_personnel
            )
            VALUES(
                :id,
                TO_DATE(:d,'YYYY-MM-DD'),
                :na,
                :en,
                :pr,
                :pe
            )
        ");
        oci_bind_by_name($st, ':id', $id);
        oci_bind_by_name($st, ':d', $date);
        oci_bind_by_name($st, ':na', $nature);
        oci_bind_by_name($st, ':en', $idEnclos);
        oci_bind_by_name($st, ':pr', $idPrest);
        oci_bind_by_name($st, ':pe', $idPers);

        $ok = oci_execute($st);
        if ($st) oci_free_statement($st);

        $message = $ok ? 'Réparation enregistrée.' : 'Erreur.';
        $type = $ok ? 'success' : 'danger';
    }

    // Ajouter un prestataire
    if ($action === 'ajouter_prestataire') {
        $nom = trim($_POST['nom_prestataire'] ?? '');
        $tp = trim($_POST['type_prestataire'] ?? 'Travaux');

        // Génère un nouvel id
        $st = oci_parse($conn, "SELECT NVL(MAX(id_prestataire),0)+1 FROM Prestataire");
        oci_execute($st);
        $r = oci_fetch_array($st, OCI_NUM);
        $id = (int)($r[0] ?? 1);
        oci_free_statement($st);

        // Insertion du prestataire
        $st = oci_parse($conn, "
            INSERT INTO Prestataire(id_prestataire,nom_prestataire,type_prestataire)
            VALUES(:id,:n,:t)
        ");
        oci_bind_by_name($st, ':id', $id);
        oci_bind_by_name($st, ':n', $nom);
        oci_bind_by_name($st, ':t', $tp);

        $ok = oci_execute($st);
        if ($st) oci_free_statement($st);

        $message = $ok ? 'Prestataire ajouté.' : 'Erreur.';
        $type = $ok ? 'success' : 'danger';
    }

    // Modifier un prestataire
    if ($action === 'modifier_prestataire') {
        $id = (int)($_POST['id_prestataire'] ?? 0);
        $nom = trim($_POST['nom_prestataire'] ?? '');
        $tp = trim($_POST['type_prestataire'] ?? '');

        $st = oci_parse($conn, "
            UPDATE Prestataire
            SET nom_prestataire = :n,
                type_prestataire = :t
            WHERE id_prestataire = :id
        ");
        oci_bind_by_name($st, ':id', $id);
        oci_bind_by_name($st, ':n', $nom);
        oci_bind_by_name($st, ':t', $tp);

        $ok = oci_execute($st);
        if ($st) oci_free_statement($st);

        $message = $ok ? 'Prestataire modifié.' : 'Erreur.';
        $type = $ok ? 'success' : 'danger';
    }
}


// Supprimer un prestataire
if ($peut_modifier && isset($_GET['supprimer_prestataire'])) {
    $idP = (int)$_GET['supprimer_prestataire'];

    $st = oci_parse($conn, "DELETE FROM Prestataire WHERE id_prestataire = :id");
    oci_bind_by_name($st, ':id', $idP);

    $ok = oci_execute($st);
    if ($st) oci_free_statement($st);

    $message = $ok ? 'Prestataire supprimé.' : 'Suppression impossible.';
    $type = $ok ? 'success' : 'danger';
}


// Liste des enclos
$enclos = [];
$st = oci_parse($conn, "SELECT id_enclos FROM Enclos ORDER BY id_enclos");
if ($st && oci_execute($st)) {
    while ($r = oci_fetch_assoc($st)) {
        $enclos[] = $r;
    }
    oci_free_statement($st);
}


// Liste des prestataires
$prests = [];
$st = oci_parse($conn, "
    SELECT id_prestataire,
           nom_prestataire,
           type_prestataire,
           email_prestataire,
           telephone_prestataire
    FROM Prestataire
    ORDER BY nom_prestataire
");
if ($st && oci_execute($st)) {
    while ($r = oci_fetch_assoc($st)) {
        $prests[] = $r;
    }
    oci_free_statement($st);
}


// Liste du personnel technique
$pers = [];
$st = oci_parse($conn, "
    SELECT p.id_personnel,
           p.prenom_personnel,
           p.nom_personnel,
           r.nom_role
    FROM Personnel p,Historique_emploi h,Role r
    WHERE p.id_personnel = h.id_personnel
      AND h.id_role = r.id_role
      AND h.date_fin IS NULL
      AND LOWER(r.nom_role) IN ('personnel technique','personnel entretien')
    ORDER BY p.nom_personnel
");
if ($st && oci_execute($st)) {
    while ($r = oci_fetch_assoc($st)) {
        $pers[] = $r;
    }
    oci_free_statement($st);
}


// Liste des réparations
$reps = [];
$st = oci_parse($conn, "
    SELECT r.id_reparation,
           r.date_reparation,
           r.nature,
           e.id_enclos,
           p.prenom_personnel,
           p.nom_personnel,
           pr.nom_prestataire,
           pr.type_prestataire
    FROM Reparation r
    LEFT JOIN Enclos e ON r.id_enclos = e.id_enclos
    LEFT JOIN Personnel p ON r.id_personnel = p.id_personnel
    LEFT JOIN Prestataire pr ON r.id_prestataire = pr.id_prestataire
    ORDER BY r.date_reparation DESC
");
if ($st && oci_execute($st)) {
    while ($r = oci_fetch_assoc($st)) {
        $reps[] = $r;
    }
    oci_free_statement($st);
}


// Ferme la connexion Oracle
oci_close($conn);


// Configuration page
$page_title = 'Réparations';
$page_css = '/assets/css/reparations.css';
$page_hero = [
    'kicker' => 'Maintenance',
    'icon'   => 'bi bi-tools',
    'title'  => 'Réparations & Maintenance',
    'desc'   => 'Interventions internes, gros travaux externes, équipes techniques et prestataires.',
    'image'  => url_site('/assets/img/repairs-hero.svg'),
    'actions'=> array_filter([
        $peut_modifier ? ['label'=>'Ajouter une réparation','icon'=>'bi bi-plus-lg','target'=>'#mRep','class'=>'btn-primary'] : null,
        $peut_modifier ? ['label'=>'Gérer prestataires','icon'=>'bi bi-buildings-fill','target'=>'#mPrest','class'=>'btn-light-surface'] : null,
        ['label'=>'Dashboard','icon'=>'bi bi-arrow-left','href'=>url_site('/index.php'),'class'=>'btn-ghost'],
    ]),
    'stats' => [
        ['value'=>count($reps),'label'=>'réparations'],
        ['value'=>count($pers),'label'=>'agents techniques'],
        ['value'=>count(array_filter($reps, fn($r) => !empty($r['NOM_PRESTATAIRE']))),'label'=>'gros travaux'],
        ['value'=>count($prests),'label'=>'prestataires'],
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

      <!-- Cartes réparations -->
      <div class="repair-grid mb-2" data-filter-group="reps">
        <?php foreach ($reps as $r):
            $ext = !empty($r['NOM_PRESTATAIRE']);
            $who = $ext ? ($r['NOM_PRESTATAIRE'] ?? '—') : trim(($r['PRENOM_PERSONNEL'] ?? '') . ' ' . ($r['NOM_PERSONNEL'] ?? ''));
            $search = strtolower(trim(($r['NATURE'] ?? '') . ' ' . ($r['ID_ENCLOS'] ?? '') . ' ' . $who));
        ?>
        <article class="repair-card" data-filter-item
                 data-mode="<?php echo $ext ? 'externe' : 'interne'; ?>"
                 data-search="<?php echo htmlspecialchars($search); ?>">
          <div class="repair-card-bar <?php echo $ext ? 'rc-bar-externe' : 'rc-bar-interne'; ?>"></div>

          <div class="repair-card-inner">
            <div class="repair-head">
              <div>
                <div class="repair-title"><?php echo htmlspecialchars($r['NATURE'] ?? 'Réparation'); ?></div>
                <div class="repair-sub">
                  <i class="bi bi-geo-alt-fill" style="font-size:.72rem;color:var(--amber)"></i>
                  Enclos #<?php echo htmlspecialchars($r['ID_ENCLOS'] ?? '—'); ?>
                </div>
              </div>
              <span class="badge-soft <?php echo $ext ? 'badge-rose' : 'badge-emerald'; ?>">
                <?php echo $ext ? 'Externe' : 'Interne'; ?>
              </span>
            </div>

            <div class="mini-list">
              <div class="mini-row">
                <span><i class="bi bi-calendar3 me-1"></i>Date</span>
                <strong><?php echo format_date_fr($r['DATE_REPARATION']); ?></strong>
              </div>
              <div class="mini-row">
                <span><i class="bi bi-person-fill me-1"></i>Intervenant</span>
                <strong><?php echo htmlspecialchars($who ?: '—'); ?></strong>
              </div>
              <div class="mini-row">
                <span>Type</span>
                <strong><?php echo $ext ? htmlspecialchars($r['TYPE_PRESTATAIRE'] ?? 'Prestataire') : 'Personnel technique'; ?></strong>
              </div>
            </div>

            <div class="item-footer">
              <a class="btn btn-primary" style="font-size:.82rem;padding:.52rem .9rem"
                 href="<?php echo htmlspecialchars(url_site('/reparations/detail.php?id=' . $r['ID_REPARATION'])); ?>">
                <i class="bi bi-eye-fill"></i> Détail
              </a>
            </div>
          </div>
        </article>
        <?php endforeach; ?>

        <?php if (empty($reps)): ?>
        <div style="grid-column:1/-1;text-align:center;padding:3rem;color:var(--txt-muted)">
          <i class="bi bi-tools" style="font-size:2.5rem;opacity:.3;display:block;margin-bottom:.75rem"></i>
          <div style="font-weight:700">Aucune réparation enregistrée</div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Section agents -->
      <div class="section-agents reveal">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem">
          <div>
            <div class="overline">Équipe maintenance</div>
            <h2 class="page-title-sm">Agents techniques</h2>
          </div>
          <span class="badge-soft badge-emerald"><?php echo count($pers); ?> agent<?php echo count($pers) > 1 ? 's' : ''; ?></span>
        </div>

        <?php if (empty($pers)): ?>
          <div style="color:var(--txt-muted);font-size:.88rem">Aucun agent technique trouvé.</div>
        <?php else: ?>
          <div class="agents-grid">
            <?php
            $avcols = ['#16a34a','#0284c7','#d97706','#e11d48','#7c3aed','#0d9488'];
            foreach ($pers as $i => $p):
              $ini = strtoupper(mb_substr($p['PRENOM_PERSONNEL'],0,1) . mb_substr($p['NOM_PERSONNEL'],0,1));
            ?>
            <div class="agent-card">
              <div class="agent-av" style="background:<?php echo $avcols[$i % count($avcols)]; ?>">
                <?php echo htmlspecialchars($ini); ?>
              </div>
              <div>
                <div class="agent-name"><?php echo htmlspecialchars($p['PRENOM_PERSONNEL'] . ' ' . $p['NOM_PERSONNEL']); ?></div>
                <div class="agent-role"><?php echo htmlspecialchars($p['NOM_ROLE'] ?? 'Technique'); ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Section prestataires -->
      <div class="section-agents reveal" style="margin-top:1.25rem">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem">
          <div>
            <div class="overline">Partenaires externes</div>
            <h2 class="page-title-sm">Prestataires</h2>
          </div>

          <div style="display:flex;align-items:center;gap:.65rem">
            <span class="badge-soft badge-rose"><?php echo count($prests); ?> prestataire<?php echo count($prests) > 1 ? 's' : ''; ?></span>
            <?php if ($peut_modifier): ?>
            <button class="btn btn-light-surface" style="font-size:.82rem;padding:.52rem .9rem;color:var(--txt)" data-bs-toggle="modal" data-bs-target="#mPrest">
              <i class="bi bi-gear-fill"></i> Gérer
            </button>
            <?php endif; ?>
          </div>
        </div>

        <?php if (empty($prests)): ?>
          <div style="color:var(--txt-muted);font-size:.88rem">Aucun prestataire enregistré.</div>
        <?php else: ?>
          <div class="presta-grid">
            <?php foreach ($prests as $p): ?>
            <div class="presta-card">
              <div class="presta-name"><?php echo htmlspecialchars($p['NOM_PRESTATAIRE']); ?></div>
              <div class="presta-type">
                <i class="bi bi-buildings-fill" style="color:var(--rose)"></i>
                <?php echo htmlspecialchars($p['TYPE_PRESTATAIRE'] ?? '—'); ?>
              </div>

              <?php if (!empty($p['EMAIL_PRESTATAIRE'])): ?>
              <div class="presta-type mt-1">
                <i class="bi bi-envelope-fill" style="color:var(--sky)"></i>
                <?php echo htmlspecialchars($p['EMAIL_PRESTATAIRE']); ?>
              </div>
              <?php endif; ?>

              <?php if (!empty($p['TELEPHONE_PRESTATAIRE'])): ?>
              <div class="presta-type mt-1">
                <i class="bi bi-telephone-fill" style="color:var(--green)"></i>
                <?php echo htmlspecialchars($p['TELEPHONE_PRESTATAIRE']); ?>
              </div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($peut_modifier): ?>
      <!-- Modal ajout réparation -->
      <div class="modal fade" id="mRep" tabindex="-1">
        <div class="modal-dialog modal-xl">
          <div class="modal-content">

            <div class="modal-header">
              <h5 class="modal-title fw-bold"><i class="bi bi-tools me-2"></i>Ajouter une réparation</h5>
              <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
              <form method="POST">
                <input type="hidden" name="action" value="ajouter">

                <div class="grid-auto">
                  <div>
                    <label class="form-label">Date</label>
                    <input class="form-control" type="date" name="date_reparation" value="<?php echo date('Y-m-d'); ?>" required>
                  </div>

                  <div>
                    <label class="form-label">Nature des travaux</label>
                    <input class="form-control" name="nature" placeholder="Réfection clôture, soudure…" required>
                  </div>

                  <div>
                    <label class="form-label">Enclos</label>
                    <select class="form-select" name="id_enclos" required>
                      <option value="">— Choisir —</option>
                      <?php foreach ($enclos as $e): ?>
                      <option value="<?php echo $e['ID_ENCLOS']; ?>">Enclos #<?php echo $e['ID_ENCLOS']; ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div style="display:flex;align-items:end">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="gT" name="gros_travaux" onchange="var gt=this.checked;document.getElementById('secInt').style.display=gt?'none':'block';document.getElementById('secExt').style.display=gt?'block':'none';">
                      <label class="form-check-label" for="gT">Gros travaux (prestataire externe)</label>
                    </div>
                  </div>

                  <div id="secInt">
                    <label class="form-label">Agent technique</label>
                    <select class="form-select" name="id_personnel">
                      <option value="">— Choisir —</option>
                      <?php foreach ($pers as $p): ?>
                      <option value="<?php echo $p['ID_PERSONNEL']; ?>"><?php echo htmlspecialchars($p['PRENOM_PERSONNEL'] . ' ' . $p['NOM_PERSONNEL']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div id="secExt" style="display:none">
                    <label class="form-label">Prestataire externe</label>
                    <select class="form-select" name="id_prestataire">
                      <option value="">— Choisir —</option>
                      <?php foreach ($prests as $p): ?>
                      <option value="<?php echo $p['ID_PRESTATAIRE']; ?>">
                        <?php echo htmlspecialchars($p['NOM_PRESTATAIRE'] . ' (' . $p['TYPE_PRESTATAIRE'] . ')'); ?>
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

      <!-- Modal gestion prestataires -->
      <div class="modal fade" id="mPrest" tabindex="-1">
        <div class="modal-dialog modal-xl">
          <div class="modal-content">

            <div class="modal-header">
              <h5 class="modal-title fw-bold"><i class="bi bi-buildings-fill me-2"></i>Gérer les prestataires</h5>
              <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
              <!-- Ajout prestataire -->
              <div class="section-card mb-4">
                <div class="overline mb-2">Nouveau prestataire</div>
                <form method="POST">
                  <input type="hidden" name="action" value="ajouter_prestataire">

                  <div class="grid-auto">
                    <div>
                      <label class="form-label">Nom *</label>
                      <input class="form-control" name="nom_prestataire" required>
                    </div>

                    <div>
                      <label class="form-label">Type *</label>
                      <input class="form-control" name="type_prestataire" placeholder="Construction, Électricité…" required>
                    </div>
                  </div>

                  <div class="action-row justify-content-end mt-2">
                    <button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Ajouter</button>
                  </div>
                </form>
              </div>

              <!-- Liste prestataires -->
              <div class="grid-cards">
                <?php foreach ($prests as $p): ?>
                <div class="section-card">
                  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.75rem;margin-bottom:.85rem">
                    <div>
                      <div class="overline">Prestataire</div>
                      <div style="font-weight:900;font-size:.95rem"><?php echo htmlspecialchars($p['NOM_PRESTATAIRE']); ?></div>
                      <div class="text-muted" style="font-size:.8rem"><?php echo htmlspecialchars($p['TYPE_PRESTATAIRE'] ?? '—'); ?></div>
                    </div>

                    <a class="btn btn-light-surface" style="font-size:.8rem;padding:.4rem .8rem;color:var(--rose)"
                       href="?supprimer_prestataire=<?php echo $p['ID_PRESTATAIRE']; ?>"
                       onclick="return confirm('Supprimer ce prestataire ?')">
                      <i class="bi bi-trash-fill"></i>
                    </a>
                  </div>

                  <form method="POST">
                    <input type="hidden" name="action" value="modifier_prestataire">
                    <input type="hidden" name="id_prestataire" value="<?php echo $p['ID_PRESTATAIRE']; ?>">

                    <div class="grid-auto">
                      <div>
                        <label class="form-label">Nom</label>
                        <input class="form-control" name="nom_prestataire" value="<?php echo htmlspecialchars($p['NOM_PRESTATAIRE']); ?>" required>
                      </div>

                      <div>
                        <label class="form-label">Type</label>
                        <input class="form-control" name="type_prestataire" value="<?php echo htmlspecialchars($p['TYPE_PRESTATAIRE'] ?? ''); ?>" required>
                      </div>
                    </div>

                    <div class="action-row justify-content-end mt-2">
                      <button class="btn btn-primary" style="font-size:.82rem;padding:.52rem .9rem">
                        <i class="bi bi-pencil-fill"></i> Modifier
                      </button>
                    </div>
                  </form>
                </div>
                <?php endforeach; ?>
              </div>
            </div>

          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Script filtre -->
      <script>
      (function(){
        var recherche = document.getElementById('rechercheReparations');
        var filtre = document.getElementById('filtreModeReparation');

        function appliquer(){
          var q = (recherche.value || '').toLowerCase();
          var mode = (filtre.value || '').toLowerCase();

          document.querySelectorAll('.repair-grid [data-filter-item]').forEach(function(card){
            var texte = (card.dataset.search || '').toLowerCase();
            var modeCarte = (card.dataset.mode || '').toLowerCase();
            var visible = texte.indexOf(q) !== -1;
            if (visible && mode !== '' && modeCarte !== mode) visible = false;
            card.style.display = visible ? '' : 'none';
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
