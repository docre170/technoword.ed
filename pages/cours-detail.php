<?php
// Affiche les informations et les leçons d'un cours publié.
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('apprenant', 'enseignant', 'admin');

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

$enrollmentStatement = $pdo->prepare(
    'SELECT progression FROM inscription
     WHERE utilisateur_id = :utilisateur_id AND cours_id = :cours_id'
);
$enrollmentStatement->execute([
    'utilisateur_id' => $user['id'],
    'cours_id' => $courseId,
]);
$enrollment = $enrollmentStatement->fetch();
if ($enrollment && $user['role'] === 'apprenant') {
    $progressStatement = $pdo->prepare(
        'SELECT COUNT(lecon.id) AS total_lecons,
                COUNT(lecon_lue.lecon_id) AS lecons_lues
         FROM lecon
         LEFT JOIN lecon_lue
           ON lecon_lue.lecon_id = lecon.id
          AND lecon_lue.utilisateur_id = :utilisateur_id
         WHERE lecon.cours_id = :cours_id'
    );
    $progressStatement->execute([
        'utilisateur_id' => $user['id'],
        'cours_id' => $courseId,
    ]);
    $progress = $progressStatement->fetch();
    $enrollment['progression'] = (int) $progress['total_lecons'] === 0
        ? 0
        : (int) floor((int) $progress['lecons_lues'] * 100 / (int) $progress['total_lecons']);
    $saveProgressStatement = $pdo->prepare(
        'UPDATE inscription
         SET progression = :progression
         WHERE utilisateur_id = :utilisateur_id AND cours_id = :cours_id'
    );
    $saveProgressStatement->execute([
        'progression' => $enrollment['progression'],
        'utilisateur_id' => $user['id'],
        'cours_id' => $courseId,
    ]);
}
$flash = $_SESSION['course_detail_flash'] ?? '';
unset($_SESSION['course_detail_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role('apprenant');
    verify_csrf_token();
    $action = $_POST['action'] ?? '';
    if (!is_string($action)) {
        http_response_code(400);
        exit('Action invalide.');
    }

    if ($action === 'inscrire') {
        $statement = $pdo->prepare(
            'INSERT INTO inscription (utilisateur_id, cours_id, progression)
             VALUES (:utilisateur_id, :cours_id, :progression)
             ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'utilisateur_id' => $user['id'],
            'cours_id' => $courseId,
            'progression' => 0,
        ]);
        $_SESSION['course_detail_flash'] = 'Inscription au cours confirmée.';
        redirect(app_url('pages/cours-detail.php?id=' . $courseId));
    }

    if ($action === 'marquer_lue') {
        if (!$enrollment) {
            http_response_code(403);
            exit('Inscrivez-vous au cours avant de suivre votre progression.');
        }

        $lessonIdInput = $_POST['lecon_id'] ?? '';
        $lessonId = is_string($lessonIdInput)
            ? filter_var($lessonIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        if ($lessonId === false) {
            http_response_code(400);
            exit('Identifiant de leçon invalide.');
        }

        $lessonExists = $pdo->prepare(
            'SELECT id FROM lecon WHERE id = :id AND cours_id = :cours_id'
        );
        $lessonExists->execute(['id' => $lessonId, 'cours_id' => $courseId]);
        if (!$lessonExists->fetchColumn()) {
            http_response_code(404);
            exit('Leçon introuvable.');
        }

        $pdo->beginTransaction();
        try {
            $markStatement = $pdo->prepare(
                'INSERT INTO lecon_lue (utilisateur_id, lecon_id)
                 VALUES (:utilisateur_id, :lecon_id)
                 ON DUPLICATE KEY UPDATE date_lecture = CURRENT_TIMESTAMP'
            );
            $markStatement->execute([
                'utilisateur_id' => $user['id'],
                'lecon_id' => $lessonId,
            ]);

            $progressStatement = $pdo->prepare(
                'UPDATE inscription
                 SET progression = CASE
                     WHEN (SELECT COUNT(*) FROM lecon WHERE cours_id = :total_course_id) = 0 THEN 0
                     ELSE FLOOR(
                         (SELECT COUNT(*)
                          FROM lecon_lue
                          INNER JOIN lecon ON lecon.id = lecon_lue.lecon_id
                          WHERE lecon_lue.utilisateur_id = :lecture_user_id
                            AND lecon.cours_id = :lecture_course_id) * 100
                         / (SELECT COUNT(*) FROM lecon WHERE cours_id = :denominator_course_id)
                     )
                 END
                 WHERE utilisateur_id = :inscription_user_id AND cours_id = :inscription_course_id'
            );
            $progressStatement->execute([
                'total_course_id' => $courseId,
                'lecture_user_id' => $user['id'],
                'lecture_course_id' => $courseId,
                'denominator_course_id' => $courseId,
                'inscription_user_id' => $user['id'],
                'inscription_course_id' => $courseId,
            ]);
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        $_SESSION['course_detail_flash'] = 'Leçon marquée comme lue. Votre progression a été mise à jour.';
        redirect(app_url('pages/cours-detail.php?id=' . $courseId));
    }

    http_response_code(400);
    exit('Action inconnue.');
}

$readLessons = [];
if ($enrollment && $user['role'] === 'apprenant') {
    $readStatement = $pdo->prepare(
        'SELECT lecon_lue.lecon_id
         FROM lecon_lue
         INNER JOIN lecon ON lecon.id = lecon_lue.lecon_id
         WHERE lecon_lue.utilisateur_id = :utilisateur_id
           AND lecon.cours_id = :cours_id'
    );
    $readStatement->execute([
        'utilisateur_id' => $user['id'],
        'cours_id' => $courseId,
    ]);
    foreach ($readStatement->fetchAll(PDO::FETCH_COLUMN) as $readLessonId) {
        $readLessons[(int) $readLessonId] = true;
    }
}

$quizStatement = $pdo->prepare(
    'SELECT q.id, q.titre, COUNT(question.id) AS nombre_questions
     FROM quiz AS q
     LEFT JOIN question ON question.quiz_id = q.id
     WHERE q.cours_id = :cours_id
     GROUP BY q.id, q.titre
     ORDER BY q.id'
);
$quizStatement->execute(['cours_id' => $courseId]);
$quizzes = $quizStatement->fetchAll();
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
                        <?php if ($user['role'] === 'apprenant' && $enrollment): ?>
                            <?php if (isset($readLessons[(int) $lesson['id']])): ?>
                                <span class="tw-read-badge">Lue ✓</span>
                            <?php else: ?>
                                <form method="post"
                                      action="<?= h(app_url('pages/cours-detail.php?id=' . $courseId)) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="marquer_lue">
                                    <input type="hidden" name="lecon_id" value="<?= (int) $lesson['id'] ?>">
                                    <button class="btn btn-sm btn-outline-primary rounded-pill text-nowrap"
                                            type="submit">Marquer comme lue</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($user['role'] === 'apprenant'): ?>
        <section class="tw-enrollment-panel mt-5 mb-5">
            <?php if ($flash !== ''): ?>
                <div class="alert alert-success rounded-3 mb-3" role="status"><?= h($flash) ?></div>
            <?php endif; ?>
            <?php if (!$enrollment): ?>
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                    <div>
                        <p class="tw-kicker mb-2">Commencez votre parcours</p>
                        <h2 class="h4 fw-bold mb-1">Inscrivez-vous gratuitement</h2>
                        <p class="text-secondary mb-0">Votre progression sera enregistrée leçon par leçon.</p>
                    </div>
                    <form method="post" action="<?= h(app_url('pages/cours-detail.php?id=' . $courseId)) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="inscrire">
                        <button class="btn btn-tw-primary px-4" type="submit">S’inscrire au cours</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                    <div>
                        <p class="tw-kicker mb-2">Votre progression</p>
                        <h2 class="h4 fw-bold mb-0">Vous êtes inscrit à ce cours</h2>
                    </div>
                    <div class="tw-progress-summary">
                        <span><?= (int) $enrollment['progression'] ?>%</span>
                        <div class="progress tw-progress-track" role="progressbar"
                             aria-label="Progression du cours"
                             aria-valuenow="<?= (int) $enrollment['progression'] ?>"
                             aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" style="width: <?= (int) $enrollment['progression'] ?>%"></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($user['role'] === 'apprenant' && $quizzes !== [] && $enrollment): ?>
        <section class="mt-5" aria-labelledby="quizzes-title">
            <p class="tw-kicker mb-2">Pour vérifier vos acquis</p>
            <h2 class="h3 fw-bold mb-3" id="quizzes-title">Quiz du cours</h2>
            <div class="row g-3">
                <?php foreach ($quizzes as $quiz): ?>
                    <div class="col-md-6">
                        <article class="tw-quiz-card">
                            <div>
                                <span class="tw-side-icon mb-3" aria-hidden="true">?</span>
                                <h3 class="h5 fw-bold"><?= h($quiz['titre']) ?></h3>
                                <p class="text-secondary mb-0">
                                    <?= (int) $quiz['nombre_questions'] ?>
                                    question<?= (int) $quiz['nombre_questions'] === 1 ? '' : 's' ?>
                                </p>
                            </div>
                            <a class="btn btn-tw-primary mt-3"
                               href="<?= h(app_url('pages/quiz.php?id=' . (int) $quiz['id'])) ?>">
                                Passer le quiz <span class="ms-2" aria-hidden="true">→</span>
                            </a>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</main>
<footer class="tw-shell tw-footer py-4">
    TechnoWorld Ed <span class="mx-1">·</span> Plateforme universitaire
</footer>
</body>
</html>
