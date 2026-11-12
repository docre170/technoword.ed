<?php
// Liste les cours publiés et permet de les filtrer par catégorie.
require __DIR__ . '/../includes/bootstrap.php';
require_role('apprenant', 'enseignant', 'admin');

$categoryInput = $_GET['categorie'] ?? '';
if (!is_string($categoryInput)) {
    http_response_code(400);
    exit('Filtre de catégorie invalide.');
}

$selectedCategoryId = null;
if ($categoryInput !== '') {
    $validatedCategoryId = filter_var(
        $categoryInput,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    if ($validatedCategoryId === false) {
        http_response_code(400);
        exit('Filtre de catégorie invalide.');
    }
    $selectedCategoryId = $validatedCategoryId;
}

$categoryStatement = $pdo->prepare('SELECT id, nom FROM categorie ORDER BY nom');
$categoryStatement->execute();
$categories = $categoryStatement->fetchAll();

if ($selectedCategoryId !== null) {
    $categoryExists = false;
    foreach ($categories as $category) {
        if ((int) $category['id'] === $selectedCategoryId) {
            $categoryExists = true;
            break;
        }
    }

    if (!$categoryExists) {
        http_response_code(404);
        exit('Catégorie introuvable.');
    }
}

if ($selectedCategoryId === null) {
    $courseStatement = $pdo->prepare(
        'SELECT c.id, c.titre, c.description, cat.nom AS categorie,
                u.nom AS auteur, COUNT(l.id) AS nombre_lecons
         FROM cours AS c
         INNER JOIN categorie AS cat ON cat.id = c.categorie_id
         INNER JOIN utilisateur AS u ON u.id = c.auteur_id
         LEFT JOIN lecon AS l ON l.cours_id = c.id
         WHERE c.statut = :statut
         GROUP BY c.id, c.titre, c.description, cat.nom, u.nom
         ORDER BY c.created_at DESC, c.id DESC'
    );
    $courseStatement->execute(['statut' => 'publie']);
} else {
    $courseStatement = $pdo->prepare(
        'SELECT c.id, c.titre, c.description, cat.nom AS categorie,
                u.nom AS auteur, COUNT(l.id) AS nombre_lecons
         FROM cours AS c
         INNER JOIN categorie AS cat ON cat.id = c.categorie_id
         INNER JOIN utilisateur AS u ON u.id = c.auteur_id
         LEFT JOIN lecon AS l ON l.cours_id = c.id
         WHERE c.statut = :statut AND c.categorie_id = :categorie_id
         GROUP BY c.id, c.titre, c.description, cat.nom, u.nom
         ORDER BY c.created_at DESC, c.id DESC'
    );
    $courseStatement->execute([
        'statut' => 'publie',
        'categorie_id' => $selectedCategoryId,
    ]);
}
$courses = $courseStatement->fetchAll();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7fb">
    <title>Catalogue des cours — TechnoWorld Ed</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= h(app_url('assets/css/app.css')) ?>" rel="stylesheet">
    <script>document.documentElement.dataset.theme = localStorage.getItem('tw-theme') || 'light';</script>
    <script src="<?= h(app_url('assets/js/app.js')) ?>" defer></script>
</head>
<body>
<header class="tw-topbar">
    <div class="tw-shell d-flex align-items-center justify-content-between gap-3">
        <a class="tw-brand" href="<?= h(app_url('index.php')) ?>">
            <span class="tw-brand-mark" aria-hidden="true">TW</span>
            <span>TechnoWorld <span class="text-primary">Ed</span></span>
        </a>
        <div class="d-flex align-items-center gap-3">
            <a class="btn btn-link text-decoration-none fw-semibold d-none d-sm-inline"
               href="<?= h(app_url('index.php')) ?>">Mon espace</a>
            <button class="tw-theme-toggle" type="button" data-theme-toggle
                    data-state="light" aria-pressed="false">
                <span class="tw-toggle-icon" aria-hidden="true">☾</span>
                <span class="tw-toggle-label">Thème sombre</span>
                <span class="tw-toggle-track" aria-hidden="true"><span class="tw-toggle-thumb"></span></span>
            </button>
            <form method="post" action="<?= h(app_url('pages/logout.php')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-outline-secondary rounded-pill px-3" type="submit">
                    Se déconnecter
                </button>
            </form>
        </div>
    </div>
</header>
<main class="tw-shell tw-main">
    <section class="tw-catalog-hero mb-4 mb-lg-5">
        <p class="tw-kicker mb-3">Le catalogue</p>
        <h1 class="tw-title">Des savoirs à <span class="tw-title-accent">explorer.</span></h1>
        <p class="tw-lead mt-3 mb-0">
            Parcourez les cours publiés en informatique, biomédecine et bio-informatique.
        </p>
    </section>

    <section aria-labelledby="catalogue-title">
        <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-4">
            <div>
                <h2 class="h3 fw-bold mb-1" id="catalogue-title">Cours disponibles</h2>
                <p class="text-secondary mb-0">
                    <?= count($courses) ?> cours<?= count($courses) === 1 ? '' : ' disponibles' ?>
                </p>
            </div>
            <form method="get" action="<?= h(app_url('pages/cours.php')) ?>"
                  class="d-flex align-items-center gap-2">
                <label class="visually-hidden" for="categorie">Filtrer par catégorie</label>
                <select class="form-select tw-form-control" id="categorie" name="categorie"
                        aria-label="Filtrer par catégorie">
                    <option value="">Toutes les catégories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"
                            <?= (int) $category['id'] === $selectedCategoryId ? 'selected' : '' ?>>
                            <?= h($category['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-tw-primary px-4" type="submit">Filtrer</button>
            </form>
        </div>

        <?php if ($courses === []): ?>
            <div class="tw-empty-state">
                <div class="tw-side-icon mx-auto mb-3" aria-hidden="true">⌕</div>
                <h3 class="h5 fw-bold">Aucun cours dans cette catégorie pour le moment</h3>
                <p class="text-secondary mb-3">Essayez une autre catégorie pour découvrir les cours publiés.</p>
                <?php if ($selectedCategoryId !== null): ?>
                    <a class="btn btn-outline-primary rounded-pill px-4"
                       href="<?= h(app_url('pages/cours.php')) ?>">Voir tous les cours</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($courses as $course): ?>
                    <article class="col-md-6 col-xl-4">
                        <div class="tw-course-card h-100">
                            <div class="tw-course-card-top">
                                <span class="tw-category-pill"><?= h($course['categorie']) ?></span>
                                <span class="tw-course-symbol" aria-hidden="true">↗</span>
                            </div>
                            <h3 class="h4 fw-bold mt-4 mb-2"><?= h($course['titre']) ?></h3>
                            <p class="tw-course-description mb-4"><?= h($course['description']) ?></p>
                            <div class="tw-course-meta mt-auto">
                                <span><?= h($course['auteur']) ?></span>
                                <span class="tw-meta-separator" aria-hidden="true">·</span>
                                <span><?= (int) $course['nombre_lecons'] ?>
                                    leçon<?= (int) $course['nombre_lecons'] === 1 ? '' : 's' ?></span>
                            </div>
                            <a class="btn btn-outline-primary rounded-pill w-100 mt-3"
                               href="<?= h(app_url('pages/cours-detail.php?id=' . (int) $course['id'])) ?>">
                                Découvrir le cours
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
<footer class="tw-shell tw-footer py-4">
    TechnoWorld Ed <span class="mx-1">·</span> Plateforme universitaire
</footer>
</body>
</html>
