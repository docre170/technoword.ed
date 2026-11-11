<?php
// Initialise la session, la connexion et les fonctions communes de sécurité.
$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
]);
session_start();

$pdo = require __DIR__ . '/../config/database.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function app_url(string $path): string
{
    $scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    if (substr($scriptDirectory, -17) === '/pages/enseignant') {
        $scriptDirectory = substr($scriptDirectory, 0, -17);
    } elseif (substr($scriptDirectory, -6) === '/pages') {
        $scriptDirectory = substr($scriptDirectory, 0, -6);
    }

    $basePath = rtrim($scriptDirectory, '/');
    return $basePath . '/' . ltrim($path, '/');
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf_token(): void
{
    $submittedToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (!is_string($submittedToken) || !is_string($sessionToken)
        || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        http_response_code(400);
        exit('Jeton CSRF invalide. Rechargez la page et réessayez.');
    }
}

function current_user(): ?array
{
    global $pdo;

    if (!isset($_SESSION['user_id']) || !is_int($_SESSION['user_id'])) {
        return null;
    }

    $statement = $pdo->prepare(
        'SELECT id, nom, email, role FROM utilisateur WHERE id = :id'
    );
    $statement->execute(['id' => $_SESSION['user_id']]);
    $user = $statement->fetch();

    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }

    return $user;
}

function require_role(string ...$allowedRoles): array
{
    $user = current_user();

    if ($user === null) {
        redirect(app_url('pages/login.php'));
    }

    if (!in_array($user['role'], $allowedRoles, true)) {
        http_response_code(403);
        exit('Accès refusé.');
    }

    return $user;
}
