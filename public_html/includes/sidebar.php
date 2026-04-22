<?php
require_once 'auth.php';
require_once 'path.php';

$menu = [
  ['label' => 'Dashboard',      'icon' => 'bi bi-grid-1x2-fill',    'url' => '/index.php',             'roles' => ['admin','dirigeant','soigneur','soigneur_chef','veterinaire','technicien','entretien','boutique','vendeur','comptable']],
  ['label' => 'Animaux',        'icon' => 'bi bi-heart-pulse-fill', 'url' => '/animaux/index.php',     'roles' => ['admin','dirigeant','soigneur','soigneur_chef','veterinaire']],
  ['label' => 'Espèces',        'icon' => 'bi bi-diagram-3-fill',   'url' => '/especes/index.php',     'roles' => ['admin','dirigeant','soigneur','soigneur_chef','veterinaire']],
  ['label' => 'Enclos',        'icon' => 'bi bi-geo-alt-fill',     'url' => '/enclos/index.php',      'roles' => ['admin','dirigeant','soigneur','soigneur_chef','technicien']],
  ['label' => 'Zones',         'icon' => 'bi bi-map-fill',         'url' => '/zones/index.php',       'roles' => ['admin','dirigeant','soigneur','soigneur_chef','technicien']],
  ['label' => 'Soins',          'icon' => 'bi bi-bandaid-fill',     'url' => '/soins/index.php',       'roles' => ['admin','dirigeant','soigneur','soigneur_chef','veterinaire']],
  ['label' => 'Personnel',      'icon' => 'bi bi-people-fill',      'url' => '/personnel/index.php',   'roles' => ['admin','dirigeant','comptable']],
  ['label' => 'Boutiques',      'icon' => 'bi bi-shop',             'url' => '/boutiques/index.php',   'roles' => ['admin','dirigeant','boutique','vendeur','comptable']],
  ['label' => 'Parrainages',    'icon' => 'bi bi-heart-fill',       'url' => '/parrainages/index.php', 'roles' => ['admin','dirigeant','comptable']],
  ['label' => 'Réparations',    'icon' => 'bi bi-tools',            'url' => '/reparations/index.php', 'roles' => ['admin','dirigeant','technicien']],
];

$page_actuelle = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$role          = get_role();
$prenom        = $_SESSION['prenom'] ?? '';
$nom           = $_SESSION['nom']    ?? '';
$initiales     = strtoupper(mb_substr($prenom, 0, 1) . mb_substr($nom, 0, 1));

// Couleur avatar déterministe
$couleurs = ['#16a34a','#0284c7','#d97706','#e11d48','#7c3aed','#0d9488'];
$couleur_av = $couleurs[abs(crc32($prenom . $nom)) % count($couleurs)];

/**
 * Détection page active.
 * RÈGLE : Dashboard actif UNIQUEMENT sur /index.php exact.
 * Autres : actif si le chemin commence par le répertoire de la section.
 */
function est_actif(string $page, string $url): bool {
    $complet = url_site($url);
    // Dashboard : correspondance exacte seulement
    if ($url === '/index.php') {
        return $page === $complet;
    }
    // Autres sections : correspondance du répertoire
    $rep = rtrim(dirname($complet), '/');
    return $page === $complet
        || $page === $rep
        || $page === $rep . '/'
        || (str_starts_with($page, $rep . '/') && strlen($rep) > strlen(url_site('')));
}
?>
<aside class="sidebar">

  <!-- ── Marque ── -->
  <div class="sb-brand">
    <div class="sb-logo-box">
      <img src="<?php echo htmlspecialchars(url_site('/assets/pawprint.svg')); ?>" alt="Zoo">
    </div>
    <div>
      <div class="sb-brand-name">Zoo'land</div>
      <div class="sb-brand-sub">Suite de gestion</div>
    </div>
  </div>

  <!-- ── Utilisateur ── -->
  <div style="padding: 0 0rem; margin-top: 0.82rem;">
    <div class="sb-user">
      <div class="sb-avatar" style="background: <?php echo $couleur_av; ?>;">
        <?php echo htmlspecialchars($initiales ?: '??'); ?>
      </div>
      <div style="flex: 1; min-width: 0;">
        <div class="sb-user-name"><?php echo htmlspecialchars(trim($prenom . ' ' . $nom)); ?></div>
        <div style="display: flex; align-items: center; gap: 0.3rem; margin-top: 0.15rem;">
          <span class="sb-online"></span>
          <span class="sb-user-role"><?php echo htmlspecialchars(get_role_affiche()); ?></span>
        </div>
      </div>
    </div>
  </div>



  <!-- ── Navigation ── -->
  <nav class="sb-nav" style="margin-top: 0.22rem;">
    <div class="sb-section-label">Navigation</div>

    <?php foreach ($menu as $item): ?>
      <?php if (!in_array($role, $item['roles'], true)) continue; ?>
      <?php $actif = est_actif($page_actuelle, $item['url']); ?>
      <a href="<?php echo htmlspecialchars(url_site($item['url'])); ?>"
         class="sb-link<?php echo $actif ? ' active' : ''; ?>">
        <span class="sb-link-icon">
          <i class="<?php echo htmlspecialchars($item['icon']); ?>"></i>
        </span>
        <?php echo htmlspecialchars($item['label']); ?>
      </a>
    <?php endforeach; ?>

    <div class="sb-section-label" style="margin-top: 0.35rem;">Compte</div>
    <a href="<?php echo htmlspecialchars(url_site('/parametres.php')); ?>"
       class="sb-link<?php echo est_actif($page_actuelle, '/parametres.php') ? ' active' : ''; ?>">
      <span class="sb-link-icon"><i class="bi bi-gear-fill"></i></span>
      Paramètres
    </a>
  </nav>

  <!-- ── Pied ── -->
  <div class="sb-footer">
    <a href="<?php echo htmlspecialchars(url_site('/logout.php')); ?>"
       class="sb-link sb-link-danger">
      <span class="sb-link-icon"><i class="bi bi-box-arrow-right"></i></span>
      Déconnexion
    </a>
  </div>

</aside>
