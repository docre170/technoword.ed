<?php
// Affiche les informations et les leçons d'un cours publié.
require __DIR__ . '/../includes/bootstrap.php';
require_role('apprenant', 'enseignant', 'admin');

$courseIdInput = $_GET['id'] ?? '';
if (!is_string($courseIdInput)) {
    http_response_code(400);
    exit('Identifiant de cours invalide.');
}

$courseId = filter_var(
    $courseIdInput,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
if ($courseId === false) {
    http_response_code(400);
    exit('Identifiant de cours invalide.');
}

$courseStatement = $pdo->prepare(
    'SELECT c.id, c.titre, c.description, cat.id AS categorie_id,
            cat.nom AS categorie, u.nom AS auteur
     FROM cours AS c
     INNER JOIN categorie AS cat ON cat.id = c.categorie_id
     INNER JOIN utilisateur AS u ON u.id = c.auteur_id
     WHERE c.id = :id AND c.statut = :statut'
);
$courseStatement->execute([
    'id' => $courseId,
    'statut' => 'publie',
]);
$course = $courseStatement->fetch();

if (!$course) {
    http_response_code(404);
    exit('Cours introuvable.');
}

$lessonStatement = $pdo->prepare(
    'SELECT id, titre, contenu, ordre
     FROM lecon
     WHERE cours_id = :cours_id
     ORDER BY ordre ASC, id ASC'
);
$lessonStatement->execute(['cours_id' => $courseId]);
$lessons = $lessonStatement->fetchAll();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7fb">
    <title><?= h($course['titre']) ?> — TechnoWorld Ed</title>
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
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-outline-secondary rounded-pill px-3 tw-theme-toggle"
                    type="button" data-theme-toggle aria-pressed="false">Thème sombre</button>
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
    <a class="tw-back-link" href="<?= h(app_url('pages/cours.php?categorie=' . (int) $course['categorie_id'])) ?>">
        <span aria-hidden="true">←</span> Retour au catalogue
    </a>

    <section class="tw-detail-hero mt-4 mb-5">
        <span class="tw-category-pill"><?= h($course['categorie']) ?></span>
        <h1 class="tw-title mt-3"><?= h($course['titre']) ?></h1>
        <p class="tw-lead mt-3 mb-3"><?= nl2br(h($course['description'])) ?></p>
        <p class="tw-detail-author mb-0">Proposé par <strong><?= h($course['auteur']) ?></strong></p>
    </section>

    <section aria-labelledby="lessons-title">
        <div class="d-flex align-items-end justify-content-between gap-3 mb-4">
            <div>
                <p class="tw-kicker mb-2">Le parcours</p>
                <h2 class="h3 fw-bold mb-0" id="lessons-title">Leçons du cours</h2>
            </div>
            <span class="tw-lesson-count"><?= count($lessons) ?> leçon<?= count($lessons) === 1 ? '' : 's' ?></span>
        </div>

        <?php if ($lessons === []): ?>
            <div class="tw-empty-state">
                <h3 class="h5 fw-bold">Les leçons arrivent bientôt</h3>
                <p class="text-secondary mb-0">Ce cours ne contient pas encore de leçon publiée.</p>
            </div>
        <?php else: ?>
            <div class="d-grid gap-3">
                <?php foreach ($lessons as $lesson): ?>
                    <article class="tw-lesson-card">
                        <div class="tw-lesson-number"><?= (int) $lesson['ordre'] ?></div>
                        <div class="flex-grow-1">
                            <h3 class="h5 fw-bold mb-2"><?= h($lesson['titre']) ?></h3>
                            <div class="tw-lesson-content"><?= nl2br(h($lesson['contenu'])) ?></div>
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
