<?php
// Accès + fichiers nécessaires
require_once '../includes/auth.php';
require_role(['admin','dirigeant','comptable']);

require_once '../includes/path.php';
require_once '../includes/helpers.php';
require_once '../config.php';


// Droits de modification
$peut_modifier = in_array(get_role(), ['admin', 'dirigeant'], true);
$message = '';
$type = 'success';


// Limites de prestations par niveau
$limites_prestations = [
    'bronze'  => 2,
    'argent'  => 3,
    'or'      => null,
    'platine' => null,
];


/* =========================
   Fonctions simples
========================= */

// Met le niveau en minuscule et propre
function niveau_normalise(string $niveau): string {
    return strtolower(trim($niveau));
}

// Retourne la limite selon le niveau
function limite_prestations_pour_niveau(string $niveau, array $limites): ?int {
    $niveau = niveau_normalise($niveau);
    return $limites[$niveau] ?? null;
}

// Formate une date
function formater_date($date, string $fallback = '—'): string {
    if (empty($date)) {
        return $fallback;
    }

    $timestamp = strtotime((string)$date);
    return $timestamp ? date('d/m/Y', $timestamp) : $fallback;
}

// Récupère le prochain id d'une table
function get_next_id($conn, string $table, string $id_column): int {
    $sql = "SELECT NVL(MAX($id_column), 0) + 1 AS NEXT_ID FROM $table";
    $st = oci_parse($conn, $sql);
    oci_execute($st);
    $row = oci_fetch_assoc($st);
    oci_free_statement($st);

    return (int)($row['NEXT_ID'] ?? 1);
}

// Compte le nombre de prestations liées à un parrainage
function compter_prestations_parrainage($conn, int $idParrainage): int {
    $sql = "SELECT COUNT(*) AS NB FROM Offrir WHERE id_parrainage = :id";
    $st = oci_parse($conn, $sql);
    oci_bind_by_name($st, ':id', $idParrainage);
    oci_execute($st);
    $row = oci_fetch_assoc($st);
    oci_free_statement($st);

    return (int)($row['NB'] ?? 0);
}

// Vérifie si la prestation est déjà attribuée
function prestation_deja_attribuee($conn, int $idParrainage, int $idPrestation): bool {
    $sql = "SELECT COUNT(*) AS NB
            FROM Offrir
            WHERE id_parrainage = :id_parrainage
              AND id_prestation = :id_prestation";
    $st = oci_parse($conn, $sql);
    oci_bind_by_name($st, ':id_parrainage', $idParrainage);
    oci_bind_by_name($st, ':id_prestation', $idPrestation);
    oci_execute($st);
    $row = oci_fetch_assoc($st);
    oci_free_statement($st);

    return (int)($row['NB'] ?? 0) > 0;
}

// Récupère le niveau d'un parrainage
function recuperer_niveau_parrainage($conn, int $idParrainage): ?string {
    $sql = "SELECT niveau FROM Parrainage WHERE id_parrainage = :id";
    $st = oci_parse($conn, $sql);
    oci_bind_by_name($st, ':id', $idParrainage);
    oci_execute($st);
    $row = oci_fetch_assoc($st);
    oci_free_statement($st);

    return $row['NIVEAU'] ?? null;
}

// Ajoute une prestation à un parrainage
function ajouter_prestation_parrainage($conn, int $idParrainage, int $idPrestation, array $limites): array {
    if ($idParrainage <= 0 || $idPrestation <= 0) {
        return ['ok' => false, 'message' => 'Données invalides.'];
    }

    if (prestation_deja_attribuee($conn, $idParrainage, $idPrestation)) {
        return ['ok' => false, 'message' => 'Cette prestation est déjà attribuée.'];
    }

    $niveau = recuperer_niveau_parrainage($conn, $idParrainage);
    if ($niveau === null) {
        return ['ok' => false, 'message' => 'Parrainage introuvable.'];
    }

    $limite = limite_prestations_pour_niveau($niveau, $limites);
    $nb_actuelles = compter_prestations_parrainage($conn, $idParrainage);

    if ($limite !== null && $nb_actuelles >= $limite) {
        return ['ok' => false, 'message' => 'Limite de prestations atteinte pour ce niveau.'];
    }

    $sql = "INSERT INTO Offrir(id_parrainage, id_prestation)
            VALUES(:id_parrainage, :id_prestation)";
    $st = oci_parse($conn, $sql);
    oci_bind_by_name($st, ':id_parrainage', $idParrainage);
    oci_bind_by_name($st, ':id_prestation', $idPrestation);
    $ok = oci_execute($st);
    oci_free_statement($st);

    return [
        'ok' => $ok,
        'message' => $ok ? 'Prestation ajoutée.' : 'Erreur lors de l’ajout de la prestation.'
    ];
}

// Retire une prestation d'un parrainage
function retirer_prestation_parrainage($conn, int $idParrainage, int $idPrestation): array {
    if ($idParrainage <= 0 || $idPrestation <= 0) {
        return ['ok' => false, 'message' => 'Données invalides.'];
    }

    $sql = "DELETE FROM Offrir
            WHERE id_parrainage = :id_parrainage
              AND id_prestation = :id_prestation";
    $st = oci_parse($conn, $sql);
    oci_bind_by_name($st, ':id_parrainage', $idParrainage);
    oci_bind_by_name($st, ':id_prestation', $idPrestation);
    $ok = oci_execute($st);
    oci_free_statement($st);

    return [
        'ok' => $ok,
        'message' => $ok ? 'Prestation retirée.' : 'Erreur lors du retrait de la prestation.'
    ];
}


/* =========================
   Traitements POST
========================= */

if ($peut_modifier && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Ajouter un visiteur
    if ($action === 'ajouter_visiteur') {
        $nom = trim($_POST['nom_visiteur'] ?? '');
        $prenom = trim($_POST['prenom_visiteur'] ?? '');
        $email = trim($_POST['email_visiteur'] ?? '');

        $id = get_next_id($conn, 'Visiteur', 'id_visiteur');

        $sql = "INSERT INTO Visiteur(id_visiteur, nom_visiteur, prenom_visiteur, email_visiteur)
                VALUES(:id, :nom, :prenom, :email)";
        $st = oci_parse($conn, $sql);
        oci_bind_by_name($st, ':id', $id);
        oci_bind_by_name($st, ':nom', $nom);
        oci_bind_by_name($st, ':prenom', $prenom);
        oci_bind_by_name($st, ':email', $email);
        $ok = oci_execute($st);
        oci_free_statement($st);

        $message = $ok ? 'Visiteur ajouté.' : 'Erreur lors de l’ajout du visiteur.';
        $type = $ok ? 'success' : 'danger';
    }

    // Modifier un visiteur
    if ($action === 'modifier_visiteur') {
        $id_visiteur = (int)($_POST['id_visiteur'] ?? 0);
        $nom = trim($_POST['nom_visiteur'] ?? '');
        $prenom = trim($_POST['prenom_visiteur'] ?? '');
        $email = trim($_POST['email_visiteur'] ?? '');

        $sql = "UPDATE Visiteur
                SET nom_visiteur = :nom,
                    prenom_visiteur = :prenom,
                    email_visiteur = :email
                WHERE id_visiteur = :id";
        $st = oci_parse($conn, $sql);
        oci_bind_by_name($st, ':nom', $nom);
        oci_bind_by_name($st, ':prenom', $prenom);
        oci_bind_by_name($st, ':email', $email);
        oci_bind_by_name($st, ':id', $id_visiteur);
        $ok = oci_execute($st);
        oci_free_statement($st);

        $message = $ok ? 'Visiteur modifié.' : 'Erreur lors de la modification.';
        $type = $ok ? 'success' : 'danger';
    }

    // Ajouter un parrainage
    if ($action === 'ajouter_parrainage') {
        $date_debut = trim($_POST['date_debut'] ?? date('Y-m-d'));
        $date_fin = !empty($_POST['date_fin']) ? trim($_POST['date_fin']) : null;
        $niveau = trim($_POST['niveau'] ?? 'Or');
        $rfid = trim($_POST['rfid'] ?? '');
        $id_visiteur = (int)($_POST['id_visiteur'] ?? 0);

        $id_parrainage = get_next_id($conn, 'Parrainage', 'id_parrainage');

        if ($date_fin !== null) {
            $sql = "INSERT INTO Parrainage(
                        id_parrainage,
                        date_debut_parrainage,
                        date_fin_parrainage,
                        niveau,
                        rfid,
                        id_visiteur
                    ) VALUES(
                        :id,
                        TO_DATE(:date_debut, 'YYYY-MM-DD'),
                        TO_DATE(:date_fin, 'YYYY-MM-DD'),
                        :niveau,
                        :rfid,
                        :id_visiteur
                    )";
            $st = oci_parse($conn, $sql);
            oci_bind_by_name($st, ':date_fin', $date_fin);
        } else {
            $sql = "INSERT INTO Parrainage(
                        id_parrainage,
                        date_debut_parrainage,
                        niveau,
                        rfid,
                        id_visiteur
                    ) VALUES(
                        :id,
                        TO_DATE(:date_debut, 'YYYY-MM-DD'),
                        :niveau,
                        :rfid,
                        :id_visiteur
                    )";
            $st = oci_parse($conn, $sql);
        }

        oci_bind_by_name($st, ':id', $id_parrainage);
        oci_bind_by_name($st, ':date_debut', $date_debut);
        oci_bind_by_name($st, ':niveau', $niveau);
        oci_bind_by_name($st, ':rfid', $rfid);
        oci_bind_by_name($st, ':id_visiteur', $id_visiteur);
        $ok = oci_execute($st);
        oci_free_statement($st);

        $message = $ok ? 'Parrainage ajouté.' : 'Erreur lors de l’ajout du parrainage.';
        $type = $ok ? 'success' : 'danger';
    }

    // Ajouter une prestation à un parrainage
    if ($action === 'ajouter_prestation') {
        $id_parrainage = (int)($_POST['id_parrainage'] ?? 0);
        $id_prestation = (int)($_POST['id_prestation'] ?? 0);

        $resultat = ajouter_prestation_parrainage($conn, $id_parrainage, $id_prestation, $limites_prestations);
        $message = $resultat['message'];
        $type = $resultat['ok'] ? 'success' : 'danger';
    }

    // Retirer une prestation d'un parrainage
    if ($action === 'retirer_prestation') {
        $id_parrainage = (int)($_POST['id_parrainage'] ?? 0);
        $id_prestation = (int)($_POST['id_prestation'] ?? 0);

        $resultat = retirer_prestation_parrainage($conn, $id_parrainage, $id_prestation);
        $message = $resultat['message'];
        $type = $resultat['ok'] ? 'success' : 'danger';
    }

    // Ajouter une prestation au catalogue
    if ($action === 'ajouter_catalogue_prestation') {
        $nom_prestation = trim($_POST['nom_prestation'] ?? '');

        if ($nom_prestation !== '') {
            $id_prestation = get_next_id($conn, 'Prestation', 'id_prestation');

            $sql = "INSERT INTO Prestation(id_prestation, nom_prestation)
                    VALUES(:id, :nom)";
            $st = oci_parse($conn, $sql);
            oci_bind_by_name($st, ':id', $id_prestation);
            oci_bind_by_name($st, ':nom', $nom_prestation);
            $ok = oci_execute($st);
            oci_free_statement($st);

            $message = $ok ? 'Prestation ajoutée au catalogue.' : 'Erreur lors de l’ajout au catalogue.';
            $type = $ok ? 'success' : 'danger';
        }
    }

    // Supprimer une prestation du catalogue
    if ($action === 'supprimer_catalogue_prestation') {
        $id_prestation = (int)($_POST['id_prestation'] ?? 0);

        if ($id_prestation > 0) {
            // Supprime d'abord les liaisons
            $sql = "DELETE FROM Offrir WHERE id_prestation = :id";
            $st = oci_parse($conn, $sql);
            oci_bind_by_name($st, ':id', $id_prestation);
            oci_execute($st);
            oci_free_statement($st);

            // Puis supprime du catalogue
            $sql = "DELETE FROM Prestation WHERE id_prestation = :id";
            $st = oci_parse($conn, $sql);
            oci_bind_by_name($st, ':id', $id_prestation);
            $ok = oci_execute($st);
            oci_free_statement($st);

            $message = $ok ? 'Prestation supprimée du catalogue.' : 'Erreur lors de la suppression.';
            $type = $ok ? 'success' : 'danger';
        }
    }
}


/* =========================
   Chargement des données
========================= */

// Liste des visiteurs
$visiteurs = [];
$sql = "SELECT id_visiteur, nom_visiteur, prenom_visiteur, email_visiteur
        FROM Visiteur
        ORDER BY nom_visiteur, prenom_visiteur";
$st = oci_parse($conn, $sql);
if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) {
        $visiteurs[] = $row;
    }
    oci_free_statement($st);
}

// Liste des animaux
$animaux = [];
$sql = "SELECT rfid, nom_animal
        FROM Animal
        ORDER BY nom_animal";
$st = oci_parse($conn, $sql);
if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) {
        $animaux[] = $row;
    }
    oci_free_statement($st);
}

// Catalogue des prestations
$toutes_prest = [];
$sql = "SELECT id_prestation, nom_prestation
        FROM Prestation
        ORDER BY nom_prestation";
$st = oci_parse($conn, $sql);
if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) {
        $toutes_prest[] = $row;
    }
    oci_free_statement($st);
}

// Liste des parrainages
$pars = [];
$sql = "SELECT
            p.id_parrainage,
            p.date_debut_parrainage,
            p.date_fin_parrainage,
            p.niveau,
            v.id_visiteur,
            v.nom_visiteur,
            v.prenom_visiteur,
            v.email_visiteur,
            a.nom_animal,
            e.nom_usuel
        FROM Parrainage p
        LEFT JOIN Visiteur v ON p.id_visiteur = v.id_visiteur
        LEFT JOIN Animal a ON p.rfid = a.rfid
        LEFT JOIN Espece e ON a.id_espece = e.id_espece
        ORDER BY p.date_debut_parrainage DESC";
$st = oci_parse($conn, $sql);
if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) {
        $pars[] = $row;
    }
    oci_free_statement($st);
}

// Prestations par parrainage
$off_by_parr = [];
$sql = "SELECT
            o.id_parrainage,
            o.id_prestation,
            p.nom_prestation
        FROM Offrir o
        JOIN Prestation p ON o.id_prestation = p.id_prestation
        ORDER BY p.nom_prestation";
$st = oci_parse($conn, $sql);
if ($st && oci_execute($st)) {
    while ($row = oci_fetch_assoc($st)) {
        $off_by_parr[$row['ID_PARRAINAGE']][] = $row;
    }
    oci_free_statement($st);
}


// Ferme la connexion
oci_close($conn);


// Nombre de parrainages actifs
$nb_actifs = count(array_filter($pars, function ($p) {
    return empty($p['DATE_FIN_PARRAINAGE']) || strtotime((string)$p['DATE_FIN_PARRAINAGE']) >= time();
}));


// Configuration page
$page_title = 'Parrainages';
$page_css = '/assets/css/parrainages.css';
$page_hero = [
    'kicker' => 'Parrainages & Prestations',
    'icon'   => 'bi bi-heart-fill',
    'title'  => 'Parrainages & Visiteurs',
    'desc'   => 'Gérez les parrainages, les visiteurs et les prestations du parc.',
    'image'  => url_site('/assets/img/sponsorship-hero.svg'),
    'actions' => array_filter([
        $peut_modifier ? ['label' => 'Ajouter un parrainage', 'icon' => 'bi bi-plus-lg', 'target' => '#mParr', 'class' => 'btn-primary'] : null,
        $peut_modifier ? ['label' => 'Ajouter un visiteur', 'icon' => 'bi bi-person-plus-fill', 'target' => '#mVisiteur', 'class' => 'btn-light-surface'] : null,
        $peut_modifier ? ['label' => 'Gérer le catalogue', 'icon' => 'bi bi-gift-fill', 'target' => '#mCataloguePrestations', 'class' => 'btn-light-surface'] : null,
        ['label' => 'Dashboard', 'icon' => 'bi bi-arrow-left', 'href' => url_site('/index.php'), 'class' => 'btn-ghost'],
    ]),
    'stats' => [
        ['value' => count($pars), 'label' => 'parrainages'],
        ['value' => $nb_actifs, 'label' => 'actifs'],
        ['value' => count($visiteurs), 'label' => 'visiteurs'],
        ['value' => count($toutes_prest), 'label' => 'prestations disponibles'],
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
  <div class="app-sidebar-col"><?php include '../includes/sidebar.php'; ?></div>

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
                <?php foreach ($heroActions as $action): ?>
                  <?php if (empty($action)) continue; ?>
                  <?php $class = $action['class'] ?? 'btn-primary'; ?>

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

      <!-- Message -->
      <?php render_alert($message, $type); ?>

      <!-- Barre de recherche -->
      <div class="search-toolbar reveal mb-4">
        <div class="search-box">
          <i class="bi bi-search"></i>
          <input
            type="search"
            id="rechercheParrainages"
            class="search-input"
            placeholder="Rechercher un visiteur, un animal ou un niveau..."
          >
        </div>

        <div>
          <select id="filtreNiveauParrainage" class="form-select search-select">
            <option value="">Tous les niveaux</option>
            <?php
            $niveaux_uniques = array_values(array_unique(array_filter(array_map(function($p) {
                return $p['NIVEAU'] ?? '';
            }, $pars))));
            foreach ($niveaux_uniques as $niv):
            ?>
              <option value="<?php echo htmlspecialchars(strtolower($niv)); ?>">
                <?php echo htmlspecialchars($niv); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="row g-4">

        <!-- Colonne gauche : parrainages -->
        <div class="col-lg-8">
          <div class="section-card reveal">
            <div class="page-header-row">
              <div>
                <div class="overline">Contrats actifs & passés</div>
                <h2 class="page-title-sm">Parrainages</h2>
              </div>
              <span class="badge-soft badge-emerald">
                <?php echo $nb_actifs; ?> actif<?php echo $nb_actifs > 1 ? 's' : ''; ?>
              </span>
            </div>

            <div class="sponsor-grid" data-filter-group="sponsors">
              <?php if (!empty($pars)): ?>
                <?php foreach ($pars as $p): ?>
                  <?php
                  $id_parrainage = (int)$p['ID_PARRAINAGE'];
                  $niveau_brut = strtolower(trim($p['NIVEAU'] ?? ''));
                  $actif = empty($p['DATE_FIN_PARRAINAGE']) || strtotime((string)$p['DATE_FIN_PARRAINAGE']) >= time();

                  $badge_class = match ($niveau_brut) {
                      'or', 'platine' => 'badge-amber',
                      'argent'        => 'badge-violet',
                      default         => 'badge-sky'
                  };

                  $prestations_actuelles = $off_by_parr[$id_parrainage] ?? [];
                  $prestations_ids = array_map(function($item) {
                      return (int)$item['ID_PRESTATION'];
                  }, $prestations_actuelles);

                  $prestations_disponibles = array_values(array_filter($toutes_prest, function($item) use ($prestations_ids) {
                      return !in_array((int)$item['ID_PRESTATION'], $prestations_ids, true);
                  }));

                  $search = strtolower(trim(
                      ($p['PRENOM_VISITEUR'] ?? '') . ' ' .
                      ($p['NOM_VISITEUR'] ?? '') . ' ' .
                      ($p['NOM_ANIMAL'] ?? '') . ' ' .
                      ($p['NIVEAU'] ?? '')
                  ));
                  ?>

                  <article
                    class="sponsor-card reveal"
                    data-filter-item
                    data-niveau="<?php echo htmlspecialchars($niveau_brut); ?>"
                    data-search="<?php echo htmlspecialchars($search); ?>"
                  >
                    <div class="item-top">
                      <div>
                        <div class="overline">Parrain</div>
                        <h3 class="item-title">
                          <?php
                          $nom_complet = trim(($p['PRENOM_VISITEUR'] ?? '') . ' ' . ($p['NOM_VISITEUR'] ?? ''));
                          echo htmlspecialchars($nom_complet !== '' ? $nom_complet : 'Visiteur');
                          ?>
                        </h3>
                        <div class="item-sub"><?php echo htmlspecialchars($p['EMAIL_VISITEUR'] ?? ''); ?></div>
                      </div>

                      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.35rem">
                        <span class="badge-soft <?php echo $badge_class; ?>">
                          <?php echo htmlspecialchars($p['NIVEAU'] ?? ''); ?>
                        </span>
                        <span class="badge-soft <?php echo $actif ? 'badge-emerald' : 'badge-rose'; ?>" style="font-size:.68rem">
                          <?php echo $actif ? 'Actif' : 'Expiré'; ?>
                        </span>
                      </div>
                    </div>

                    <div class="mini-list">
                      <div class="mini-row">
                        <span>Animal</span>
                        <strong><?php echo htmlspecialchars($p['NOM_ANIMAL'] ?? '—'); ?></strong>
                      </div>
                      <div class="mini-row">
                        <span>Espèce</span>
                        <strong><?php echo htmlspecialchars($p['NOM_USUEL'] ?? '—'); ?></strong>
                      </div>
                      <div class="mini-row">
                        <span>Début</span>
                        <strong><?php echo formater_date($p['DATE_DEBUT_PARRAINAGE']); ?></strong>
                      </div>
                      <div class="mini-row">
                        <span>Fin</span>
                        <strong><?php echo formater_date($p['DATE_FIN_PARRAINAGE']); ?></strong>
                      </div>
                    </div>

                    <!-- Prestations attribuées -->
                    <div style="margin-top:.88rem">
                      <div class="overline mb-2">
                        <i class="bi bi-gift-fill me-1" style="color:var(--amber)"></i>
                        Prestations incluses
                      </div>

                      <?php if (empty($prestations_actuelles)): ?>
                        <div style="font-size:.8rem;color:var(--txt-muted);font-style:italic">
                          Aucune prestation assignée
                        </div>
                      <?php else: ?>
                        <div style="display:flex;flex-wrap:wrap;gap:.4rem">
                          <?php foreach ($prestations_actuelles as $prestation): ?>
                            <span class="badge-soft badge-amber" style="font-size:.72rem">
                              <i class="bi bi-gift"></i>
                              <?php echo htmlspecialchars($prestation['NOM_PRESTATION']); ?>

                              <?php if ($peut_modifier): ?>
                                <form method="POST" style="display:inline;margin-left:.3rem">
                                  <input type="hidden" name="action" value="retirer_prestation">
                                  <input type="hidden" name="id_parrainage" value="<?php echo $id_parrainage; ?>">
                                  <input type="hidden" name="id_prestation" value="<?php echo (int)$prestation['ID_PRESTATION']; ?>">
                                  <button
                                    type="submit"
                                    style="background:none;border:none;padding:0;color:var(--rose);cursor:pointer;font-size:.75rem"
                                    onclick="return confirm('Retirer cette prestation ?')"
                                  >
                                    <i class="bi bi-x-circle-fill"></i>
                                  </button>
                                </form>
                              <?php endif; ?>
                            </span>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                    </div>

                    <!-- Ajout prestation -->
                    <?php if ($peut_modifier && !empty($prestations_disponibles)): ?>
                      <div style="margin-top:.72rem;padding-top:.72rem;border-top:1px solid var(--border)">
                        <div class="overline mb-2" style="font-size:.62rem">
                          <i class="bi bi-plus-circle me-1" style="color:var(--green)"></i>
                          Ajouter une prestation
                        </div>

                        <form method="POST" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
                          <input type="hidden" name="action" value="ajouter_prestation">
                          <input type="hidden" name="id_parrainage" value="<?php echo $id_parrainage; ?>">

                          <select
                            name="id_prestation"
                            class="form-select"
                            style="flex:1;min-width:160px;padding:.42rem .8rem;font-size:.82rem;border-radius:12px"
                            required
                          >
                            <option value="">— Choisir —</option>
                            <?php foreach ($prestations_disponibles as $prestation_dispo): ?>
                              <option value="<?php echo (int)$prestation_dispo['ID_PRESTATION']; ?>">
                                <?php echo htmlspecialchars($prestation_dispo['NOM_PRESTATION']); ?>
                              </option>
                            <?php endforeach; ?>
                          </select>

                          <button type="submit" class="btn btn-primary" style="font-size:.8rem;padding:.45rem .85rem">
                            <i class="bi bi-plus-lg"></i> Ajouter
                          </button>
                        </form>
                      </div>
                    <?php elseif ($peut_modifier && empty($prestations_disponibles) && !empty($toutes_prest)): ?>
                      <div style="margin-top:.72rem;padding-top:.72rem;border-top:1px solid var(--border);font-size:.78rem;color:var(--txt-muted)">
                        <i class="bi bi-check-circle-fill me-1" style="color:var(--green)"></i>
                        Aucune autre prestation disponible.
                      </div>
                    <?php endif; ?>
                  </article>
                <?php endforeach; ?>
              <?php else: ?>
                <div style="text-align:center;padding:3rem;color:var(--txt-muted)">
                  <i class="bi bi-heart" style="font-size:2.5rem;opacity:.3;display:block;margin-bottom:.75rem"></i>
                  <div style="font-weight:700">Aucun parrainage enregistré</div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Colonne droite -->
        <div class="col-lg-4">

          <!-- Visiteurs -->
          <div class="section-card reveal">
            <div class="page-header-row">
              <div>
                <div class="overline">Parrains</div>
                <h2 class="page-title-sm">Visiteurs</h2>
              </div>
              <span class="badge-soft badge-sky"><?php echo count($visiteurs); ?></span>
            </div>

            <div class="stack-list">
              <?php foreach ($visiteurs as $v): ?>
                <div
                  class="visitor-card"
                  style="background:rgba(255,255,255,.85);border:1px solid var(--border);border-radius:18px;padding:.88rem 1rem;transition:.18s"
                  onmouseover="this.style.borderColor='rgba(211,138,16,.28)'"
                  onmouseout="this.style.borderColor='rgba(81,63,35,.12)'"
                >
                  <div style="font-weight:900;font-size:.92rem">
                    <?php echo htmlspecialchars(trim(($v['PRENOM_VISITEUR'] ?? '') . ' ' . ($v['NOM_VISITEUR'] ?? ''))); ?>
                  </div>

                  <?php if (!empty($v['EMAIL_VISITEUR'])): ?>
                    <div class="text-muted mt-1" style="font-size:.8rem">
                      <i class="bi bi-envelope me-1"></i>
                      <?php echo htmlspecialchars($v['EMAIL_VISITEUR']); ?>
                    </div>
                  <?php endif; ?>

                  <?php if ($peut_modifier): ?>
                    <div class="action-row mt-2">
                      <button
                        class="btn btn-light-surface"
                        style="font-size:.8rem;padding:.42rem .88rem;color:var(--txt)"
                        data-bs-toggle="modal"
                        data-bs-target="#visitor<?php echo (int)$v['ID_VISITEUR']; ?>"
                      >
                        <i class="bi bi-pencil-fill"></i> Modifier
                      </button>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Catalogue prestations -->
          <div class="section-card reveal mt-4">
            <div class="overline mb-3">
              <i class="bi bi-gift-fill me-1" style="color:var(--amber)"></i>
              Prestations disponibles
            </div>

            <div style="display:flex;flex-direction:column;gap:.45rem">
              <?php foreach ($toutes_prest as $pr): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.52rem .72rem;background:rgba(255,255,255,.82);border:1px solid var(--border);border-radius:14px">
                  <div style="display:flex;align-items:center;gap:.6rem;min-width:0">
                    <i class="bi bi-gift" style="color:var(--amber);flex-shrink:0"></i>
                    <span style="font-size:.85rem;font-weight:700">
                      <?php echo htmlspecialchars($pr['NOM_PRESTATION']); ?>
                    </span>
                  </div>

                  <?php if ($peut_modifier): ?>
                    <form method="POST" onsubmit="return confirm('Supprimer cette prestation du catalogue ?');" style="margin:0">
                      <input type="hidden" name="action" value="supprimer_catalogue_prestation">
                      <input type="hidden" name="id_prestation" value="<?php echo (int)$pr['ID_PRESTATION']; ?>">
                      <button type="submit" class="btn btn-light-surface" style="padding:.38rem .7rem;font-size:.78rem;color:var(--rose)">
                        <i class="bi bi-trash3-fill"></i>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

        </div>
      </div>

      <?php if ($peut_modifier): ?>

        <!-- Modal ajouter visiteur -->
        <div class="modal fade" id="mVisiteur" tabindex="-1">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title fw-bold">Ajouter un visiteur</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
              </div>

              <div class="modal-body">
                <form method="POST">
                  <input type="hidden" name="action" value="ajouter_visiteur">

                  <div class="grid-auto">
                    <div>
                      <label class="form-label">Nom</label>
                      <input class="form-control" name="nom_visiteur" required>
                    </div>

                    <div>
                      <label class="form-label">Prénom</label>
                      <input class="form-control" name="prenom_visiteur" required>
                    </div>

                    <div style="grid-column:1/-1">
                      <label class="form-label">Email</label>
                      <input class="form-control" type="email" name="email_visiteur">
                    </div>
                  </div>

                  <div class="action-row justify-content-end">
                    <button type="button" class="btn btn-light-surface" data-bs-dismiss="modal">Annuler</button>
                    <button class="btn btn-primary">Ajouter</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>

        <!-- Modals modifier visiteur -->
        <?php foreach ($visiteurs as $v): ?>
          <div class="modal fade" id="visitor<?php echo (int)$v['ID_VISITEUR']; ?>" tabindex="-1">
            <div class="modal-dialog">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title fw-bold">Modifier le visiteur</h5>
                  <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                  <form method="POST">
                    <input type="hidden" name="action" value="modifier_visiteur">
                    <input type="hidden" name="id_visiteur" value="<?php echo (int)$v['ID_VISITEUR']; ?>">

                    <div class="grid-auto">
                      <div>
                        <label class="form-label">Nom</label>
                        <input class="form-control" name="nom_visiteur" value="<?php echo htmlspecialchars($v['NOM_VISITEUR']); ?>" required>
                      </div>

                      <div>
                        <label class="form-label">Prénom</label>
                        <input class="form-control" name="prenom_visiteur" value="<?php echo htmlspecialchars($v['PRENOM_VISITEUR']); ?>" required>
                      </div>

                      <div style="grid-column:1/-1">
                        <label class="form-label">Email</label>
                        <input class="form-control" type="email" name="email_visiteur" value="<?php echo htmlspecialchars($v['EMAIL_VISITEUR'] ?? ''); ?>">
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
        <?php endforeach; ?>

        <!-- Modal ajouter parrainage -->
        <div class="modal fade" id="mParr" tabindex="-1">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title fw-bold">Ajouter un parrainage</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
              </div>

              <div class="modal-body">
                <form method="POST">
                  <input type="hidden" name="action" value="ajouter_parrainage">

                  <div class="grid-auto">
                    <div>
                      <label class="form-label">Animal</label>
                      <select class="form-select" name="rfid" required>
                        <option value="">— Choisir —</option>
                        <?php foreach ($animaux as $a): ?>
                          <option value="<?php echo htmlspecialchars($a['RFID']); ?>">
                            <?php echo htmlspecialchars($a['NOM_ANIMAL'] ?? $a['RFID']); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <div>
                      <label class="form-label">Visiteur</label>
                      <select class="form-select" name="id_visiteur" required>
                        <option value="">— Choisir —</option>
                        <?php foreach ($visiteurs as $v): ?>
                          <option value="<?php echo (int)$v['ID_VISITEUR']; ?>">
                            <?php echo htmlspecialchars(($v['PRENOM_VISITEUR'] ?? '') . ' ' . ($v['NOM_VISITEUR'] ?? '')); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <div>
                      <label class="form-label">Niveau</label>
                      <select class="form-select" name="niveau">
                        <option>Bronze</option>
                        <option>Argent</option>
                        <option selected>Or</option>
                      </select>
                    </div>

                    <div>
                      <label class="form-label">Date de début</label>
                      <input class="form-control" type="date" name="date_debut" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div>
                      <label class="form-label">Date de fin</label>
                      <input class="form-control" type="date" name="date_fin">
                    </div>
                  </div>

                  <div class="action-row justify-content-end">
                    <button type="button" class="btn btn-light-surface" data-bs-dismiss="modal">Annuler</button>
                    <button class="btn btn-primary">Ajouter</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal ajouter prestation catalogue -->
        <div class="modal fade" id="mCataloguePrestations" tabindex="-1">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title fw-bold">Ajouter une prestation au catalogue</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
              </div>

              <div class="modal-body">
                <form method="POST">
                  <input type="hidden" name="action" value="ajouter_catalogue_prestation">

                  <label class="form-label">Nom de la prestation</label>
                  <input class="form-control" type="text" name="nom_prestation" placeholder="Ex. Rencontre soigneur" required>

                  <div class="action-row justify-content-end">
                    <button type="button" class="btn btn-light-surface" data-bs-dismiss="modal">Annuler</button>
                    <button class="btn btn-primary">
                      <i class="bi bi-plus-lg"></i> Ajouter
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
// Recherche et filtre
(function () {
  var recherche = document.getElementById('rechercheParrainages');
  var filtre = document.getElementById('filtreNiveauParrainage');

  function appliquerFiltres() {
    var texteRecherche = (recherche.value || '').toLowerCase();
    var niveau = (filtre.value || '').toLowerCase();

    document.querySelectorAll('.sponsor-grid [data-filter-item]').forEach(function (card) {
      var texteCarte = (card.dataset.search || '').toLowerCase();
      var niveauCarte = (card.dataset.niveau || '').toLowerCase();

      var visible = texteCarte.indexOf(texteRecherche) !== -1;

      if (visible && niveau !== '' && niveauCarte !== niveau) {
        visible = false;
      }

      card.style.display = visible ? '' : 'none';
    });
  }

  if (recherche) {
    recherche.addEventListener('input', appliquerFiltres);
  }

  if (filtre) {
    filtre.addEventListener('change', appliquerFiltres);
  }
})();

// Animation apparition
document.querySelectorAll('.reveal').forEach(function (el, i) {
  setTimeout(function () {
    el.classList.add('visible');
  }, 80 + i * 35);
});
</script>
</body>
</html>
