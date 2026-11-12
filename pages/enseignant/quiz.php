<?php
// Permet à un enseignant de gérer les quiz de ses propres cours.
require __DIR__ . '/../../includes/bootstrap.php';
$teacher = require_role('enseignant');

function quiz_page_id(mixed $value): int|false
{
    if (!is_string($value) && !is_int($value)) {
        return false;
    }

    return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}

function quiz_page_length(string $value): int
{
    $length = preg_match_all('/./us', $value);
    return $length === false ? PHP_INT_MAX : $length;
}

function quiz_course_is_owned(PDO $pdo, int $courseId, int $teacherId): bool
{
    $statement = $pdo->prepare(
        'SELECT id FROM cours WHERE id = :id AND auteur_id = :auteur_id'
    );
    $statement->execute(['id' => $courseId, 'auteur_id' => $teacherId]);
    return (bool) $statement->fetchColumn();
}

function quiz_is_owned(PDO $pdo, int $quizId, int $courseId, int $teacherId): bool
{
    $statement = $pdo->prepare(
        'SELECT q.id
         FROM quiz AS q
         INNER JOIN cours AS c ON c.id = q.cours_id
         WHERE q.id = :quiz_id AND q.cours_id = :cours_id
           AND c.auteur_id = :auteur_id'
    );
    $statement->execute([
        'quiz_id' => $quizId,
        'cours_id' => $courseId,
        'auteur_id' => $teacherId,
    ]);
    return (bool) $statement->fetchColumn();
}

function normalize_answers(mixed $input, mixed $correctInput): array|false
{
    if (!is_array($input) || count($input) < 2 || count($input) > 6) {
        return false;
    }

    $correctIndex = is_string($correctInput)
        ? filter_var($correctInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])
        : false;
    if ($correctIndex === false || $correctIndex >= count($input)) {
        return false;
    }

    $answers = [];
    $correctAnswerSelected = false;
    foreach (array_values($input) as $index => $answerInput) {
        if (!is_string($answerInput)) {
            return false;
        }
        $answer = trim($answerInput);
        if ($answer === '') {
            if ($index === $correctIndex) {
                return false;
            }
            continue;
        }
        if (quiz_page_length($answer) < 1 || quiz_page_length($answer) > 500) {
            return false;
        }
        if ($index === $correctIndex) {
            $correctAnswerSelected = true;
        }
        $answers[] = [
            'texte' => $answer,
            'est_correcte' => $index === $correctIndex ? 1 : 0,
        ];
    }

    return count($answers) >= 2 && $correctAnswerSelected ? $answers : false;
}

$courseId = quiz_page_id($_GET['cours'] ?? null);
if ($courseId === false) {
    http_response_code(400);
    exit('Identifiant de cours invalide.');
}

$courseStatement = $pdo->prepare(
    'SELECT id, titre, statut FROM cours
     WHERE id = :id AND auteur_id = :auteur_id'
);
$courseStatement->execute(['id' => $courseId, 'auteur_id' => $teacher['id']]);
$course = $courseStatement->fetch();
if (!$course) {
    http_response_code(404);
    exit('Cours introuvable.');
}

$errors = [];
$flash = $_SESSION['teacher_quiz_flash'] ?? '';
unset($_SESSION['teacher_quiz_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $actionInput = $_POST['action'] ?? '';
    if (!is_string($actionInput)) {
        http_response_code(400);
        exit('Action invalide.');
    }

    $quizId = quiz_page_id($_POST['quiz_id'] ?? null);
    $questionId = quiz_page_id($_POST['question_id'] ?? null);

    switch ($actionInput) {
        case 'creer_quiz':
        case 'modifier_quiz':
            $titleInput = $_POST['titre'] ?? '';
            $title = is_string($titleInput) ? trim($titleInput) : '';
            if (quiz_page_length($title) < 3 || quiz_page_length($title) > 200) {
                $errors[] = 'Le titre du quiz doit contenir entre 3 et 200 caractères.';
            }

            if ($actionInput === 'modifier_quiz'
                && ($quizId === false || !quiz_is_owned($pdo, $quizId, $courseId, (int) $teacher['id']))) {
                http_response_code(404);
                exit('Quiz introuvable.');
            }

            if ($errors === []) {
                if ($actionInput === 'creer_quiz') {
                    $statement = $pdo->prepare(
                        'INSERT INTO quiz (titre, cours_id) VALUES (:titre, :cours_id)'
                    );
                    $statement->execute(['titre' => $title, 'cours_id' => $courseId]);
                    $quizId = (int) $pdo->lastInsertId();
                    $_SESSION['teacher_quiz_flash'] = 'Quiz créé.';
                } else {
                    $statement = $pdo->prepare(
                        'UPDATE quiz SET titre = :titre
                         WHERE id = :id AND cours_id = :cours_id'
                    );
                    $statement->execute([
                        'titre' => $title,
                        'id' => $quizId,
                        'cours_id' => $courseId,
                    ]);
                    $_SESSION['teacher_quiz_flash'] = 'Quiz modifié.';
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
                redirect(app_url('pages/enseignant/quiz.php?cours=' . $courseId . '&quiz=' . $quizId));
            }
            break;

        case 'supprimer_quiz':
            if ($quizId === false || !quiz_is_owned($pdo, $quizId, $courseId, (int) $teacher['id'])) {
                http_response_code(404);
                exit('Quiz introuvable.');
            }
            $statement = $pdo->prepare(
                'DELETE FROM quiz WHERE id = :id AND cours_id = :cours_id'
            );
            $statement->execute(['id' => $quizId, 'cours_id' => $courseId]);
            $statusStatement = $pdo->prepare(
                'UPDATE cours SET statut = :statut
                 WHERE id = :id AND auteur_id = :auteur_id'
            );
            $statusStatement->execute([
                'statut' => 'en_attente',
                'id' => $courseId,
                'auteur_id' => $teacher['id'],
            ]);
            $_SESSION['teacher_quiz_flash'] = 'Quiz et questions associés supprimés.';
            redirect(app_url('pages/enseignant/quiz.php?cours=' . $courseId));

        case 'creer_question':
        case 'modifier_question':
            $questionInput = $_POST['enonce'] ?? '';
            $questionText = is_string($questionInput) ? trim($questionInput) : '';
            $answers = normalize_answers($_POST['reponses'] ?? null, $_POST['correcte'] ?? null);

            if ($quizId === false || !quiz_is_owned($pdo, $quizId, $courseId, (int) $teacher['id'])) {
                http_response_code(404);
                exit('Quiz introuvable.');
            }
            if (quiz_page_length($questionText) < 5 || quiz_page_length($questionText) > 2000) {
                $errors[] = 'L’énoncé doit contenir entre 5 et 2 000 caractères.';
            }
            if ($answers === false) {
                $errors[] = 'Ajoutez de 2 à 6 réponses (1 à 500 caractères chacune) et choisissez la bonne.';
            }

            if ($actionInput === 'modifier_question') {
                $ownerStatement = $pdo->prepare(
                    'SELECT question.id
                     FROM question
                     INNER JOIN quiz ON quiz.id = question.quiz_id
                     INNER JOIN cours ON cours.id = quiz.cours_id
                     WHERE question.id = :question_id AND quiz.id = :quiz_id
                       AND cours.id = :cours_id AND cours.auteur_id = :auteur_id'
                );
                $ownerStatement->execute([
                    'question_id' => $questionId,
                    'quiz_id' => $quizId,
                    'cours_id' => $courseId,
                    'auteur_id' => $teacher['id'],
                ]);
                if (!$ownerStatement->fetchColumn()) {
                    http_response_code(404);
                    exit('Question introuvable.');
                }
            }

            if ($errors === []) {
                $pdo->beginTransaction();
                try {
                    if ($actionInput === 'creer_question') {
                        $statement = $pdo->prepare(
                            'INSERT INTO question (enonce, quiz_id)
                             VALUES (:enonce, :quiz_id)'
                        );
                        $statement->execute(['enonce' => $questionText, 'quiz_id' => $quizId]);
                        $questionId = (int) $pdo->lastInsertId();
                    } else {
                        $statement = $pdo->prepare(
                            'UPDATE question SET enonce = :enonce
                             WHERE id = :id AND quiz_id = :quiz_id'
                        );
                        $statement->execute([
                            'enonce' => $questionText,
                            'id' => $questionId,
                            'quiz_id' => $quizId,
                        ]);
                        $deleteAnswers = $pdo->prepare(
                            'DELETE FROM reponse WHERE question_id = :question_id'
                        );
                        $deleteAnswers->execute(['question_id' => $questionId]);
                    }

                    $insertAnswer = $pdo->prepare(
                        'INSERT INTO reponse (texte, est_correcte, question_id)
                         VALUES (:texte, :est_correcte, :question_id)'
                    );
                    foreach ($answers as $answer) {
                        $insertAnswer->execute([
                            'texte' => $answer['texte'],
                            'est_correcte' => $answer['est_correcte'],
                            'question_id' => $questionId,
                        ]);
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
                    $pdo->commit();
                    $_SESSION['teacher_quiz_flash'] = 'Question et réponses enregistrées.';
                    redirect(app_url('pages/enseignant/quiz.php?cours=' . $courseId . '&quiz=' . $quizId));
                } catch (PDOException $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }
            }
            break;

        case 'supprimer_question':
            if ($quizId === false || $questionId === false
                || !quiz_is_owned($pdo, $quizId, $courseId, (int) $teacher['id'])) {
                http_response_code(404);
                exit('Quiz ou question introuvable.');
            }
            $statement = $pdo->prepare(
                'DELETE question
                 FROM question
                 INNER JOIN quiz ON quiz.id = question.quiz_id
                 INNER JOIN cours ON cours.id = quiz.cours_id
                 WHERE question.id = :question_id AND quiz.id = :quiz_id
                   AND cours.id = :cours_id AND cours.auteur_id = :auteur_id'
            );
            $statement->execute([
                'question_id' => $questionId,
                'quiz_id' => $quizId,
                'cours_id' => $courseId,
                'auteur_id' => $teacher['id'],
            ]);
            if ($statement->rowCount() !== 1) {
                http_response_code(404);
                exit('Question introuvable.');
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
            $_SESSION['teacher_quiz_flash'] = 'Question supprimée.';
            redirect(app_url('pages/enseignant/quiz.php?cours=' . $courseId . '&quiz=' . $quizId));

        default:
            http_response_code(400);
            exit('Action inconnue.');
    }
}

$quizListStatement = $pdo->prepare(
    'SELECT q.id, q.titre, COUNT(DISTINCT question.id) AS nombre_questions
     FROM quiz AS q
     LEFT JOIN question ON question.quiz_id = q.id
     WHERE q.cours_id = :cours_id
     GROUP BY q.id, q.titre
     ORDER BY q.id'
);
$quizListStatement->execute(['cours_id' => $courseId]);
$quizzes = $quizListStatement->fetchAll();

$selectedQuizId = quiz_page_id($_GET['quiz'] ?? null);
if ($selectedQuizId === false && isset($_GET['quiz'])) {
    http_response_code(400);
    exit('Identifiant de quiz invalide.');
}

$selectedQuiz = null;
$questions = [];
if ($selectedQuizId !== false && $selectedQuizId !== null) {
    $selectedQuizStatement = $pdo->prepare(
        'SELECT id, titre FROM quiz WHERE id = :id AND cours_id = :cours_id'
    );
    $selectedQuizStatement->execute(['id' => $selectedQuizId, 'cours_id' => $courseId]);
    $selectedQuiz = $selectedQuizStatement->fetch();
    if (!$selectedQuiz) {
        http_response_code(404);
        exit('Quiz introuvable.');
    }

    $questionStatement = $pdo->prepare(
        'SELECT id, enonce FROM question WHERE quiz_id = :quiz_id ORDER BY id'
    );
    $questionStatement->execute(['quiz_id' => $selectedQuizId]);
    $questions = $questionStatement->fetchAll();

    $answerStatement = $pdo->prepare(
        'SELECT reponse.id, reponse.texte, reponse.est_correcte
         FROM reponse
         INNER JOIN question ON question.id = reponse.question_id
         WHERE question.id = :question_id
         ORDER BY reponse.id'
    );
    foreach ($questions as &$question) {
        $answerStatement->execute(['question_id' => $question['id']]);
        $question['reponses'] = $answerStatement->fetchAll();
    }
    unset($question);
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7fb">
    <title>Quiz — <?= h($course['titre']) ?> — TechnoWorld Ed</title>
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
               href="<?= h(app_url('pages/enseignant/cours.php?cours=' . $courseId)) ?>">Retour au cours</a>
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
    <p class="tw-kicker mb-2">Espace enseignant · <?= h($course['titre']) ?></p>
    <h1 class="h2 fw-bold mb-1">Gestion des quiz</h1>
    <p class="text-secondary mb-4">Les changements au quiz renvoient le cours en attente de validation.</p>

    <?php if ($flash !== ''): ?>
        <div class="alert alert-success rounded-3" role="status"><?= h($flash) ?></div>
    <?php endif; ?>
    <?php if ($errors !== []): ?>
        <div class="alert alert-danger rounded-3" role="alert">
            <ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="row g-4 align-items-start">
        <section class="col-lg-4">
            <div class="tw-panel">
                <div class="tw-panel-body">
                    <h2 class="h5 fw-bold"><?= $selectedQuiz ? 'Modifier le quiz' : 'Créer un quiz' ?></h2>
                    <form method="post" action="<?= h(app_url('pages/enseignant/quiz.php?cours=' . $courseId . ($selectedQuiz ? '&quiz=' . (int) $selectedQuiz['id'] : ''))) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="<?= $selectedQuiz ? 'modifier_quiz' : 'creer_quiz' ?>">
                        <?php if ($selectedQuiz): ?>
                            <input type="hidden" name="quiz_id" value="<?= (int) $selectedQuiz['id'] ?>">
                        <?php endif; ?>
                        <label class="form-label" for="quiz-title">Titre</label>
                        <input class="form-control tw-form-control mb-3" id="quiz-title" name="titre"
                               maxlength="200" minlength="3" required value="<?= h($selectedQuiz['titre'] ?? '') ?>">
                        <button class="btn btn-tw-primary w-100" type="submit">
                            <?= $selectedQuiz ? 'Enregistrer le quiz' : 'Créer le quiz' ?>
                        </button>
                    </form>
                    <?php if ($selectedQuiz): ?>
                        <form class="mt-2" method="post" action="<?= h(app_url('pages/enseignant/quiz.php?cours=' . $courseId)) ?>"
                              onsubmit="return confirm('Supprimer ce quiz, ses questions et ses résultats ?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="supprimer_quiz">
                            <input type="hidden" name="quiz_id" value="<?= (int) $selectedQuiz['id'] ?>">
                            <button class="btn btn-outline-danger w-100" type="submit">Supprimer le quiz</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($quizzes !== []): ?>
                <div class="mt-4">
                    <h2 class="h6 text-secondary text-uppercase fw-bold mb-2">Quiz du cours</h2>
                    <div class="d-grid gap-2">
                        <?php foreach ($quizzes as $quiz): ?>
                            <a class="tw-quiz-link <?= (int) ($selectedQuiz['id'] ?? 0) === (int) $quiz['id'] ? 'is-selected' : '' ?>"
                               href="<?= h(app_url('pages/enseignant/quiz.php?cours=' . $courseId . '&quiz=' . (int) $quiz['id'])) ?>">
                                <span class="fw-semibold"><?= h($quiz['titre']) ?></span>
                                <span class="small text-secondary"><?= (int) $quiz['nombre_questions'] ?> question<?= (int) $quiz['nombre_questions'] === 1 ? '' : 's' ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <section class="col-lg-8">
            <?php if (!$selectedQuiz): ?>
                <div class="tw-empty-state">
                    <h2 class="h5 fw-bold">Créez un quiz pour ce cours</h2>
                    <p class="text-secondary mb-0">Vous pourrez ensuite y ajouter des questions à choix unique.</p>
                </div>
            <?php else: ?>
                <div class="tw-panel mb-4">
                    <div class="tw-panel-body">
                        <h2 class="h5 fw-bold">Ajouter une question</h2>
                        <p class="small text-secondary">De 2 à 6 réponses, avec une seule bonne réponse.</p>
                        <form method="post" action="<?= h(app_url('pages/enseignant/quiz.php?cours=' . $courseId . '&quiz=' . (int) $selectedQuiz['id'])) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="creer_question">
                            <input type="hidden" name="quiz_id" value="<?= (int) $selectedQuiz['id'] ?>">
                            <label class="form-label" for="new-question">Énoncé</label>
                            <textarea class="form-control tw-form-control mb-3" id="new-question" name="enonce"
                                      rows="3" minlength="5" maxlength="2000" required></textarea>
                            <div class="row g-2">
                                <?php for ($index = 0; $index < 4; $index++): ?>
                                    <div class="col-md-6">
                                        <label class="form-label" for="new-answer-<?= $index ?>">
                                            Réponse <?= $index + 1 ?>
                                            <?php if ($index < 2): ?><span class="text-danger">*</span><?php endif; ?>
                                        </label>
                                        <div class="input-group">
                                            <input class="form-control tw-form-control" id="new-answer-<?= $index ?>"
                                                   name="reponses[]" maxlength="500" <?= $index < 2 ? 'required' : '' ?>>
                                            <span class="input-group-text">
                                                <input class="form-check-input mt-0" type="radio" name="correcte"
                                                       value="<?= $index ?>" aria-label="Réponse correcte <?= $index + 1 ?>" required>
                                            </span>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                            <p class="form-text">Cochez le bouton radio associé à la réponse correcte.</p>
                            <button class="btn btn-tw-primary" type="submit">Ajouter la question</button>
                        </form>
                    </div>
                </div>

                <div class="d-grid gap-3">
                    <?php foreach ($questions as $question): ?>
                        <details class="tw-editor-card">
                            <summary class="d-flex align-items-center gap-3">
                                <span class="tw-lesson-number">?</span>
                                <span class="fw-semibold flex-grow-1"><?= h($question['enonce']) ?></span>
                                <span class="small text-secondary">Modifier</span>
                            </summary>
                            <div class="pt-4">
                                <form method="post" action="<?= h(app_url('pages/enseignant/quiz.php?cours=' . $courseId . '&quiz=' . (int) $selectedQuiz['id'])) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="modifier_question">
                                    <input type="hidden" name="quiz_id" value="<?= (int) $selectedQuiz['id'] ?>">
                                    <input type="hidden" name="question_id" value="<?= (int) $question['id'] ?>">
                                    <label class="form-label" for="question-<?= (int) $question['id'] ?>">Énoncé</label>
                                    <textarea class="form-control tw-form-control mb-3"
                                              id="question-<?= (int) $question['id'] ?>" name="enonce"
                                              rows="3" maxlength="2000" required><?= h($question['enonce']) ?></textarea>
                                    <p class="small text-secondary mb-2">Sélectionnez la bonne réponse. Les réponses actuelles sont conservées.</p>
                                    <div class="row g-2">
                                        <?php foreach ($question['reponses'] as $index => $answer): ?>
                                            <div class="col-md-6">
                                                <label class="form-label" for="answer-<?= (int) $answer['id'] ?>">Réponse <?= $index + 1 ?></label>
                                                <div class="input-group">
                                                    <input class="form-control tw-form-control" id="answer-<?= (int) $answer['id'] ?>"
                                                           name="reponses[]" maxlength="500" required value="<?= h($answer['texte']) ?>">
                                                    <span class="input-group-text">
                                                        <input class="form-check-input mt-0" type="radio" name="correcte"
                                                               value="<?= $index ?>" aria-label="Réponse correcte <?= $index + 1 ?>"
                                                            <?= isset($answer['est_correcte']) && (int) $answer['est_correcte'] === 1 ? 'checked' : '' ?>>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (count($question['reponses']) < 6): ?>
                                            <div class="col-md-6">
                                                <label class="form-label" for="extra-answer-<?= (int) $question['id'] ?>">
                                                    Ajouter une réponse
                                                </label>
                                                <div class="input-group">
                                                    <input class="form-control tw-form-control"
                                                           id="extra-answer-<?= (int) $question['id'] ?>"
                                                           name="reponses[]" maxlength="500">
                                                    <span class="input-group-text">
                                                        <input class="form-check-input mt-0" type="radio" name="correcte"
                                                               value="<?= count($question['reponses']) ?>"
                                                               aria-label="Réponse correcte ajoutée">
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <button class="btn btn-outline-primary rounded-pill mt-3" type="submit">Enregistrer la question</button>
                                </form>
                                <form class="mt-2" method="post"
                                      action="<?= h(app_url('pages/enseignant/quiz.php?cours=' . $courseId . '&quiz=' . (int) $selectedQuiz['id'])) ?>"
                                      onsubmit="return confirm('Supprimer cette question ?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="supprimer_question">
                                    <input type="hidden" name="quiz_id" value="<?= (int) $selectedQuiz['id'] ?>">
                                    <input type="hidden" name="question_id" value="<?= (int) $question['id'] ?>">
                                    <button class="btn btn-link text-danger px-0" type="submit">Supprimer la question</button>
                                </form>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>
<footer class="tw-shell tw-footer py-4">TechnoWorld Ed <span class="mx-1">·</span> Espace enseignant</footer>
</body>
</html>
