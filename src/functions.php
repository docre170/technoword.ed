<?php
declare(strict_types=1);

/**
 * Renvoie une réponse JSON et termine la requête.
 */
function repondre_json(int $statut, array $corps): never
{
    http_response_code($statut);
    echo json_encode(
        $corps,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    exit;
}

/**
 * Renvoie une erreur au format commun de l'API.
 */
function repondre_erreur(int $statut, string $message): never
{
    repondre_json($statut, [
        'success' => false,
        'message' => $message,
    ]);
}

/**
 * Lit un fichier de données et vérifie qu'il contient une liste JSON.
 */
function lire_donnees(string $nom): array
{
    $chemin = __DIR__ . '/../data/' . $nom . '.json';
    $contenu = @file_get_contents($chemin);

    if ($contenu === false) {
        throw new RuntimeException('Fichier de données illisible.');
    }

    $donnees = json_decode($contenu, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($donnees) || !array_is_list($donnees)) {
        throw new UnexpectedValueException('Format de données invalide.');
    }

    return $donnees;
}

/**
 * Enregistre la liste des cours par remplacement atomique du fichier.
 */
function enregistrer_cours(array $cours): void
{
    $chemin = __DIR__ . '/../data/cours.json';
    $temporaire = tempnam(dirname($chemin), 'cours-');

    if ($temporaire === false) {
        throw new RuntimeException('Création du fichier temporaire impossible.');
    }

    try {
        $contenu = json_encode(
            $cours,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        if (file_put_contents($temporaire, $contenu) !== strlen($contenu)) {
            throw new RuntimeException('Écriture des cours impossible.');
        }

        if (!rename($temporaire, $chemin)) {
            throw new RuntimeException('Remplacement des cours impossible.');
        }
    } finally {
        if (is_file($temporaire)) {
            @unlink($temporaire);
        }
    }
}

/**
 * Vérifie les quatre champs obligatoires d'un nouveau cours.
 */
function valider_nouveau_cours(mixed $corps): array
{
    if (!is_array($corps) || array_is_list($corps)) {
        repondre_erreur(400, 'Corps JSON invalide.');
    }

    $champs = ['titre', 'description', 'categorie', 'auteur'];
    $cours = [];

    foreach ($champs as $champ) {
        if (!array_key_exists($champ, $corps)
            || !is_string($corps[$champ])
            || trim($corps[$champ]) === ''
            || mb_strlen($corps[$champ], 'UTF-8') > 100
        ) {
            repondre_erreur(400, 'Champs du cours invalides.');
        }

        $cours[$champ] = $corps[$champ];
    }

    return $cours;
}

/**
 * Ajoute un cours sous verrou exclusif afin de protéger les écritures concurrentes.
 */
function creer_cours(): never
{
    $corpsBrut = file_get_contents('php://input');
    if ($corpsBrut === false) {
        repondre_erreur(400, 'Corps JSON illisible.');
    }

    try {
        $corps = json_decode($corpsBrut, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        repondre_erreur(400, 'Corps JSON illisible.');
    }

    $nouveauCours = valider_nouveau_cours($corps);
    $cheminVerrou = __DIR__ . '/../data/cours.json.lock';
    $verrou = @fopen($cheminVerrou, 'c');

    if ($verrou === false) {
        throw new RuntimeException('Verrou des cours indisponible.');
    }

    try {
        if (!flock($verrou, LOCK_EX)) {
            throw new RuntimeException('Verrou des cours indisponible.');
        }

        $cours = lire_donnees('cours');
        foreach ($cours as $coursExistant) {
            if (($coursExistant['titre'] ?? null) === $nouveauCours['titre']) {
                repondre_erreur(409, 'Ce titre existe déjà.');
            }
        }

        $dernierCours = $cours === [] ? null : $cours[array_key_last($cours)];
        $nouveauCours['id'] = $dernierCours === null
            ? 1
            : ((int) $dernierCours['id'] + 1);
        $cours[] = $nouveauCours;

        enregistrer_cours($cours);
        repondre_json(201, [
            'success' => true,
            'data' => $nouveauCours,
        ]);
    } finally {
        flock($verrou, LOCK_UN);
        fclose($verrou);
    }
}

/**
 * Trie les cours par identifiant croissant.
 */
function trier_cours(array &$cours): void
{
    usort($cours, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);
}

/**
 * Renvoie une page de vingt cours.
 */
function lister_cours(array $parametres): never
{
    $page = 1;
    if (array_key_exists('page', $parametres)) {
        $valeurPage = $parametres['page'];
        $pageNormalisee = is_string($valeurPage) ? ltrim($valeurPage, '0') : '';
        if (!is_string($valeurPage)
            || !ctype_digit($valeurPage)
            || filter_var($pageNormalisee === '' ? '0' : $pageNormalisee, FILTER_VALIDATE_INT) === false
            || (int) ($pageNormalisee === '' ? '0' : $pageNormalisee) < 1
        ) {
            repondre_erreur(400, 'Page invalide.');
        }
        $page = (int) $pageNormalisee;
    }

    $cours = lire_donnees('cours');
    trier_cours($cours);
    $total = count($cours);
    $dernierePage = (int) ceil($total / 20);
    $elements = $page > $dernierePage
        ? []
        : array_slice($cours, ($page - 1) * 20, 20);

    repondre_json(200, [
        'success' => true,
        'data' => [
            'page' => $page,
            'par_page' => 20,
            'total' => $total,
            'items' => $elements,
        ],
    ]);
}

/**
 * Recherche un terme dans les titres et descriptions.
 */
function rechercher_cours(array $parametres): never
{
    if (!array_key_exists('q', $parametres)
        || !is_string($parametres['q'])
        || $parametres['q'] === ''
    ) {
        repondre_erreur(400, 'Le terme de recherche est obligatoire.');
    }

    $terme = mb_strtolower($parametres['q'], 'UTF-8');
    $cours = lire_donnees('cours');
    trier_cours($cours);

    $resultats = [];
    foreach ($cours as $element) {
        $titre = mb_strtolower($element['titre'], 'UTF-8');
        $description = mb_strtolower($element['description'], 'UTF-8');

        if (mb_strpos($titre, $terme, 0, 'UTF-8') !== false
            || mb_strpos($description, $terme, 0, 'UTF-8') !== false
        ) {
            $resultats[] = $element;
            if (count($resultats) === 50) {
                break;
            }
        }
    }

    repondre_json(200, [
        'success' => true,
        'data' => [
            'total' => count($resultats),
            'items' => $resultats,
        ],
    ]);
}

/**
 * Calcule la moyenne des scores associés à un cours existant.
 */
function calculer_moyenne(int $id): never
{
    $cours = lire_donnees('cours');
    $coursExiste = false;
    foreach ($cours as $element) {
        if ($element['id'] === $id) {
            $coursExiste = true;
            break;
        }
    }

    if (!$coursExiste) {
        repondre_erreur(404, 'Cours introuvable.');
    }

    $resultats = lire_donnees('resultats');
    $somme = 0.0;
    $nombre = 0;
    foreach ($resultats as $resultat) {
        if ($resultat['cours_id'] === $id) {
            $somme += $resultat['score'];
            $nombre++;
        }
    }

    $moyenne = $nombre === 0
        ? null
        : floor((($somme / $nombre) + PHP_FLOAT_EPSILON) * 100 + 0.5) / 100;

    repondre_json(200, [
        'success' => true,
        'data' => [
            'cours_id' => $id,
            'nombre_resultats' => $nombre,
            'moyenne' => $moyenne,
        ],
    ]);
}
