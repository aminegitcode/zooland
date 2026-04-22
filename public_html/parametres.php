<?php
require_once 'includes/auth.php'; verifier_connexion();
require_once 'includes/path.php';
require_once 'includes/helpers.php';
require_once 'config.php';

$message = ''; $type = 'success';

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='changer_mdp') {
    $ancien  = $_POST['ancien_mdp']  ?? '';
    $nouveau = $_POST['nouveau_mdp'] ?? '';
    $confirm = $_POST['confirm_mdp'] ?? '';
    $id_p    = (int)($_SESSION['id_personnel'] ?? 0);
    if ($nouveau !== $confirm) {
        $message = 'Les nouveaux mots de passe ne correspondent pas.'; $type = 'danger';
    } elseif (strlen($nouveau) < 6) {
        $message = 'Minimum 6 caractères requis.'; $type = 'danger';
    } elseif ($id_p > 0) {
        $st = oci_parse($conn,"SELECT mot_de_passe FROM Personnel WHERE id_personnel=:id");
        oci_bind_by_name($st,':id',$id_p); oci_execute($st); $row = oci_fetch_assoc($st); oci_free_statement($st);
        if ($row && password_verify($ancien, $row['MOT_DE_PASSE'])) {
            $hash = password_hash($nouveau, PASSWORD_BCRYPT);
            $st2  = oci_parse($conn,"UPDATE Personnel SET mot_de_passe=:h WHERE id_personnel=:id");
            oci_bind_by_name($st2,':h',$hash); oci_bind_by_name($st2,':id',$id_p);
            $ok = oci_execute($st2); oci_free_statement($st2);
            $message = $ok ? 'Mot de passe mis à jour !' : 'Erreur lors de la mise à jour.';
            $type    = $ok ? 'success' : 'danger';
        } else { $message = 'Ancien mot de passe incorrect.'; $type = 'danger'; }
    }
}
oci_close($conn);

$prenom = $_SESSION['prenom'] ?? '';
$nom    = $_SESSION['nom']    ?? '';
$role_l = get_role_affiche();

$page_title = 'Paramètres';
$page_css = '/assets/css/global.css';
$page_hero = [
    'kicker'  => 'Mon compte',
    'icon'    => 'bi bi-gear-fill',
    'title'   => 'Paramètres du compte',
    'desc'    => 'Gérez vos informations et votre mot de passe.',
    'image'   => url_site('/assets/img/personnel-hero.svg'),
    'actions' => [['label'=>'Dashboard','icon'=>'bi bi-arrow-left','href'=>url_site('/index.php'),'class'=>'btn-ghost']],
    'stats'   => [
        ['value'=>htmlspecialchars(trim($prenom.' '.$nom)),'label'=>'Utilisateur'],
        ['value'=>htmlspecialchars($role_l),'label'=>'Rôle actuel'],
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
  <link href="<?php echo htmlspecialchars(url_site('/assets/vendor/bootstrap/css/bootstrap.min.css')); ?>"
    rel="stylesheet">
  <link href="<?php echo htmlspecialchars(url_site('/assets/css/bootstrap-icons-local.css')); ?>" rel="stylesheet">
  <link
    href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400&display=swap"
    rel="stylesheet">
  <link href="<?php echo htmlspecialchars(url_site('/assets/css/global.css')); ?>" rel="stylesheet">
  <?php if (!empty($page_css)): ?>
  <link href="<?php echo htmlspecialchars(url_site($page_css)); ?>" rel="stylesheet">
  <?php endif; ?>
</head>

<body>
  <div class="d-flex app-layout">
    <div class="app-sidebar-col"><?php include 'includes/sidebar.php'; ?></div>
    <main class="app-content-col">
      <div class="page-padding">
        <section class="page-hero reveal parallax"
          style="--hero-img:url('<?php echo htmlspecialchars($heroImg, ENT_QUOTES); ?>')">
          <div class="hero-pill"><i class="<?php echo htmlspecialchars($heroIcon); ?>"></i>
            <?php echo htmlspecialchars($heroKicker); ?></div>
          <div class="hero-grid">
            <div class="hero-copy">
              <h1 class="hero-title"><?php echo $heroTitle; ?></h1>
              <?php if ($heroDesc): ?><p class="hero-desc"><?php echo htmlspecialchars($heroDesc); ?></p><?php endif; ?>
              <?php if ($heroActions): ?>
              <div class="hero-actions">
                <?php foreach ($heroActions as $action): if (!$action) continue; $class = $action['class'] ?? 'btn-primary'; ?>
                <?php if (!empty($action['href'])): ?>
                <a class="btn <?php echo htmlspecialchars($class); ?>"
                  href="<?php echo htmlspecialchars($action['href']); ?>">
                  <?php if (!empty($action['icon'])): ?><i class="<?php echo htmlspecialchars($action['icon']); ?>"></i>
                  <?php endif; ?>
                  <?php echo htmlspecialchars($action['label'] ?? ''); ?>
                </a>
                <?php else: ?>
                <button class="btn <?php echo htmlspecialchars($class); ?>" type="button"
                  <?php if (!empty($action['target'])): ?> data-bs-toggle="modal"
                  data-bs-target="<?php echo htmlspecialchars($action['target']); ?>" <?php endif; ?>>
                  <?php if (!empty($action['icon'])): ?><i class="<?php echo htmlspecialchars($action['icon']); ?>"></i>
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

        <?php
render_alert($message, $type);

$cols=['#16a34a','#0284c7','#d97706','#e11d48','#7c3aed','#0d9488'];
$col = $cols[abs(crc32($prenom.$nom)) % count($cols)];
$ini = strtoupper(mb_substr($prenom,0,1).mb_substr($nom,0,1));
?>
        <div class="row g-4">

          <!-- Profil -->
          <div class="col-lg-4">
            <div class="section-card reveal">
              <div class="overline mb-3">Profil</div>
              <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.35rem">
                <div
                  style="width:56px;height:56px;border-radius:18px;background:<?php echo $col;?>;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:1.35rem;color:#fff;flex-shrink:0">
                  <?php echo htmlspecialchars($ini ?: '?'); ?>
                </div>
                <div>
                  <div style="font-size:1.05rem;font-weight:900"><?php echo htmlspecialchars(trim($prenom.' '.$nom));?>
                  </div>
                  <div class="text-muted" style="font-size:.84rem"><?php echo htmlspecialchars($role_l);?></div>
                </div>
              </div>
              <div class="surface-subtle">
                <div class="mini-row" style="padding:.5rem 0;border-bottom:1px solid var(--border)"><span
                    class="text-muted"><i
                      class="bi bi-person-fill me-1"></i>Prénom</span><strong><?php echo htmlspecialchars($prenom);?></strong>
                </div>
                <div class="mini-row" style="padding:.5rem 0;border-bottom:1px solid var(--border)"><span
                    class="text-muted"><i
                      class="bi bi-person-fill me-1"></i>Nom</span><strong><?php echo htmlspecialchars($nom);?></strong>
                </div>
                <div class="mini-row" style="padding:.5rem 0"><span class="text-muted"><i
                      class="bi bi-shield-fill me-1"></i>Rôle</span><strong><?php echo htmlspecialchars($role_l);?></strong>
                </div>
              </div>
            </div>
          </div>

          <!-- Changer mot de passe -->
          <div class="col-lg-8">
            <div class="section-card reveal">
              <div class="page-header-row mb-3">
                <div>
                  <div class="overline">Sécurité</div>
                  <h2 class="page-title-sm">Changer le mot de passe</h2>
                </div>
                <span class="badge-soft badge-amber"><i class="bi bi-lock-fill me-1"></i>Sécurisé</span>
              </div>
              <form method="POST" style="max-width:440px">
                <input type="hidden" name="action" value="changer_mdp">

                <div class="mb-3">
                  <label class="form-label">Mot de passe actuel</label>
                  <div style="position:relative">
                    <input type="password" id="f1" name="ancien_mdp" class="form-control"
                      placeholder="Votre mot de passe actuel" required autocomplete="current-password">
                    <button type="button" onclick="toggleV('f1',this)"
                      style="position:absolute;right:.85rem;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--txt-muted);cursor:pointer"><i
                        class="bi bi-eye"></i></button>
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label">Nouveau mot de passe</label>
                  <div style="position:relative">
                    <input type="password" id="f2" name="nouveau_mdp" class="form-control"
                      placeholder="Min. 6 caractères" required autocomplete="new-password"
                      oninput="checkStr(this.value);checkMatch()">
                    <button type="button" onclick="toggleV('f2',this)"
                      style="position:absolute;right:.85rem;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--txt-muted);cursor:pointer"><i
                        class="bi bi-eye"></i></button>
                  </div>
                  <div style="margin-top:.5rem">
                    <div style="height:5px;border-radius:999px;background:rgba(0,0,0,.08);overflow:hidden">
                      <div id="strBar"
                        style="height:100%;border-radius:999px;transition:width .3s,background .3s;width:0"></div>
                    </div>
                    <div id="strLbl" style="font-size:.75rem;font-weight:700;margin-top:.3rem;color:var(--txt-muted)">
                    </div>
                  </div>
                </div>

                <div class="mb-4">
                  <label class="form-label">Confirmer le nouveau mot de passe</label>
                  <input type="password" id="f3" name="confirm_mdp" class="form-control" placeholder="Répétez" required
                    oninput="checkMatch()">
                  <div id="matchLbl" style="font-size:.76rem;font-weight:700;margin-top:.35rem"></div>
                </div>

                <div class="surface-subtle mb-4" style="font-size:.82rem;color:var(--txt-muted)">
                  <strong style="color:var(--amber)"><i class="bi bi-info-circle-fill me-1"></i>Conseils</strong><br>
                  Au moins 6 caractères · Mélangez majuscules, chiffres et symboles
                </div>

                <button type="submit" class="btn btn-primary"><i class="bi bi-shield-lock-fill"></i> Mettre à
                  jour</button>
              </form>
            </div>
          </div>

        </div>

        <script>
          function toggleV(id, btn) {
            var i = document.getElementById(id);
            i.type = i.type === 'password' ? 'text' : 'password';
            btn.querySelector('i').className = i.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
          }

          function checkStr(v) {
            var b = document.getElementById('strBar'),
              l = document.getElementById('strLbl'),
              s = 0;
            if (v.length >= 6) s++;
            if (v.length >= 10) s++;
            if (/[A-Z]/.test(v)) s++;
            if (/[0-9]/.test(v)) s++;
            if (/[^A-Za-z0-9]/.test(v)) s++;
            var m = [{
              w: '0%',
              c: '',
              l: ''
            }, {
              w: '20%',
              c: '#e11d48',
              l: 'Très faible'
            }, {
              w: '40%',
              c: '#d97706',
              l: 'Faible'
            }, {
              w: '60%',
              c: '#d97706',
              l: 'Moyen'
            }, {
              w: '80%',
              c: '#0d9488',
              l: 'Fort'
            }, {
              w: '100%',
              c: '#2f855a',
              l: 'Très fort'
            }][Math.min(s, 5)];
            b.style.width = m.w;
            b.style.background = m.c;
            l.textContent = m.l;
            l.style.color = m.c;
          }

          function checkMatch() {
            var a = document.getElementById('f2').value,
              b = document.getElementById('f3').value,
              l = document.getElementById('matchLbl');
            if (!b) {
              l.textContent = '';
              return;
            }
            if (a === b) {
              l.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>Correspondent';
              l.style.color = '#2f855a';
            } else {
              l.innerHTML = '<i class="bi bi-x-circle-fill me-1"></i>Ne correspondent pas';
              l.style.color = '#e11d48';
            }
          }
        </script>
      </div>
    </main>
  </div>
  <script src="<?php echo htmlspecialchars(url_site('/assets/vendor/bootstrap/js/bootstrap.bundle.min.js')); ?>">
  </script>
  <script>
    document.querySelectorAll('.reveal').forEach(function(el, i) {
      setTimeout(function() {
        el.classList.add('visible');
      }, 80 + i * 35);
    });
    document.querySelectorAll('[data-toggle-extern]').forEach(function(box) {
      function sync() {
        var t = document.getElementById(box.dataset.toggleExtern);
        if (!t) return;
        document.querySelectorAll(box.dataset.target).forEach(function(el) {
          el.style.display = t.checked ? '' : 'none';
        });
        document.querySelectorAll(box.dataset.altTarget).forEach(function(el) {
          el.style.display = t.checked ? 'none' : '';
        });
      }
      var target = document.getElementById(box.dataset.toggleExtern);
      if (target) {
        target.addEventListener('change', sync);
        sync();
      }
    });
  </script>
</body>

</html>