<?php
// Affiche les cours auxquels l'apprenant est inscrit et leur progression.
require __DIR__ . '/../includes/bootstrap.php';
$student = require_role('apprenant');

$statement = $pdo->prepare(
    'SELECT c.id, c.titre, c.description, cat.nom AS categorie,
            i.progression, i.date_inscription,
            COUNT(l.id) AS total_lecons,
            COUNT(ll.lecon_id) AS lecons_lues
     FROM inscription AS i
     INNER JOIN cours AS c ON c.id = i.cours_id
     INNER JOIN categorie AS cat ON cat.id = c.categorie_id
     LEFT JOIN lecon AS l ON l.cours_id = c.id
     LEFT JOIN lecon_lue AS ll
       ON ll.lecon_id = l.id AND ll.utilisateur_id = i.utilisateur_id
     WHERE i.utilisateur_id = :utilisateur_id AND c.statut = :statut
     GROUP BY c.id, c.titre, c.description, cat.nom, i.progression, i.date_inscription
     ORDER BY i.date_inscription DESC'
);
$statement->execute([
    'utilisateur_id' => $student['id'],
    'statut' => 'publie',
]);
$courses = $statement->fetchAll();
$saveProgressStatement = $pdo->prepare(
    'UPDATE inscription
     SET progression = :progression
     WHERE utilisateur_id = :utilisateur_id AND cours_id = :cours_id'
);
foreach ($courses as &$course) {
    $course['progression'] = (int) $course['total_lecons'] === 0
        ? 0
        : (int) floor((int) $course['lecons_lues'] * 100 / (int) $course['total_lecons']);
    $saveProgressStatement->execute([
        'progression' => $course['progression'],
        'utilisateur_id' => $student['id'],
        'cours_id' => $course['id'],
    ]);
}
unset($course);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7fb">
    <title>Mes cours — TechnoWorld Ed</title>
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
                <button class="btn btn-outline-secondary rounded-pill px-3" type="submit">Déconnexion</button>
            </form>
        </div>
    </div>
</header>
<main class="tw-shell tw-main">
    <section class="tw-catalog-hero mb-4 mb-lg-5">
        <p class="tw-kicker mb-2">Votre apprentissage</p>
        <h1 class="tw-title">Mes <span class="tw-title-accent">cours.</span></h1>
        <p class="tw-lead mt-3 mb-0">Retrouvez vos inscriptions et reprenez votre progression.</p>
    </section>

    <?php if ($courses === []): ?>
        <div class="tw-empty-state">
            <h2 class="h5 fw-bold">Aucun cours pour le moment</h2>
            <p class="text-secondary">Inscrivez-vous à un cours pour suivre votre progression.</p>
            <a class="btn btn-tw-primary px-4" href="<?= h(app_url('pages/cours.php')) ?>">Parcourir les cours</a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($courses as $course): ?>
                <article class="col-md-6 col-xl-4">
                    <div class="tw-course-card h-100">
                        <span class="tw-category-pill align-self-start"><?= h($course['categorie']) ?></span>
                        <h2 class="h4 fw-bold mt-4 mb-2"><?= h($course['titre']) ?></h2>
                        <p class="tw-course-description"><?= h($course['description']) ?></p>
                        <div class="tw-my-course-progress">
                            <div class="d-flex justify-content-between small mb-2">
                                <span><?= (int) $course['lecons_lues'] ?> / <?= (int) $course['total_lecons'] ?> leçons lues</span>
                                <strong><?= (int) $course['progression'] ?>%</strong>
                            </div>
                            <div class="progress tw-progress-track" role="progressbar"
                                 aria-label="Progression de <?= h($course['titre']) ?>"
                                 aria-valuenow="<?= (int) $course['progression'] ?>"
                                 aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar" style="width: <?= (int) $course['progression'] ?>%"></div>
                            </div>
                        </div>
                        <a class="btn btn-outline-primary rounded-pill w-100 mt-3"
                           href="<?= h(app_url('pages/cours-detail.php?id=' . (int) $course['id'])) ?>">
                            Continuer le cours
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<footer class="tw-shell tw-footer py-4">TechnoWorld Ed <span class="mx-1">·</span> Mes cours</footer>
</body>
</html>
