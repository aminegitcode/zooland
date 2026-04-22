<?php
session_start();

require_once 'includes/auth.php';

if (isset($_SESSION['id_personnel'])) {
    header('Location: index.php');
    exit;
}

$erreur = '';
$login_val = '';

/* =========================
   Connexion + statistiques
========================= */
require_once 'config.php';

function lire_nombre($conn, string $sql): int
{
    $st = oci_parse($conn, $sql);
    if (!$st) {
        return 0;
    }

    $ok = oci_execute($st);
    if (!$ok) {
        oci_free_statement($st);
        return 0;
    }

    $row = oci_fetch_array($st, OCI_NUM);
    oci_free_statement($st);

    return (int)($row[0] ?? 0);
}

$nb_especes = lire_nombre($conn, "SELECT COUNT(*) FROM Espece");
$nb_animaux = lire_nombre($conn, "SELECT COUNT(*) FROM Animal");
$nb_zones   = lire_nombre($conn, "SELECT COUNT(*) FROM Zone");

/* =========================
   Traitement connexion
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_val   = trim($_POST['login'] ?? '');
    $mdp         = $_POST['mot_de_passe'] ?? '';
    $login_saisi = strtolower($login_val);

    $comptes_speciaux = [
        'admin'    => 'admin123',
        'soigneur' => 'soigneur123',
        'boutique' => 'boutique123'
    ];

    if (isset($comptes_speciaux[$login_saisi])) {
        if ($mdp === $comptes_speciaux[$login_saisi]) {
            $_SESSION['id_personnel'] = 0;
            $_SESSION['nom']          = ucfirst($login_saisi);
            $_SESSION['prenom']       = ucfirst($login_saisi);
            $_SESSION['role']         = ucfirst($login_saisi);
            $_SESSION['role_label']   = ucfirst($login_saisi);

            oci_close($conn);
            header('Location: index.php');
            exit;
        } else {
            $erreur = "Login ou mot de passe incorrect.";
        }
    } else {
        $stid = oci_parse(
            $conn,
            "SELECT p.id_personnel,
                    p.nom_personnel,
                    p.prenom_personnel,
                    p.mot_de_passe,
                    r.nom_role
             FROM Personnel p
             JOIN Historique_emploi h ON p.id_personnel = h.id_personnel
             JOIN Role r ON h.id_role = r.id_role
             WHERE h.date_fin IS NULL"
        );

        if (!$stid) {
            $e = oci_error($conn);
            trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
        }

        $r = oci_execute($stid);
        if (!$r) {
            $e = oci_error($stid);
            trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
        }

        $row = null;

        while ($ligne = oci_fetch_assoc($stid)) {
            $prenom = strtolower($ligne['PRENOM_PERSONNEL']);
            $nom = strtolower($ligne['NOM_PERSONNEL']);
            $login_complet = $prenom . '.' . $nom;

            if ($login_saisi === $login_complet) {
                $row = $ligne;
                break;
            }
        }

        oci_free_statement($stid);

        if ($row && password_verify($mdp, $row['MOT_DE_PASSE'])) {
            $_SESSION['id_personnel'] = $row['ID_PERSONNEL'];
            $_SESSION['nom']          = $row['NOM_PERSONNEL'];
            $_SESSION['prenom']       = $row['PRENOM_PERSONNEL'];
            $_SESSION['role_label']   = $row['NOM_ROLE'];
            $_SESSION['role']         = normaliser_role($row['NOM_ROLE']);

            oci_close($conn);
            header('Location: index.php');
            exit;
        } else {
            $erreur = "Login ou mot de passe incorrect.";
        }
    }
}

oci_close($conn);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Zoo de Babentruk</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,200..1000;1,200..1000&display=swap"
        rel="stylesheet">

    <link href="assets/css/global.css" rel="stylesheet">
    <link href="assets/css/login.css" rel="stylesheet">
</head>

<body>

    <div class="container-fluid p-0">
        <div class="row g-0 min-vh-100">

            <div class="col-lg-6 left-panel d-none d-lg-flex">
                <div class="left-inner">
                    <div class="hero-logo-box">
                        <img src="assets/pawprint.svg" alt="Logo du zoo">
                        <span class="hero-logo-leaf">🍃</span>
                    </div>

                    <h1 class="hero-title extra-bold">
                        Zoo de <span class="hero-title-accent">Babentruk</span>
                    </h1>

                    <p class="hero-subtitle">
                        Système de gestion intégré pour la préservation, le suivi des pensionnaires,
                        l’organisation du personnel et la supervision des zones du parc.
                    </p>

                    <div class="hero-stats">
                        <div class="hero-stat-card text-center">
                            <div class="hero-stat-icon">🦓</div>
                            <div class="hero-stat-value"><?php echo $nb_especes; ?></div>
                            <div class="hero-stat-label">Espèces</div>
                        </div>

                        <div class="hero-stat-card text-center">
                            <div class="hero-stat-icon">🐾</div>
                            <div class="hero-stat-value"><?php echo $nb_animaux; ?></div>
                            <div class="hero-stat-label">Animaux</div>
                        </div>

                        <div class="hero-stat-card text-center">
                            <div class="hero-stat-icon">🏕️</div>
                            <div class="hero-stat-value"><?php echo $nb_zones; ?></div>
                            <div class="hero-stat-label">Zones</div>
                        </div>
                    </div>

                    <div class="hero-note">
                        <span>🌿</span>
                        <span>Préservation · Conservation · Excellence</span>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 d-flex align-items-center justify-content-center login-right-panel">
                <div class="login-form-wrapper px-3 px-md-4">
                    <div class="card login-card p-4 p-md-5">
                        <div class="text-center mb-4">
                            <h2 class="fs-2 extra-bold mb-2">Connexion 🦎</h2>
                            <p class="text-muted mb-0">Accédez à votre espace de gestion</p>
                        </div>

                        <?php if (!empty($erreur)) : ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2 rounded-3" role="alert">
                            <i class="bi bi-exclamation-circle-fill"></i>
                            <span><?php echo htmlspecialchars($erreur); ?></span>
                        </div>
                        <?php endif; ?>

                        <form method="POST" action="login.php" id="loginForm">
                            <div class="mb-3">
                                <label for="login" class="form-label fw-semibold">Login</label>
                                <div class="input-group rounded-input px-2">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" id="login" name="login" class="form-control"
                                        placeholder="Votre identifiant"
                                        value="<?php echo htmlspecialchars($login_val); ?>" required autofocus>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="mot_de_passe" class="form-label fw-semibold">Mot de passe</label>
                                <div class="input-group rounded-input px-2">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" id="mot_de_passe" name="mot_de_passe" class="form-control"
                                        placeholder="••••••••" required>
                                    <button type="button" class="show-pass" onclick="togglePassword()">
                                        <i class="bi bi-eye" id="eyeIcon"></i>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" class="btn-primary2 w-100" id="submitBtn">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="spinner"></span>
                                <span id="btnText">Se connecter 🐾</span>
                            </button>
                        </form>
                    </div>

                    <div class="login-card card mt-3 px-4 py-3">
                        <p class="fw-bold mb-3 d-flex align-items-center gap-2 login-demo-title">
                            🎫 Comptes de démonstration
                        </p>

                        <div class="d-flex flex-column gap-2">
                            <div class="demo-item d-flex justify-content-between align-items-center px-3"
                                onclick="fillLogin('admin')">
                                <span class="fw-semibold d-flex align-items-center gap-2 login-demo-label">👑
                                    Admin</span>
                                <span class="font-monospace login-demo-login">admin</span>
                            </div>

                            <div class="demo-item d-flex justify-content-between align-items-center px-3"
                                onclick="fillLogin('soigneur')">
                                <span class="fw-semibold d-flex align-items-center gap-2 login-demo-label">🩺
                                    Soigneur</span>
                                <span class="font-monospace login-demo-login">soigneur</span>
                            </div>

                            <div class="demo-item d-flex justify-content-between align-items-center px-3"
                                onclick="fillLogin('boutique')">
                                <span class="fw-semibold d-flex align-items-center gap-2 login-demo-label">🛒
                                    Boutique</span>
                                <span class="font-monospace login-demo-login">boutique</span>
                            </div>
                        </div>

                        <p class="text-muted text-center mt-3 mb-0 login-demo-passwords">
                            Mot de passe :
                            <span class="font-monospace fw-semibold">admin123</span>,
                            <span class="font-monospace fw-semibold">soigneur123</span>,
                            <span class="font-monospace fw-semibold">boutique123</span>
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const input = document.getElementById('mot_de_passe');
            const icon = document.getElementById('eyeIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'bi bi-eye';
            }
        }

        function fillLogin(login) {
            document.getElementById('login').value = login;
            document.getElementById('mot_de_passe').value = login + '123';
        }
        document.getElementById('loginForm').addEventListener('submit', function() {
            document.getElementById('spinner').classList.remove('d-none');
            document.getElementById('btnText').textContent = 'Connexion...';
            document.getElementById('submitBtn').disabled = true;
        });
    </script>

</body>

</html>