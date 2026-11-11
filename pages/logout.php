<?php
// Termine la session uniquement après une requête POST protégée contre les CSRF.
require __DIR__ . '/../includes/bootstrap.php';
require_role('apprenant', 'enseignant', 'admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Méthode non autorisée.');
}

verify_csrf_token();
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $cookieParameters = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $cookieParameters['path'],
        'domain' => $cookieParameters['domain'],
        'secure' => $cookieParameters['secure'],
        'httponly' => $cookieParameters['httponly'],
        'samesite' => 'Lax',
    ]);
}

session_destroy();
redirect(app_url('pages/login.php'));
