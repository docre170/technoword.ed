<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('serialize_precision', '-1');

require __DIR__ . '/src/functions.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? '';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? $path : '/';

try {
    if ($path === '/cours/recherche') {
        if ($method !== 'GET') {
            repondre_erreur(405, 'Méthode non autorisée.');
        }

        rechercher_cours($_GET);
    }

    if ($path === '/cours') {
        if ($method === 'POST') {
            creer_cours();
        }

        if ($method === 'GET') {
            lister_cours($_GET);
        }

        repondre_erreur(405, 'Méthode non autorisée.');
    }

    if (preg_match('~^/cours/([^/]+)/moyenne$~', $path, $correspondance) === 1) {
        if ($method !== 'GET') {
            repondre_erreur(405, 'Méthode non autorisée.');
        }

        $idTexte = $correspondance[1];
        $idNormalise = ltrim($idTexte, '0');
        $id = filter_var($idNormalise === '' ? '0' : $idNormalise, FILTER_VALIDATE_INT);
        if (!ctype_digit($idTexte) || $id === false) {
            repondre_erreur(400, 'Identifiant invalide.');
        }

        calculer_moyenne((int) $id);
    }

    repondre_erreur(404, 'Route introuvable.');
} catch (Throwable $erreur) {
    repondre_erreur(500, 'Erreur interne.');
}
