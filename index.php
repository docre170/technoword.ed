<?php
// Affiche l'espace d'accueil réservé aux utilisateurs connectés.
require __DIR__ . '/includes/bootstrap.php';
$user = require_role('apprenant', 'enseignant', 'admin');

$dashboardData = [];
if ($user['role'] === 'apprenant') {
    $statement = $pdo->prepare(
        'SELECT
            (SELECT COUNT(*) FROM cours WHERE statut = :statut) AS courses,
            (SELECT COUNT(*) FROM inscription WHERE utilisateur_id = :utilisateur_id) AS enrolled'
    );
    $statement->execute([
        'statut' => 'publie',
        'utilisateur_id' => $user['id'],
    ]);
    $dashboardData = $statement->fetch();
} elseif ($user['role'] === 'enseignant') {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) AS total,
                SUM(CASE WHEN statut = :pending THEN 1 ELSE 0 END) AS pending
         FROM cours WHERE auteur_id = :auteur_id'
    );
    $statement->execute([
        'pending' => 'en_attente',
        'auteur_id' => $user['id'],
    ]);
    $dashboardData = $statement->fetch();
} else {
    $statement = $pdo->prepare(
        'SELECT
            (SELECT COUNT(*) FROM utilisateur) AS users,
            (SELECT COUNT(*) FROM cours WHERE statut = :pending) AS pending,
            (SELECT COUNT(*) FROM cours WHERE statut = :published) AS published,
            (SELECT COUNT(*) FROM categorie) AS categories'
    );
    $statement->execute([
        'pending' => 'en_attente',
        'published' => 'publie',
    ]);
    $dashboardData = $statement->fetch();
}

$dashboardCopy = [
    'apprenant' => [
        'kicker' => 'Votre espace étudiant',
        'title' => 'Apprendre aujourd’hui,',
        'accent' => 'comprendre demain.',
        'description' => 'Explorez les cours et avancez à votre rythme en informatique et en biomédecine.',
        'action' => 'Explorer les cours',
        'url' => 'pages/cours.php',
        'metric' => 'cours publiés',
        'value' => (int) ($dashboardData['courses'] ?? 0),
        'icon' => '⌕',
    ],
    'enseignant' => [
        'kicker' => 'Espace enseignant',
        'title' => 'Transmettez votre',
        'accent' => 'expertise.',
        'description' => 'Créez vos cours, structurez vos leçons et suivez leur statut de validation.',
        'action' => 'Gérer mes cours',
        'url' => 'pages/enseignant/cours.php',
        'metric' => 'cours dans votre espace',
        'value' => (int) ($dashboardData['total'] ?? 0),
        'icon' => '✎',
    ],
    'admin' => [
        'kicker' => 'Administration',
        'title' => 'Pilotez la',
        'accent' => 'plateforme.',
        'description' => 'Vue d’ensemble des comptes, du catalogue et des cours à examiner.',
        'action' => 'Examiner les cours',
        'url' => 'pages/admin/cours.php',
        'metric' => 'cours en attente',
        'value' => (int) ($dashboardData['pending'] ?? 0),
        'icon' => '▦',
    ],
];
$dashboard = $dashboardCopy[$user['role']];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7fb">
    <title><?= h(ucfirst($user['role'])) ?> — TechnoWorld Ed</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= h(app_url('assets/css/app.css')) ?>" rel="stylesheet">
    <script>
        document.documentElement.dataset.theme = localStorage.getItem('tw-theme') || 'light';
    </script>
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
    <div class="row align-items-stretch g-4 g-lg-5 tw-dashboard tw-dashboard-<?= h($user['role']) ?>">
        <section class="col-lg-7 py-lg-4">
            <p class="tw-kicker mb-3"><?= h($dashboard['kicker']) ?></p>
            <h1 class="tw-title"><?= h($dashboard['title']) ?><br>
                <span class="tw-title-accent"><?= h($dashboard['accent']) ?></span>
            </h1>
            <p class="tw-lead mt-4 mb-4"><?= h($dashboard['description']) ?></p>
            <span class="tw-role"><span class="tw-role-dot" aria-hidden="true"></span>
                <?= h(ucfirst($user['role'])) ?>
            </span>
            <div class="mt-4">
                <a class="btn btn-tw-primary px-4" href="<?= h(app_url($dashboard['url'])) ?>">
                    <?= h($dashboard['action']) ?> <span class="ms-2" aria-hidden="true">→</span>
                </a>
            </div>
        </section>
        <aside class="col-lg-5">
            <div class="tw-side-panel tw-dashboard-panel d-flex flex-column justify-content-between">
                <div>
                    <div class="tw-side-icon mb-4" aria-hidden="true"><?= h($dashboard['icon']) ?></div>
                    <p class="tw-kicker mb-2">Compte connecté</p>
                    <h2 class="h3 fw-bold mb-2"><?= h($user['nom']) ?></h2>
                    <p class="text-secondary mb-0"><?= h($user['email']) ?></p>
                </div>
                <div class="tw-dashboard-metric mt-5">
                    <span class="tw-dashboard-metric-value"><?= (int) $dashboard['value'] ?></span>
                    <span class="tw-dashboard-metric-label"><?= h($dashboard['metric']) ?></span>
                </div>
            </div>
        </aside>
    </div>
    <?php if ($user['role'] === 'admin'): ?>
        <section class="row g-3 mt-4" aria-label="Indicateurs de la plateforme">
            <div class="col-6 col-lg-4">
                <div class="tw-stat-card"><span><?= (int) ($dashboardData['users'] ?? 0) ?></span><small>comptes</small></div>
            </div>
            <div class="col-6 col-lg-4">
                <div class="tw-stat-card"><span><?= (int) ($dashboardData['published'] ?? 0) ?></span><small>cours publiés</small></div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="tw-stat-card"><span><?= (int) ($dashboardData['categories'] ?? 0) ?></span><small>catégories</small></div>
            </div>
        </section>
        <div class="d-flex flex-wrap gap-3 mt-4">
            <a class="btn btn-tw-primary px-4" href="<?= h(app_url('pages/admin/cours.php')) ?>">
                Examiner les cours <span class="ms-2" aria-hidden="true">→</span>
            </a>
            <a class="btn btn-outline-primary rounded-pill px-4"
               href="<?= h(app_url('pages/admin/utilisateurs.php')) ?>">
                Gérer les comptes
            </a>
            <a class="btn btn-outline-primary rounded-pill px-4"
               href="<?= h(app_url('pages/admin/categories.php')) ?>">
                Gérer les catégories
            </a>
            <a class="tw-back-link align-self-center" href="<?= h(app_url('pages/cours.php')) ?>">
                Voir le catalogue publié
            </a>
        </div>
    <?php elseif ($user['role'] === 'apprenant'): ?>
        <section class="mt-5">
            <div class="tw-section-heading">
                <div>
                    <p class="tw-kicker mb-2">Votre activité</p>
                    <h2 class="h3 fw-bold mb-0">Reprenez votre parcours</h2>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-3 mt-3">
                <a class="btn btn-outline-primary rounded-pill px-4"
                   href="<?= h(app_url('pages/mes-cours.php')) ?>">
                    Mes cours <span class="tw-count-badge ms-2"><?= (int) ($dashboardData['enrolled'] ?? 0) ?></span>
                </a>
                <a class="btn btn-outline-primary rounded-pill px-4"
                   href="<?= h(app_url('pages/resultats.php')) ?>">
                    Mes résultats
                </a>
            </div>
        </section>
    <?php elseif ($user['role'] === 'enseignant'): ?>
        <section class="mt-5">
            <div class="tw-section-heading">
                <div>
                    <p class="tw-kicker mb-2">Suivi éditorial</p>
                    <h2 class="h3 fw-bold mb-0">Vos cours</h2>
                </div>
                <a class="tw-back-link" href="<?= h(app_url('pages/enseignant/cours.php')) ?>">
                    Ouvrir la gestion <span aria-hidden="true">→</span>
                </a>
            </div>
            <div class="tw-status-strip mt-3">
                <span class="tw-status-dot" aria-hidden="true"></span>
                <?= (int) ($dashboardData['pending'] ?? 0) ?> en attente de validation
            </div>
        </section>
    <?php endif; ?>
</main>
<footer class="tw-shell tw-footer py-4">
    TechnoWorld Ed <span class="mx-1">·</span> Plateforme universitaire
</footer>
</body>
</html>
