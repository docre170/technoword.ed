<?php
// Permet à l'administrateur d'examiner, publier ou refuser les cours en attente.
require __DIR__ . '/../../includes/bootstrap.php';
require_role('admin');

$flash = $_SESSION['admin_course_flash'] ?? '';
unset($_SESSION['admin_course_flash']);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $courseIdInput = $_POST['cours_id'] ?? '';
    $decision = $_POST['decision'] ?? '';
    $courseId = is_string($courseIdInput)
        ? filter_var($courseIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;

    if ($courseId === false) {
        http_response_code(400);
        exit('Identifiant de cours invalide.');
    }

    $newStatus = match ($decision) {
        'publier' => 'publie',
        'refuser' => 'refuse',
        default => false,
    };

    if ($newStatus === false) {
        http_response_code(400);
        exit('Décision invalide.');
    }

    $statement = $pdo->prepare(
        'UPDATE cours
         SET statut = :nouveau_statut
         WHERE id = :id AND statut = :statut_actuel'
    );
    $statement->execute([
        'nouveau_statut' => $newStatus,
        'id' => $courseId,
        'statut_actuel' => 'en_attente',
    ]);

    if ($statement->rowCount() !== 1) {
        $errors[] = 'Ce cours n’est plus en attente ou n’existe pas. Actualisez la page.';
    } else {
        $_SESSION['admin_course_flash'] = $newStatus === 'publie'
            ? 'Cours publié : il est maintenant visible dans le catalogue.'
            : 'Cours refusé. Il n’apparaîtra pas dans le catalogue public.';
        redirect(app_url('pages/admin/cours.php'));
    }
}

$pendingStatement = $pdo->prepare(
    'SELECT c.id, c.titre, c.description, c.created_at,
            cat.nom AS categorie, u.nom AS auteur,
            COUNT(l.id) AS nombre_lecons
     FROM cours AS c
     INNER JOIN categorie AS cat ON cat.id = c.categorie_id
     INNER JOIN utilisateur AS u ON u.id = c.auteur_id
     LEFT JOIN lecon AS l ON l.cours_id = c.id
     WHERE c.statut = :statut
     GROUP BY c.id, c.titre, c.description, c.created_at, cat.nom, u.nom
     ORDER BY c.created_at ASC, c.id ASC'
);
$pendingStatement->execute(['statut' => 'en_attente']);
$pendingCourses = $pendingStatement->fetchAll();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7fb">
    <title>Validation des cours — TechnoWorld Ed</title>
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
               href="<?= h(app_url('index.php')) ?>">Administration</a>
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
    <div class="tw-admin-hero mb-4 mb-lg-5">
        <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3">
            <div>
                <p class="tw-kicker mb-2">Espace administrateur</p>
                <h1 class="tw-title">Validation des <span class="tw-title-accent">cours.</span></h1>
                <p class="tw-lead mt-3 mb-0">
                    Examinez les contenus soumis par les enseignants avant leur publication.
                </p>
            </div>
            <div class="tw-pending-count">
                <span><?= count($pendingCourses) ?></span>
                <small>en attente</small>
            </div>
        </div>
    </div>

    <?php if ($flash !== ''): ?>
        <div class="alert alert-success rounded-3" role="status"><?= h($flash) ?></div>
    <?php endif; ?>
    <?php if ($errors !== []): ?>
        <div class="alert alert-warning rounded-3" role="alert">
            <?php foreach ($errors as $error): ?><p class="mb-0"><?= h($error) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section aria-labelledby="pending-courses-title">
        <div class="tw-section-heading mb-3">
            <div>
                <p class="tw-kicker mb-2">À examiner</p>
                <h2 class="h3 fw-bold mb-0" id="pending-courses-title">Cours soumis</h2>
            </div>
            <a class="tw-back-link" href="<?= h(app_url('index.php')) ?>">
                Retour au tableau de bord <span aria-hidden="true">→</span>
            </a>
        </div>

        <?php if ($pendingCourses === []): ?>
            <div class="tw-empty-state">
                <div class="tw-side-icon mx-auto mb-3" aria-hidden="true">✓</div>
                <h3 class="h5 fw-bold">Tout est à jour</h3>
                <p class="text-secondary mb-0">Aucun cours n’attend actuellement votre décision.</p>
            </div>
        <?php else: ?>
            <div class="d-grid gap-3">
                <?php foreach ($pendingCourses as $course): ?>
                    <article class="tw-review-card">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-4">
                            <div class="flex-grow-1">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <span class="tw-category-pill"><?= h($course['categorie']) ?></span>
                                    <span class="tw-status-badge tw-status-en_attente">En attente</span>
                                </div>
                                <h3 class="h4 fw-bold mb-2"><?= h($course['titre']) ?></h3>
                                <p class="text-secondary mb-3"><?= nl2br(h($course['description'])) ?></p>
                                <div class="tw-course-meta">
                                    <span>Par <?= h($course['auteur']) ?></span>
                                    <span class="tw-meta-separator" aria-hidden="true">·</span>
                                    <span><?= (int) $course['nombre_lecons'] ?>
                                        leçon<?= (int) $course['nombre_lecons'] === 1 ? '' : 's' ?></span>
                                    <span class="tw-meta-separator" aria-hidden="true">·</span>
                                    <span>Soumis le <?= h(date('d/m/Y', strtotime($course['created_at']))) ?></span>
                                </div>
                            </div>
                            <div class="tw-review-actions">
                                <form method="post" action="<?= h(app_url('pages/admin/cours.php')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="cours_id" value="<?= (int) $course['id'] ?>">
                                    <input type="hidden" name="decision" value="publier">
                                    <button class="btn btn-tw-primary w-100" type="submit">
                                        Publier le cours
                                    </button>
                                </form>
                                <form method="post" action="<?= h(app_url('pages/admin/cours.php')) ?>"
                                      onsubmit="return confirm('Refuser ce cours ?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="cours_id" value="<?= (int) $course['id'] ?>">
                                    <input type="hidden" name="decision" value="refuser">
                                    <button class="btn btn-outline-danger w-100" type="submit">
                                        Refuser
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
<footer class="tw-shell tw-footer py-4">
    TechnoWorld Ed <span class="mx-1">·</span> Administration
</footer>
</body>
</html>
