<?php
// Permet à un enseignant de gérer uniquement ses cours et leurs leçons.
require __DIR__ . '/../../includes/bootstrap.php';
$teacher = require_role('enseignant');

function field_length(string $value): int
{
    $length = preg_match_all('/./us', $value);
    return $length === false ? PHP_INT_MAX : $length;
}

function validate_positive_id(mixed $value): int|false
{
    if (!is_string($value) && !is_int($value)) {
        return false;
    }

    return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}

function ensure_owned_course(PDO $pdo, int $courseId, int $teacherId): bool
{
    $statement = $pdo->prepare(
        'SELECT id FROM cours WHERE id = :id AND auteur_id = :auteur_id'
    );
    $statement->execute(['id' => $courseId, 'auteur_id' => $teacherId]);
    return (bool) $statement->fetchColumn();
}

$errors = [];
$flash = $_SESSION['teacher_course_flash'] ?? '';
unset($_SESSION['teacher_course_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $action = $_POST['action'] ?? '';
    if (!is_string($action)) {
        http_response_code(400);
        exit('Action invalide.');
    }

    $courseId = validate_positive_id($_POST['cours_id'] ?? null);
    $lessonId = validate_positive_id($_POST['lecon_id'] ?? null);

    switch ($action) {
        case 'creer_cours':
        case 'modifier_cours':
            $titleInput = $_POST['titre'] ?? '';
            $descriptionInput = $_POST['description'] ?? '';
            $categoryId = validate_positive_id($_POST['categorie_id'] ?? null);
            $title = is_string($titleInput) ? trim($titleInput) : '';
            $description = is_string($descriptionInput) ? trim($descriptionInput) : '';

            if (field_length($title) < 3 || field_length($title) > 200) {
                $errors[] = 'Le titre doit contenir entre 3 et 200 caractères.';
            }
            if (field_length($description) < 10 || field_length($description) > 20000) {
                $errors[] = 'La description doit contenir entre 10 et 20 000 caractères.';
            }
            if ($categoryId === false) {
                $errors[] = 'Veuillez choisir une catégorie valide.';
            } else {
                $categoryStatement = $pdo->prepare('SELECT id FROM categorie WHERE id = :id');
                $categoryStatement->execute(['id' => $categoryId]);
                if (!$categoryStatement->fetchColumn()) {
                    $errors[] = 'La catégorie sélectionnée n’existe plus.';
                }
            }
            if ($action === 'modifier_cours'
                && ($courseId === false || !ensure_owned_course($pdo, $courseId, (int) $teacher['id']))) {
                http_response_code(404);
                exit('Cours introuvable.');
            }

            if ($errors === []) {
                if ($action === 'creer_cours') {
                    $statement = $pdo->prepare(
                        'INSERT INTO cours
                            (titre, description, statut, prix, acces, auteur_id, categorie_id)
                         VALUES (:titre, :description, :statut, 0, :acces, :auteur_id, :categorie_id)'
                    );
                    $statement->execute([
                        'titre' => $title,
                        'description' => $description,
                        'statut' => 'en_attente',
                        'acces' => 'gratuit',
                        'auteur_id' => $teacher['id'],
                        'categorie_id' => $categoryId,
                    ]);
                    $courseId = (int) $pdo->lastInsertId();
                    $_SESSION['teacher_course_flash'] = 'Cours créé et envoyé pour validation.';
                } else {
                    $statement = $pdo->prepare(
                        'UPDATE cours
                         SET titre = :titre, description = :description,
                             categorie_id = :categorie_id, statut = :statut
                         WHERE id = :id AND auteur_id = :auteur_id'
                    );
                    $statement->execute([
                        'titre' => $title,
                        'description' => $description,
                        'categorie_id' => $categoryId,
                        'statut' => 'en_attente',
                        'id' => $courseId,
                        'auteur_id' => $teacher['id'],
                    ]);
                    $_SESSION['teacher_course_flash'] = 'Cours modifié et renvoyé pour validation.';
                }

                redirect(app_url('pages/enseignant/cours.php?cours=' . $courseId));
            }
            break;

        case 'supprimer_cours':
            if ($courseId === false || !ensure_owned_course($pdo, $courseId, (int) $teacher['id'])) {
                http_response_code(404);
                exit('Cours introuvable.');
            }

            $statement = $pdo->prepare(
                'DELETE FROM cours WHERE id = :id AND auteur_id = :auteur_id'
            );
            $statement->execute(['id' => $courseId, 'auteur_id' => $teacher['id']]);
            $_SESSION['teacher_course_flash'] = 'Cours et contenu associé supprimés.';
            redirect(app_url('pages/enseignant/cours.php'));

        case 'creer_lecon':
            $lessonTitleInput = $_POST['titre_lecon'] ?? '';
            $lessonContentInput = $_POST['contenu'] ?? '';
            $lessonTitle = is_string($lessonTitleInput) ? trim($lessonTitleInput) : '';
            $lessonContent = is_string($lessonContentInput) ? trim($lessonContentInput) : '';

            if ($courseId === false || !ensure_owned_course($pdo, $courseId, (int) $teacher['id'])) {
                http_response_code(404);
                exit('Cours introuvable.');
            }
            if (field_length($lessonTitle) < 1 || field_length($lessonTitle) > 200) {
                $errors[] = 'Le titre de la leçon doit contenir entre 1 et 200 caractères.';
            }
            if (field_length($lessonContent) < 1 || field_length($lessonContent) > 50000) {
                $errors[] = 'Le contenu de la leçon doit contenir entre 1 et 50 000 caractères.';
            }

            if ($errors === []) {
                $pdo->beginTransaction();
                try {
                    $orderStatement = $pdo->prepare(
                        'SELECT COALESCE(MAX(ordre), 0) + 1
                         FROM lecon WHERE cours_id = :cours_id'
                    );
                    $orderStatement->execute(['cours_id' => $courseId]);
                    $lessonOrder = (int) $orderStatement->fetchColumn();

                    $statement = $pdo->prepare(
                        'INSERT INTO lecon (titre, contenu, ordre, cours_id)
                         VALUES (:titre, :contenu, :ordre, :cours_id)'
                    );
                    $statement->execute([
                        'titre' => $lessonTitle,
                        'contenu' => $lessonContent,
                        'ordre' => $lessonOrder,
                        'cours_id' => $courseId,
                    ]);
                    $statusStatement = $pdo->prepare(
                        'UPDATE cours SET statut = :statut
                         WHERE id = :id AND auteur_id = :auteur_id'
                    );
                    $statusStatement->execute([
                        'statut' => 'en_attente',
                        'id' => $courseId,
                        'auteur_id' => $teacher['id'],
                    ]);
                    $pdo->commit();
                    $_SESSION['teacher_course_flash'] = 'Leçon ajoutée. Le cours est en attente de validation.';
                    redirect(app_url('pages/enseignant/cours.php?cours=' . $courseId));
                } catch (PDOException $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }
            }
            break;

        case 'modifier_lecon':
            $lessonTitleInput = $_POST['titre_lecon'] ?? '';
            $lessonContentInput = $_POST['contenu'] ?? '';
            $orderInput = $_POST['ordre'] ?? '';
            $lessonTitle = is_string($lessonTitleInput) ? trim($lessonTitleInput) : '';
            $lessonContent = is_string($lessonContentInput) ? trim($lessonContentInput) : '';
            $lessonOrder = validate_positive_id($orderInput);

            if ($courseId === false || !ensure_owned_course($pdo, $courseId, (int) $teacher['id'])) {
                http_response_code(404);
                exit('Cours introuvable.');
            }
            if ($lessonId === false) {
                http_response_code(404);
                exit('Leçon introuvable.');
            }
            if (field_length($lessonTitle) < 1 || field_length($lessonTitle) > 200) {
                $errors[] = 'Le titre de la leçon doit contenir entre 1 et 200 caractères.';
            }
            if (field_length($lessonContent) < 1 || field_length($lessonContent) > 50000) {
                $errors[] = 'Le contenu de la leçon doit contenir entre 1 et 50 000 caractères.';
            }
            if ($lessonOrder === false || $lessonOrder > 2147483647) {
                $errors[] = 'L’ordre de la leçon doit être un entier positif valide.';
            }

            $lessonOwnerStatement = $pdo->prepare(
                'SELECT l.id
                 FROM lecon AS l
                 INNER JOIN cours AS c ON c.id = l.cours_id
                 WHERE l.id = :lecon_id AND l.cours_id = :cours_id
                   AND c.auteur_id = :auteur_id'
            );
            $lessonOwnerStatement->execute([
                'lecon_id' => $lessonId,
                'cours_id' => $courseId,
                'auteur_id' => $teacher['id'],
            ]);
            if (!$lessonOwnerStatement->fetchColumn()) {
                http_response_code(404);
                exit('Leçon introuvable.');
            }

            if ($errors === []) {
                try {
                    $pdo->beginTransaction();
                    $statement = $pdo->prepare(
                        'UPDATE lecon
                         SET titre = :titre, contenu = :contenu, ordre = :ordre
                         WHERE id = :id AND cours_id = :cours_id'
                    );
                    $statement->execute([
                        'titre' => $lessonTitle,
                        'contenu' => $lessonContent,
                        'ordre' => $lessonOrder,
                        'id' => $lessonId,
                        'cours_id' => $courseId,
                    ]);
                    $statusStatement = $pdo->prepare(
                        'UPDATE cours SET statut = :statut
                         WHERE id = :id AND auteur_id = :auteur_id'
                    );
                    $statusStatement->execute([
                        'statut' => 'en_attente',
                        'id' => $courseId,
                        'auteur_id' => $teacher['id'],
                    ]);
                    $pdo->commit();
                    $_SESSION['teacher_course_flash'] = 'Leçon modifiée. Le cours est en attente de validation.';
                    redirect(app_url('pages/enseignant/cours.php?cours=' . $courseId));
                } catch (PDOException $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    if (($exception->errorInfo[0] ?? '') === '23000') {
                        $errors[] = 'Une leçon utilise déjà cet ordre dans ce cours.';
                    } else {
                        throw $exception;
                    }
                }
            }
            break;

        case 'supprimer_lecon':
            if ($courseId === false || !ensure_owned_course($pdo, $courseId, (int) $teacher['id'])
                || $lessonId === false) {
                http_response_code(404);
                exit('Cours ou leçon introuvable.');
            }

            $statement = $pdo->prepare(
                'DELETE l FROM lecon AS l
                 INNER JOIN cours AS c ON c.id = l.cours_id
                 WHERE l.id = :lecon_id AND l.cours_id = :cours_id
                   AND c.auteur_id = :auteur_id'
            );
            $statement->execute([
                'lecon_id' => $lessonId,
                'cours_id' => $courseId,
                'auteur_id' => $teacher['id'],
            ]);
            if ($statement->rowCount() !== 1) {
                http_response_code(404);
                exit('Leçon introuvable.');
            }
            $statusStatement = $pdo->prepare(
                'UPDATE cours SET statut = :statut
                 WHERE id = :id AND auteur_id = :auteur_id'
            );
            $statusStatement->execute([
                'statut' => 'en_attente',
                'id' => $courseId,
                'auteur_id' => $teacher['id'],
            ]);
            $_SESSION['teacher_course_flash'] = 'Leçon supprimée. Le cours est en attente de validation.';
            redirect(app_url('pages/enseignant/cours.php?cours=' . $courseId));

        default:
            http_response_code(400);
            exit('Action inconnue.');
    }
}

$categoriesStatement = $pdo->prepare('SELECT id, nom FROM categorie ORDER BY nom');
$categoriesStatement->execute();
$categories = $categoriesStatement->fetchAll();

$coursesStatement = $pdo->prepare(
    'SELECT c.id, c.titre, c.description, c.statut, c.categorie_id,
            cat.nom AS categorie, COUNT(l.id) AS nombre_lecons
     FROM cours AS c
     INNER JOIN categorie AS cat ON cat.id = c.categorie_id
     LEFT JOIN lecon AS l ON l.cours_id = c.id
     WHERE c.auteur_id = :auteur_id
     GROUP BY c.id, c.titre, c.description, c.statut, c.categorie_id, cat.nom
     ORDER BY c.updated_at DESC, c.id DESC'
);
$coursesStatement->execute(['auteur_id' => $teacher['id']]);
$courses = $coursesStatement->fetchAll();

$selectedCourseId = validate_positive_id($_GET['cours'] ?? null);
if ($selectedCourseId === false && isset($_GET['cours'])) {
    http_response_code(400);
    exit('Identifiant de cours invalide.');
}

$selectedCourse = null;
$lessons = [];
if ($selectedCourseId !== false && $selectedCourseId !== null) {
    $selectedStatement = $pdo->prepare(
        'SELECT c.id, c.titre, c.description, c.statut, c.categorie_id
         FROM cours AS c
         WHERE c.id = :id AND c.auteur_id = :auteur_id'
    );
    $selectedStatement->execute([
        'id' => $selectedCourseId,
        'auteur_id' => $teacher['id'],
    ]);
    $selectedCourse = $selectedStatement->fetch();
    if (!$selectedCourse) {
        http_response_code(404);
        exit('Cours introuvable.');
    }

    $lessonsStatement = $pdo->prepare(
        'SELECT id, titre, contenu, ordre
         FROM lecon WHERE cours_id = :cours_id
         ORDER BY ordre, id'
    );
    $lessonsStatement->execute(['cours_id' => $selectedCourseId]);
    $lessons = $lessonsStatement->fetchAll();
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7fb">
    <title>Gestion des cours — TechnoWorld Ed</title>
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
    <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-4">
        <div>
            <p class="tw-kicker mb-2">Espace enseignant</p>
            <h1 class="h2 fw-bold mb-1">Gestion de vos cours</h1>
            <p class="text-secondary mb-0">Les modifications sont soumises à une nouvelle validation.</p>
        </div>
        <a class="btn btn-outline-secondary rounded-pill px-4" href="<?= h(app_url('index.php')) ?>">
            Retour à l’espace
        </a>
    </div>

    <?php if ($flash !== ''): ?>
        <div class="alert alert-success rounded-3" role="status"><?= h($flash) ?></div>
    <?php endif; ?>
    <?php if ($errors !== []): ?>
        <div class="alert alert-danger rounded-3" role="alert">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-4 align-items-start">
        <section class="col-lg-5">
            <div class="tw-panel">
                <div class="tw-panel-body">
                    <p class="tw-kicker mb-2"><?= $selectedCourse ? 'Modifier le cours' : 'Nouveau contenu' ?></p>
                    <h2 class="h4 fw-bold mb-3"><?= $selectedCourse ? h($selectedCourse['titre']) : 'Créer un cours' ?></h2>
                    <form method="post" action="<?= h(app_url('pages/enseignant/cours.php' . ($selectedCourse ? '?cours=' . (int) $selectedCourse['id'] : ''))) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="<?= $selectedCourse ? 'modifier_cours' : 'creer_cours' ?>">
                        <?php if ($selectedCourse): ?>
                            <input type="hidden" name="cours_id" value="<?= (int) $selectedCourse['id'] ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label" for="titre">Titre</label>
                            <input class="form-control tw-form-control" id="titre" name="titre"
                                   maxlength="200" minlength="3" required
                                   value="<?= h($selectedCourse['titre'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="description">Description</label>
                            <textarea class="form-control tw-form-control" id="description" name="description"
                                      rows="5" maxlength="20000" minlength="10" required><?= h($selectedCourse['description'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="categorie_id">Catégorie</label>
                            <select class="form-select tw-form-control" id="categorie_id" name="categorie_id" required>
                                <option value="">Choisir une catégorie</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= (int) $category['id'] ?>"
                                        <?= isset($selectedCourse['categorie_id'])
                                            && (int) $selectedCourse['categorie_id'] === (int) $category['id']
                                                ? 'selected' : '' ?>>
                                        <?= h($category['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn btn-tw-primary w-100" type="submit">
                            <?= $selectedCourse ? 'Enregistrer et renvoyer en validation' : 'Créer et envoyer en validation' ?>
                        </button>
                    </form>
                    <?php if ($selectedCourse): ?>
                        <form class="mt-3" method="post" action="<?= h(app_url('pages/enseignant/cours.php')) ?>"
                              onsubmit="return confirm('Supprimer ce cours et tout son contenu ?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="supprimer_cours">
                            <input type="hidden" name="cours_id" value="<?= (int) $selectedCourse['id'] ?>">
                            <button class="btn btn-outline-danger w-100" type="submit">Supprimer le cours</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="col-lg-7">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 class="h4 fw-bold mb-0">Vos cours <span class="tw-count-badge"><?= count($courses) ?></span></h2>
                <?php if ($selectedCourse): ?>
                    <a class="tw-back-link" href="<?= h(app_url('pages/enseignant/cours.php')) ?>">Nouveau cours</a>
                <?php endif; ?>
            </div>
            <?php if ($courses === []): ?>
                <div class="tw-empty-state">
                    <div class="tw-side-icon mx-auto mb-3" aria-hidden="true">✎</div>
                    <h3 class="h5 fw-bold">Votre espace de création est prêt</h3>
                    <p class="text-secondary mb-0">Créez un cours pour commencer à construire votre parcours.</p>
                </div>
            <?php else: ?>
                <div class="d-grid gap-3">
                    <?php foreach ($courses as $course): ?>
                        <article class="tw-course-row <?= (int) ($selectedCourse['id'] ?? 0) === (int) $course['id'] ? 'is-selected' : '' ?>">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                <div>
                                    <span class="tw-category-pill"><?= h($course['categorie']) ?></span>
                                    <h3 class="h5 fw-bold mt-2 mb-1"><?= h($course['titre']) ?></h3>
                                    <p class="text-secondary small mb-0">
                                        <?= (int) $course['nombre_lecons'] ?> leçon<?= (int) $course['nombre_lecons'] === 1 ? '' : 's' ?>
                                    </p>
                                </div>
                                <span class="tw-status-badge tw-status-<?= h($course['statut']) ?>">
                                    <?= h(str_replace('_', ' ', $course['statut'])) ?>
                                </span>
                            </div>
                            <a class="btn btn-sm btn-outline-primary rounded-pill mt-3"
                               href="<?= h(app_url('pages/enseignant/cours.php?cours=' . (int) $course['id'])) ?>">
                                Gérer le cours et ses leçons
                            </a>
                            <a class="btn btn-sm btn-outline-secondary rounded-pill mt-3 ms-1"
                               href="<?= h(app_url('pages/enseignant/quiz.php?cours=' . (int) $course['id'])) ?>">
                                Gérer les quiz
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <?php if ($selectedCourse): ?>
        <section class="mt-5" aria-labelledby="lessons-title">
            <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-3">
                <div>
                    <p class="tw-kicker mb-2">Contenu pédagogique</p>
                    <h2 class="h3 fw-bold mb-0" id="lessons-title">Leçons de « <?= h($selectedCourse['titre']) ?> »</h2>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="tw-panel">
                        <div class="tw-panel-body">
                            <h3 class="h5 fw-bold">Ajouter une leçon</h3>
                            <p class="small text-secondary">La leçon sera ajoutée à la fin du parcours.</p>
                            <form method="post" action="<?= h(app_url('pages/enseignant/cours.php?cours=' . (int) $selectedCourse['id'])) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="creer_lecon">
                                <input type="hidden" name="cours_id" value="<?= (int) $selectedCourse['id'] ?>">
                                <div class="mb-3">
                                    <label class="form-label" for="titre_lecon">Titre</label>
                                    <input class="form-control tw-form-control" id="titre_lecon" name="titre_lecon"
                                           maxlength="200" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="contenu_lecon">Contenu</label>
                                    <textarea class="form-control tw-form-control" id="contenu_lecon" name="contenu"
                                              rows="6" maxlength="50000" required></textarea>
                                </div>
                                <button class="btn btn-tw-primary w-100" type="submit">Ajouter la leçon</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <?php if ($lessons === []): ?>
                        <div class="tw-empty-state">
                            <p class="text-secondary mb-0">Aucune leçon pour ce cours.</p>
                        </div>
                    <?php else: ?>
                        <div class="d-grid gap-3">
                            <?php foreach ($lessons as $lesson): ?>
                                <details class="tw-editor-card">
                                    <summary class="d-flex align-items-center gap-3">
                                        <span class="tw-lesson-number"><?= (int) $lesson['ordre'] ?></span>
                                        <span class="fw-semibold flex-grow-1"><?= h($lesson['titre']) ?></span>
                                        <span class="small text-secondary">Modifier</span>
                                    </summary>
                                    <div class="pt-4">
                                        <form method="post" action="<?= h(app_url('pages/enseignant/cours.php?cours=' . (int) $selectedCourse['id'])) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="modifier_lecon">
                                            <input type="hidden" name="cours_id" value="<?= (int) $selectedCourse['id'] ?>">
                                            <input type="hidden" name="lecon_id" value="<?= (int) $lesson['id'] ?>">
                                            <div class="row g-3">
                                                <div class="col-md-8">
                                                    <label class="form-label" for="titre_lecon_<?= (int) $lesson['id'] ?>">Titre</label>
                                                    <input class="form-control tw-form-control"
                                                           id="titre_lecon_<?= (int) $lesson['id'] ?>"
                                                           name="titre_lecon" maxlength="200" required
                                                           value="<?= h($lesson['titre']) ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label" for="ordre_<?= (int) $lesson['id'] ?>">Ordre</label>
                                                    <input class="form-control tw-form-control"
                                                           id="ordre_<?= (int) $lesson['id'] ?>"
                                                           name="ordre" type="number" min="1" max="2147483647"
                                                           required value="<?= (int) $lesson['ordre'] ?>">
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label" for="contenu_<?= (int) $lesson['id'] ?>">Contenu</label>
                                                    <textarea class="form-control tw-form-control"
                                                              id="contenu_<?= (int) $lesson['id'] ?>"
                                                              name="contenu" rows="5" maxlength="50000"
                                                              required><?= h($lesson['contenu']) ?></textarea>
                                                </div>
                                            </div>
                                            <button class="btn btn-outline-primary rounded-pill mt-3" type="submit">Enregistrer la leçon</button>
                                        </form>
                                        <form class="mt-2" method="post"
                                              action="<?= h(app_url('pages/enseignant/cours.php?cours=' . (int) $selectedCourse['id'])) ?>"
                                              onsubmit="return confirm('Supprimer cette leçon ?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="supprimer_lecon">
                                            <input type="hidden" name="cours_id" value="<?= (int) $selectedCourse['id'] ?>">
                                            <input type="hidden" name="lecon_id" value="<?= (int) $lesson['id'] ?>">
                                            <button class="btn btn-link text-danger px-0" type="submit">Supprimer la leçon</button>
                                        </form>
                                    </div>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
</main>
<footer class="tw-shell tw-footer py-4">
    TechnoWorld Ed <span class="mx-1">·</span> Espace enseignant
</footer>
</body>
</html>
