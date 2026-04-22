<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'path.php';
function verifier_connexion() {
    if (!isset($_SESSION['id_personnel'])) {
        header('Location: ' . url_site('/login.php'));
        exit;
    }
}
function get_role() { return strtolower(trim($_SESSION['role'] ?? '')); }
function get_role_affiche() { return $_SESSION['role_label'] ?? ''; }
function verifier_role(array $roles_autorises) {
    verifier_connexion();
    $role_actuel = get_role();
    if (!in_array($role_actuel, $roles_autorises, true)) {
        header('Location: ' . url_site('/index.php'));
        exit;
    }
}
function require_role(array $roles_autorises) { verifier_role($roles_autorises); }
function a_le_droit(array $roles_autorises) { return in_array(get_role(), $roles_autorises, true); }
function normaliser_role(string $nom_role): string
{
    $nom_role = strtolower(trim($nom_role));
    return match ($nom_role) {
        'administrateur'       => 'admin',
        'directeur'            => 'dirigeant',
        'soigneur chef'        => 'soigneur_chef',
        'soigneur'             => 'soigneur',
        'veterinaire'          => 'veterinaire',
        'personnel technique'  => 'technicien',
        'personnel entretien'  => 'entretien',
        'responsable boutique' => 'boutique',
        'vendeur'              => 'vendeur',
        'comptable'            => 'comptable',
        default                => 'inconnu',
    };
}
?>