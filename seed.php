<?php
declare(strict_types=1);

mt_srand(42);

function echouer(string $message): never
{
    fwrite(STDERR, 'Erreur : ' . $message . PHP_EOL);
    exit(1);
}

function choisir(array $valeurs): string
{
    return $valeurs[mt_rand(0, count($valeurs) - 1)];
}

function creerDescription(string $sujet): string
{
    $debuts = [
        'Découvrez',
        'Étudiez',
        'Approfondissez',
        'Expérimentez',
        'Maîtrisez',
    ];
    $complements = [
        'notions, méthodes et exemples',
        'applications en santé et en recherche',
        'principes utiles à la pratique',
        'concepts clés et leur mise en œuvre',
        'approches rigoureuses et résultats',
    ];
    $objectifs = [
        'Des exercices stimulent l’esprit critique.',
        'Vous comparerez des cas réels et des méthodes.',
        'Théorie et pratique éclairent les données.',
        'Des activités guidées facilitent l’apprentissage.',
        'Vous testerez des méthodes et lirez les résultats.',
    ];
    $niveaux = [
        'Adapté au niveau débutant.',
        'Niveau intermédiaire.',
        'Progression vers l’avancé.',
        'Des exemples guident l’apprentissage.',
    ];

    $description = choisir($debuts) . ' « ' . $sujet . ' » : ' . choisir($complements) . '. ' . choisir($objectifs);
    if (mt_rand(0, 1) === 1) {
        $description .= ' ' . choisir($niveaux);
    }

    return $description;
}

function ecrireAtomiquement(string $destination, string $contenu): void
{
    $dossier = dirname($destination);
    $temporaire = @tempnam($dossier, basename($destination) . '.tmp-');
    if ($temporaire === false) {
        echouer('Impossible de créer un fichier temporaire dans ' . $dossier);
    }

    $octetsEcrits = @file_put_contents($temporaire, $contenu, LOCK_EX);
    if ($octetsEcrits === false || $octetsEcrits !== strlen($contenu)) {
        @unlink($temporaire);
        echouer('Impossible d’écrire le fichier temporaire de ' . basename($destination));
    }

    if (!@rename($temporaire, $destination)) {
        if (file_exists($destination) && !@unlink($destination)) {
            @unlink($temporaire);
            echouer('Impossible de remplacer ' . $destination);
        }
        if (!@rename($temporaire, $destination)) {
            @unlink($temporaire);
            echouer('Impossible de déplacer le fichier temporaire vers ' . $destination);
        }
    }
}

if (ini_set('serialize_precision', '-1') === false) {
    echouer('Impossible de configurer la précision de sérialisation.');
}

global $argc, $argv;
if ($argc > 2) {
    echouer('Utilisation : php seed.php [nb_cours]');
}

$nombreCours = 5000;
if ($argc === 2) {
    $nombreValide = filter_var($argv[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($nombreValide === false) {
        echouer('Le nombre de cours doit être un entier positif ou nul.');
    }
    $nombreCours = $nombreValide;
}

$sujets = [
    'Algorithmique', 'Bases de données', 'Réseaux', 'Génie logiciel',
    'Intelligence artificielle', 'Apprentissage automatique', 'Cybersécurité',
    'Systèmes d’exploitation', 'Programmation web', 'Informatique graphique',
    'Architecture des ordinateurs', 'Cloud computing', 'Robotique',
    'Traitement du langage naturel', 'Vision par ordinateur', 'Cryptographie',
    'Structures de données', 'Développement mobile', 'Systèmes distribués',
    'Interaction humain-machine', 'Biologie cellulaire', 'Génétique',
    'Anatomie', 'Bio-informatique', 'Épidémiologie', 'Immunologie',
    'Neurosciences', 'Microbiologie', 'Physiologie', 'Pharmacologie',
    'Biologie moléculaire', 'Écologie', 'Virologie', 'Génomique',
    'Protéomique', 'Biostatistique', 'Imagerie médicale', 'Santé publique',
    'Biochimie', 'Télémédecine',
];
$auteurs = [
    'Alice Martin', 'Benoît Durand', 'Camille Petit', 'David Moreau',
    'Émilie Laurent', 'Farid Simon', 'Gabrielle Michel', 'Hugo Lefebvre',
    'Inès Leroy', 'Jules Roux', 'Kenza David', 'Louis Bertrand',
    'Maya Morel', 'Noé Fournier', 'Olivia Girard', 'Paul Bonnet',
    'Quentin Dupont', 'Rose Lambert', 'Sami Fontaine', 'Théo Rousseau',
    'Uma Vincent', 'Victor Muller', 'Wendy Faure', 'Xavier André',
    'Yasmine Mercier', 'Zéphyrin Blanc', 'Anaïs Garnier', 'Joël Chevalier',
    'Aimé François', 'Léa Gauthier',
];
$utilisateurs = [
    'Amina', 'Arthur', 'Aya', 'Bastien', 'Chloé', 'Dylan', 'Élodie', 'Enzo',
    'Farah', 'Gabriel', 'Hana', 'Ibrahim', 'Jade', 'Jérôme', 'Karim', 'Lina',
    'Lucas', 'Maëlle', 'Malik', 'Nadia', 'Nicolas', 'Océane', 'Omar', 'Pauline',
    'Rayan', 'Salomé', 'Sarah', 'Thomas', 'Yanis', 'Zoé', 'Adèle', 'Bilal',
    'Célia', 'Dounia', 'Ethan', 'Fatima', 'Gaspard', 'Ilona', 'Julie', 'Khaled',
    'Léo', 'Manon', 'Naël', 'Oscar', 'Rita', 'Sofia', 'Tariq', 'Victor',
    'Yara', 'Zack',
];
$categories = ['Informatique', 'Biomédecine', 'Bio-informatique'];

$dossierDonnees = __DIR__ . DIRECTORY_SEPARATOR . 'data';
if (!is_dir($dossierDonnees) && !@mkdir($dossierDonnees, 0777, true) && !is_dir($dossierDonnees)) {
    echouer('Impossible de créer le dossier data/.');
}

$cours = [];
$resultats = [];
$idResultat = 1;
for ($idCours = 1; $idCours <= $nombreCours; $idCours++) {
    $sujet = choisir($sujets);
    $cours[] = [
        'id' => $idCours,
        'titre' => 'Cours ' . $idCours . ' : ' . $sujet,
        'description' => creerDescription($sujet),
        'categorie' => choisir($categories),
        'auteur' => choisir($auteurs),
    ];

    for ($i = 0; $i < 5; $i++) {
        $resultats[] = [
            'id' => $idResultat++,
            'cours_id' => $idCours,
            'utilisateur' => choisir($utilisateurs),
            'score' => mt_rand(0, 200) / 10,
        ];
    }
}

$optionsJson = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR;
try {
    $jsonCours = json_encode($cours, $optionsJson);
    $jsonResultats = json_encode($resultats, $optionsJson);
} catch (JsonException $exception) {
    echouer('Impossible d’encoder les données JSON : ' . $exception->getMessage());
}

$cheminCours = $dossierDonnees . DIRECTORY_SEPARATOR . 'cours.json';
$cheminResultats = $dossierDonnees . DIRECTORY_SEPARATOR . 'resultats.json';
ecrireAtomiquement($cheminCours, $jsonCours . PHP_EOL);
ecrireAtomiquement($cheminResultats, $jsonResultats . PHP_EOL);

$tailleCours = @filesize($cheminCours);
$tailleResultats = @filesize($cheminResultats);
$hashCours = @hash_file('sha256', $cheminCours);
$hashResultats = @hash_file('sha256', $cheminResultats);
if ($tailleCours === false || $tailleResultats === false || $hashCours === false || $hashResultats === false) {
    echouer('Impossible de vérifier les fichiers générés.');
}

printf(
    "Cours : %d\nRésultats : %d\ncours.json : %d octets, SHA-256 : %s\nresultats.json : %d octets, SHA-256 : %s\n",
    $nombreCours,
    count($resultats),
    $tailleCours,
    $hashCours,
    $tailleResultats,
    $hashResultats
);
