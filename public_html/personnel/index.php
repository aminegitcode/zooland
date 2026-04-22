<?php

require_once '../includes/auth.php';
require_role(['admin','dirigeant','comptable']);

require_once '../includes/path.php';
require_once '../includes/helpers.php';
require_once '../config.php';


// Variables principales
$role_session = get_role();
$peut_modifier = in_array($role_session, ['admin','dirigeant'], true);
$message = '';
$type_message = 'success';


// Traitement des formulaires
if ($peut_modifier && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Ajouter un personnel
    if ($action === 'ajouter') {
        $nom = trim($_POST['nom_personnel'] ?? '');
        $prenom = trim($_POST['prenom_personnel'] ?? '');
        $salaire = (float)($_POST['salaire'] ?? 0);
        $date = trim($_POST['date_entree'] ?? date('Y-m-d'));
        $mdp = password_hash(trim($_POST['mot_de_passe'] ?? 'temp123'), PASSWORD_BCRYPT);
        $manager = !empty($_POST['id_manager']) ? (int)$_POST['id_manager'] : null;
        $roleId = !empty($_POST['id_role']) ? (int)$_POST['id_role'] : 0;
        $newRole = trim($_POST['nouveau_role'] ?? '');

        $ok = true;

        // Génère un nouvel id_personnel
        $st = oci_parse($conn, "SELECT NVL(MAX(id_personnel),0)+1 FROM Personnel");
        $ok = $st && oci_execute($st);
        $row = $ok ? oci_fetch_array($st, OCI_NUM) : [1];
        $newId = (int)($row[0] ?? 1);
        if ($st) oci_free_statement($st);

        // Si on veut créer un nouveau rôle
        if ($ok && $newRole !== '') {
            $st = oci_parse($conn, "SELECT id_role FROM Role WHERE LOWER(nom_role)=LOWER(:nom)");
            oci_bind_by_name($st, ':nom', $newRole);
            $ok = $st && oci_execute($st);
            $found = $ok ? oci_fetch_assoc($st) : null;
            if ($st) oci_free_statement($st);

            if ($found && !empty($found['ID_ROLE'])) {
                $roleId = (int)$found['ID_ROLE'];
            } else {
                // Génère un nouvel id_role
                $st = oci_parse($conn, "SELECT NVL(MAX(id_role),0)+1 FROM Role");
                $ok = $st && oci_execute($st);
                $row = $ok ? oci_fetch_array($st, OCI_NUM) : [1];
                $roleId = (int)($row[0] ?? 1);
                if ($st) oci_free_statement($st);

                // Crée le rôle
                if ($ok) {
                    $st = oci_parse($conn, "INSERT INTO Role(id_role,nom_role) VALUES(:id,:nom)");
                    oci_bind_by_name($st, ':id', $roleId);
                    oci_bind_by_name($st, ':nom', $newRole);
                    $ok = oci_execute($st);
                    if ($st) oci_free_statement($st);
                }
            }
        }

        // Vérifie qu'un rôle est bien choisi
        if ($ok && $roleId <= 0) {
            $ok = false;
            $message = 'Choisissez un rôle ou créez-en un nouveau.';
            $type_message = 'danger';
        }

        // Ajoute le personnel
        if ($ok) {
            if ($manager) {
                $st = oci_parse($conn, "
                    INSERT INTO Personnel(
                        id_personnel,
                        nom_personnel,
                        prenom_personnel,
                        mot_de_passe,
                        date_entree,
                        salaire,
                        id_manager
                    ) VALUES(
                        :id,
                        :n,
                        :p,
                        :m,
                        TO_DATE(:d,'YYYY-MM-DD'),
                        :s,
                        :mg
                    )
                ");
                oci_bind_by_name($st, ':mg', $manager);
            } else {
                $st = oci_parse($conn, "
                    INSERT INTO Personnel(
                        id_personnel,
                        nom_personnel,
                        prenom_personnel,
                        mot_de_passe,
                        date_entree,
                        salaire
                    ) VALUES(
                        :id,
                        :n,
                        :p,
                        :m,
                        TO_DATE(:d,'YYYY-MM-DD'),
                        :s
                    )
                ");
            }

            oci_bind_by_name($st, ':id', $newId);
            oci_bind_by_name($st, ':n', $nom);
            oci_bind_by_name($st, ':p', $prenom);
            oci_bind_by_name($st, ':m', $mdp);
            oci_bind_by_name($st, ':d', $date);
            oci_bind_by_name($st, ':s', $salaire);

            $ok = oci_execute($st);
            if ($st) oci_free_statement($st);
        }

        // Génère un id pour l'historique
        if ($ok) {
            $st = oci_parse($conn, "SELECT NVL(MAX(id_historique_emploi),0)+1 FROM Historique_emploi");
            $ok = $st && oci_execute($st);
            $row = $ok ? oci_fetch_array($st, OCI_NUM) : [1];
            $histId = (int)($row[0] ?? 1);
            if ($st) oci_free_statement($st);
        }

        // Ajoute la première étape dans l'historique
        if ($ok) {
            $st = oci_parse($conn, "
                INSERT INTO Historique_emploi(
                    id_historique_emploi,
                    id_personnel,
                    id_role,
                    date_debut
                ) VALUES(
                    :id,
                    :pers,
                    :role,
                    TO_DATE(:d,'YYYY-MM-DD')
                )
            ");
            oci_bind_by_name($st, ':id', $histId);
            oci_bind_by_name($st, ':pers', $newId);
            oci_bind_by_name($st, ':role', $roleId);
            oci_bind_by_name($st, ':d', $date);

            $ok = oci_execute($st);
            if ($st) oci_free_statement($st);
        }

        // Message final
        if ($message === '') {
            $message = $ok ? 'Personnel ajouté et parcours initial enregistré.' : 'Erreur lors de l\'ajout du personnel.';
            $type_message = $ok ? 'success' : 'danger';
        }
    }

    // Ajouter une nouvelle étape de rôle
    if ($action === 'changer_role') {
        $idPersonnel = (int)($_POST['id_personnel'] ?? 0);
        $roleId = (int)($_POST['id_role'] ?? 0);
        $dateDebut = trim($_POST['date_debut_role'] ?? date('Y-m-d'));

        if ($idPersonnel > 0 && $roleId > 0) {
            $ok = true;

            // Ferme le rôle actuel
            $st = oci_parse($conn, "
                UPDATE Historique_emploi
                SET date_fin = TO_DATE(:df,'YYYY-MM-DD')
                WHERE id_personnel = :id
                  AND date_fin IS NULL
            ");
            oci_bind_by_name($st, ':df', $dateDebut);
            oci_bind_by_name($st, ':id', $idPersonnel);
            $ok = oci_execute($st);
            if ($st) oci_free_statement($st);

            // Génère un nouvel id_historique_emploi
            $st = oci_parse($conn, "SELECT NVL(MAX(id_historique_emploi),0)+1 FROM Historique_emploi");
            $ok = $ok && oci_execute($st);
            $row = $ok ? oci_fetch_array($st, OCI_NUM) : [1];
            $histId = (int)($row[0] ?? 1);
            if ($st) oci_free_statement($st);

            // Crée la nouvelle étape
            if ($ok) {
                $st = oci_parse($conn, "
                    INSERT INTO Historique_emploi(
                        id_historique_emploi,
                        id_personnel,
                        id_role,
                        date_debut
                    ) VALUES(
                        :id,
                        :pers,
                        :role,
                        TO_DATE(:d,'YYYY-MM-DD')
                    )
                ");
                oci_bind_by_name($st, ':id', $histId);
                oci_bind_by_name($st, ':pers', $idPersonnel);
                oci_bind_by_name($st, ':role', $roleId);
                oci_bind_by_name($st, ':d', $dateDebut);

                $ok = oci_execute($st);
                if ($st) oci_free_statement($st);
            }

            $message = $ok ? 'Nouveau rôle enregistré dans l\'historique.' : 'Erreur lors du changement de rôle.';
            $type_message = $ok ? 'success' : 'danger';
        }
    }
}


// Supprimer un personnel
if ($peut_modifier && isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];

    $st = oci_parse($conn, "DELETE FROM Personnel WHERE id_personnel=:id");
    oci_bind_by_name($st, ':id', $id);

    $ok = oci_execute($st);

    $message = $ok ? 'Personnel supprimé.' : 'Erreur lors de la suppression.';
    $type_message = $ok ? 'success' : 'danger';

    if ($st) oci_free_statement($st);
}


// Liste des rôles
$roles = [];
$st = oci_parse($conn, "SELECT id_role, nom_role FROM Role ORDER BY nom_role");
if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) {
        $roles[] = $row;
    }
    oci_free_statement($st);
}


// Liste des managers possibles
$liste_managers = [];
$st = oci_parse($conn, "
    SELECT id_personnel, nom_personnel, prenom_personnel
    FROM Personnel
    ORDER BY nom_personnel, prenom_personnel
");
if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) {
        $liste_managers[] = $row;
    }
    oci_free_statement($st);
}


// Liste du personnel
$personnel = [];
$sql = "
    SELECT p.id_personnel,
           p.nom_personnel,
           p.prenom_personnel,
           p.date_entree,
           p.salaire,
           pm.nom_personnel manager_nom,
           pm.prenom_personnel manager_prenom,
           (SELECT r.nom_role
              FROM Historique_emploi h
              JOIN Role r ON h.id_role=r.id_role
             WHERE h.id_personnel=p.id_personnel
               AND h.date_fin IS NULL
               AND ROWNUM=1) role_actuel,
           (SELECT COUNT(*) FROM Animal a WHERE a.id_personnel=p.id_personnel) nb_animaux,
           (SELECT COUNT(*) FROM Historique_soins hs WHERE hs.id_personnel=p.id_personnel) nb_soins,
           (SELECT COUNT(*) FROM Nourrissage n WHERE n.id_personnel=p.id_personnel) nb_nourrissages,
           (SELECT COUNT(*) FROM Historique_emploi h WHERE h.id_personnel=p.id_personnel) nb_etapes,
           (SELECT LISTAGG(e.nom_usuel, ', ') WITHIN GROUP(ORDER BY e.nom_usuel)
              FROM Specialiser s
              JOIN Espece e ON s.id_espece=e.id_espece
             WHERE s.id_personnel=p.id_personnel) specialites
    FROM Personnel p
    LEFT JOIN Personnel pm ON p.id_manager=pm.id_personnel
    ORDER BY p.nom_personnel, p.prenom_personnel
";
$st = oci_parse($conn, $sql);
if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) {
        $personnel[] = $row;
    }
    oci_free_statement($st);
}


// Statistiques globales
$nbPersonnel = count($personnel);
$nbParcours = array_sum(array_map(fn($p) => (int)($p['NB_ETAPES'] ?? 0), $personnel));
$salaireMoy = 0;
$salaires = array_filter(array_map(fn($p) => (float)($p['SALAIRE'] ?? 0), $personnel));
if ($salaires) {
    $salaireMoy = array_sum($salaires) / count($salaires);
}


// Ferme la connexion Oracle
oci_close($conn);


// Configuration de la page
$page_title = 'Personnel';
$page_css = '/assets/css/personnel.css';
$page_hero = [
    'kicker' => 'Équipes & parcours',
    'icon' => 'bi bi-people-fill',
    'title' => 'Gestion des personnels',
    'desc' => 'Fiches individuelles, rôle à l\'embauche, historique d\'emploi, animaux suivis et activités soins / nourrissages.',
    'image' => url_site('/assets/img/personnel-hero.svg'),
    'actions' => array_filter([
        $peut_modifier ? ['label' => 'Ajouter un membre', 'icon' => 'bi bi-plus-lg', 'target' => '#modalAjouter', 'class' => 'btn-primary'] : null,
        $peut_modifier ? ['label' => 'Changer un rôle', 'icon' => 'bi bi-shuffle', 'target' => '#modalRole', 'class' => 'btn-light-surface'] : null,
        ['label' => 'Retour dashboard', 'icon' => 'bi bi-arrow-left', 'href' => url_site('/index.php'), 'class' => 'btn-ghost'],
    ]),
    'stats' => [
        ['value' => $nbPersonnel, 'label' => 'membres'],
        ['value' => number_format($salaireMoy, 0, ',', ' ') . ' €', 'label' => 'salaire moyen'],
        ['value' => $nbParcours, 'label' => 'étapes de parcours'],
        ['value' => count(array_filter($personnel, fn($p) => !empty($p['SPECIALITES']))), 'label' => 'profils spécialisés'],
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
      <?php render_alert($message, $type_message); ?>

      <!-- Tableau du personnel -->
      <div class="section-card reveal" style="padding:0;overflow:hidden">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:.92rem 1.15rem;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:.75rem">
          <div style="display:flex;align-items:center;gap:.75rem">
            <span style="font-size:.95rem;font-weight:900;color:var(--txt)">Membres du personnel</span>
            <span class="badge-soft badge-emerald" id="comptPers"><?php echo count($personnel); ?> membre<?php echo count($personnel) > 1 ? 's' : ''; ?></span>
          </div>

          <div style="display:flex;align-items:center;gap:.6rem">
            <input type="search" id="fPers" class="form-control" placeholder="Rechercher…" style="max-width:220px;padding:.42rem .82rem;font-size:.84rem">
            <select id="fRole" class="form-select" style="max-width:175px;padding:.42rem .82rem;font-size:.84rem">
              <option value="">Tous les rôles</option>
              <?php foreach (array_values(array_unique(array_filter(array_map(fn($r) => $r['NOM_ROLE'] ?? '', $roles)))) as $rn): ?>
              <option value="<?php echo htmlspecialchars(strtolower($rn)); ?>"><?php echo htmlspecialchars($rn); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="table-responsive">
          <table style="width:100%;border-collapse:collapse" id="tblPers">
            <thead>
              <tr>
                <th style="padding:.76rem 1.05rem;background:rgba(240,235,225,.92);font-size:.62rem;font-weight:900;letter-spacing:.10em;text-transform:uppercase;color:var(--txt-muted);border-bottom:1px solid var(--border);white-space:nowrap">Membre</th>
                <th style="padding:.76rem 1.05rem;background:rgba(240,235,225,.92);font-size:.62rem;font-weight:900;letter-spacing:.10em;text-transform:uppercase;color:var(--txt-muted);border-bottom:1px solid var(--border)">Rôle actuel</th>
                <th style="padding:.76rem 1.05rem;background:rgba(240,235,225,.92);font-size:.62rem;font-weight:900;letter-spacing:.10em;text-transform:uppercase;color:var(--txt-muted);border-bottom:1px solid var(--border)">Entrée</th>
                <th style="padding:.76rem 1.05rem;background:rgba(240,235,225,.92);font-size:.62rem;font-weight:900;letter-spacing:.10em;text-transform:uppercase;color:var(--txt-muted);border-bottom:1px solid var(--border)">Salaire</th>
                <th style="padding:.76rem 1.05rem;background:rgba(240,235,225,.92);font-size:.62rem;font-weight:900;letter-spacing:.10em;text-transform:uppercase;color:var(--txt-muted);border-bottom:1px solid var(--border)">Spécialisations</th>
                <th style="padding:.76rem 1.05rem;background:rgba(240,235,225,.92);font-size:.62rem;font-weight:900;letter-spacing:.10em;text-transform:uppercase;color:var(--txt-muted);border-bottom:1px solid var(--border);width:100px">Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php
            foreach ($personnel as $i => $p):
              $full = trim(($p['PRENOM_PERSONNEL'] ?? '') . ' ' . ($p['NOM_PERSONNEL'] ?? ''));
              $init = strtoupper(mb_substr($p['PRENOM_PERSONNEL'] ?? '', 0, 1) . mb_substr($p['NOM_PERSONNEL'] ?? '', 0, 1));
              $col = '#2f855a';
              $role_row = strtolower($p['ROLE_ACTUEL'] ?? '');
            ?>
            <tr class="pers-row"
                style="cursor:pointer;border-bottom:1px solid rgba(81,63,35,.06)"
                data-nom="<?php echo strtolower($full); ?>"
                data-role="<?php echo $role_row; ?>"
                data-spec="<?php echo strtolower($p['SPECIALITES'] ?? ''); ?>"
                onclick="window.location.href='<?php echo htmlspecialchars(url_site('/personnel/detail.php?id=' . $p['ID_PERSONNEL'])); ?>'">
              <td style="padding:.76rem 1.05rem;vertical-align:middle">
                <div style="display:flex;align-items:center;gap:.72rem">
                  <div style="width:34px;height:34px;border-radius:10px;background:<?php echo $col; ?>;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:.8rem;color:#fff;flex-shrink:0">
                    <?php echo htmlspecialchars($init ?: '?'); ?>
                  </div>
                  <div>
                    <div style="font-weight:800;font-size:.875rem;color:var(--txt)"><?php echo htmlspecialchars($full); ?></div>
                    <?php if (!empty($p['MANAGER_NOM'])): ?>
                    <div style="font-size:.72rem;color:var(--txt-muted)">
                      → <?php echo htmlspecialchars(($p['MANAGER_PRENOM'] ?? '') . ' ' . ($p['MANAGER_NOM'] ?? '')); ?>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </td>

              <td style="padding:.76rem 1.05rem;vertical-align:middle">
                <?php if (!empty($p['ROLE_ACTUEL'])): ?>
                <span class="badge-soft badge-amber" style="font-size:.72rem"><?php echo htmlspecialchars($p['ROLE_ACTUEL']); ?></span>
                <?php else: ?>
                <span style="font-size:.82rem;color:var(--txt-muted)">—</span>
                <?php endif; ?>
              </td>

              <td style="padding:.76rem 1.05rem;vertical-align:middle;font-size:.84rem;color:var(--txt-muted)">
                <?php echo format_date_fr($p['DATE_ENTREE'] ?? ''); ?>
              </td>

              <td style="padding:.76rem 1.05rem;vertical-align:middle">
                <?php if (!empty($p['SALAIRE'])): ?>
                <span style="font-weight:800;font-size:.84rem;color:var(--green)">
                  <?php echo number_format((float)$p['SALAIRE'], 0, ',', ' '); ?> €
                </span>
                <?php else: ?>
                <span style="color:var(--txt-muted)">—</span>
                <?php endif; ?>
              </td>

              <td style="padding:.76rem 1.05rem;vertical-align:middle">
                <?php if (!empty($p['SPECIALITES'])): ?>
                <span style="font-size:.78rem;color:var(--teal)"><?php echo htmlspecialchars($p['SPECIALITES']); ?></span>
                <?php else: ?>
                <span style="font-size:.78rem;color:var(--txt-muted)">—</span>
                <?php endif; ?>
              </td>

              <td style="padding:.76rem 1.05rem;vertical-align:middle" onclick="event.stopPropagation()">
                <div style="display:flex;gap:.38rem">
                  <a href="<?php echo htmlspecialchars(url_site('/personnel/detail.php?id=' . $p['ID_PERSONNEL'])); ?>"
                     style="width:28px;height:28px;border-radius:7px;background:rgba(47,124,213,.12);color:var(--sky);display:flex;align-items:center;justify-content:center;font-size:.78rem;text-decoration:none"
                     title="Voir fiche">
                    <i class="bi bi-eye-fill"></i>
                  </a>

                  <?php if ($peut_modifier): ?>
                  <a href="?supprimer=<?php echo $p['ID_PERSONNEL']; ?>"
                     onclick="return confirm('Supprimer <?php echo htmlspecialchars(addslashes($full)); ?> ?')"
                     style="width:28px;height:28px;border-radius:7px;background:rgba(217,79,112,.12);color:var(--rose);display:flex;align-items:center;justify-content:center;font-size:.78rem;text-decoration:none"
                     title="Supprimer">
                    <i class="bi bi-trash-fill"></i>
                  </a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>

            <?php if (empty($personnel)): ?>
            <tr>
              <td colspan="6" style="text-align:center;padding:3rem;color:var(--txt-muted)">
                <i class="bi bi-people" style="font-size:2rem;opacity:.3;display:block;margin-bottom:.6rem"></i>
                Aucun personnel enregistré
              </td>
            </tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Script recherche / filtre -->
      <script>
      (function(){
        var fi = document.getElementById('fPers');
        var fr = document.getElementById('fRole');

        function filter(){
          var q = (fi.value || '').toLowerCase();
          var r = (fr.value || '').toLowerCase();
          var n = 0;

          document.querySelectorAll('.pers-row').forEach(function(tr){
            var ok = (tr.dataset.nom.includes(q) || tr.dataset.spec.includes(q))
                  && (r === '' || tr.dataset.role === r);
            tr.style.display = ok ? '' : 'none';
            if(ok) n++;
          });

          var el = document.getElementById('comptPers');
          if (el) el.textContent = n + ' membre' + (n > 1 ? 's' : '');
        }

        fi.addEventListener('input', filter);
        fr.addEventListener('change', filter);
      })();
      </script>

      <?php if ($peut_modifier): ?>
      <!-- Modal ajout -->
      <div class="modal fade" id="modalAjouter" tabindex="-1">
        <div class="modal-dialog modal-xl">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title fw-bold">Ajouter un personnel</h5>
              <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
              <form method="POST">
                <input type="hidden" name="action" value="ajouter">

                <div class="grid-auto">
                  <div>
                    <label class="form-label">Nom</label>
                    <input class="form-control" name="nom_personnel" required>
                  </div>

                  <div>
                    <label class="form-label">Prénom</label>
                    <input class="form-control" name="prenom_personnel" required>
                  </div>

                  <div>
                    <label class="form-label">Salaire</label>
                    <input class="form-control" type="number" step="0.01" name="salaire">
                  </div>

                  <div>
                    <label class="form-label">Date d'entrée</label>
                    <input class="form-control" type="date" name="date_entree" value="<?php echo date('Y-m-d'); ?>" required>
                  </div>

                  <div>
                    <label class="form-label">Mot de passe initial</label>
                    <input class="form-control" name="mot_de_passe" value="temp123" required>
                  </div>

                  <div>
                    <label class="form-label">Manager</label>
                    <select class="form-select" name="id_manager">
                      <option value="">— Aucun —</option>
                      <?php foreach ($liste_managers as $m): ?>
                      <option value="<?php echo $m['ID_PERSONNEL']; ?>">
                        <?php echo htmlspecialchars($m['PRENOM_PERSONNEL'] . ' ' . $m['NOM_PERSONNEL']); ?>
                      </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div>
                    <label class="form-label">Rôle existant</label>
                    <select class="form-select" name="id_role">
                      <option value="">— Choisir —</option>
                      <?php foreach ($roles as $r): ?>
                      <option value="<?php echo $r['ID_ROLE']; ?>"><?php echo htmlspecialchars($r['NOM_ROLE']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div>
                    <label class="form-label">Ou créer un rôle</label>
                    <input class="form-control" name="nouveau_role" placeholder="Ex: Responsable médiation">
                  </div>
                </div>

                <div class="action-row justify-content-end">
                  <button type="button" class="btn btn-light-surface" data-bs-dismiss="modal">Annuler</button>
                  <button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Ajouter</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>

      <!-- Modal changement de rôle -->
      <div class="modal fade" id="modalRole" tabindex="-1">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title fw-bold">Enregistrer un nouveau rôle dans le parcours</h5>
              <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
              <form method="POST">
                <input type="hidden" name="action" value="changer_role">

                <div class="grid-auto">
                  <div>
                    <label class="form-label">Personnel</label>
                    <select class="form-select" name="id_personnel" required>
                      <option value="">— Choisir —</option>
                      <?php foreach ($personnel as $p): ?>
                      <option value="<?php echo $p['ID_PERSONNEL']; ?>">
                        <?php echo htmlspecialchars(($p['PRENOM_PERSONNEL'] ?? '') . ' ' . ($p['NOM_PERSONNEL'] ?? '')); ?>
                      </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div>
                    <label class="form-label">Nouveau rôle</label>
                    <select class="form-select" name="id_role" required>
                      <option value="">— Choisir —</option>
                      <?php foreach ($roles as $r): ?>
                      <option value="<?php echo $r['ID_ROLE']; ?>"><?php echo htmlspecialchars($r['NOM_ROLE']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div>
                    <label class="form-label">Date de début</label>
                    <input class="form-control" type="date" name="date_debut_role" value="<?php echo date('Y-m-d'); ?>" required>
                  </div>
                </div>

                <div class="action-row justify-content-end">
                  <button type="button" class="btn btn-light-surface" data-bs-dismiss="modal">Annuler</button>
                  <button class="btn btn-primary"><i class="bi bi-shuffle"></i> Enregistrer</button>
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
</script>
</body>
</html>
