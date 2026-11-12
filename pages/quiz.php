<?php
// Affiche un quiz publié et calcule les réponses correctes uniquement côté serveur.
require __DIR__ . '/../includes/bootstrap.php';
$student = require_role('apprenant');

$quizIdInput = $_GET['id'] ?? '';
if (!is_string($quizIdInput)) {
    http_response_code(400);
    exit('Identifiant de quiz invalide.');
}

$quizId = filter_var(
    $quizIdInput,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
if ($quizId === false) {
    http_response_code(400);
    exit('Identifiant de quiz invalide.');
}

$quizStatement = $pdo->prepare(
    'SELECT q.id, q.titre, c.id AS cours_id, c.titre AS cours_titre
     FROM quiz AS q
     INNER JOIN cours AS c ON c.id = q.cours_id
     WHERE q.id = :id AND c.statut = :statut'
);
$quizStatement->execute(['id' => $quizId, 'statut' => 'publie']);
$quiz = $quizStatement->fetch();
if (!$quiz) {
    http_response_code(404);
    exit('Quiz introuvable.');
}

$enrollmentStatement = $pdo->prepare(
    'SELECT utilisateur_id FROM inscription
     WHERE utilisateur_id = :utilisateur_id AND cours_id = :cours_id'
);
$enrollmentStatement->execute([
    'utilisateur_id' => $student['id'],
    'cours_id' => $quiz['cours_id'],
]);
if (!$enrollmentStatement->fetchColumn()) {
    http_response_code(403);
    exit('Inscrivez-vous au cours avant de passer ce quiz.');
}

$questionStatement = $pdo->prepare(
    'SELECT id, enonce FROM question WHERE quiz_id = :quiz_id ORDER BY id'
);
$questionStatement->execute(['quiz_id' => $quizId]);
$questions = $questionStatement->fetchAll();

$answersStatement = $pdo->prepare(
    'SELECT id, texte FROM reponse WHERE question_id = :question_id ORDER BY id'
);
foreach ($questions as &$question) {
    $answersStatement->execute(['question_id' => $question['id']]);
    $question['reponses'] = $answersStatement->fetchAll();
}
unset($question);

$errors = [];
$result = $_SESSION['quiz_submission_result'] ?? null;
unset($_SESSION['quiz_submission_result']);
if (is_array($result) && (int) ($result['quiz_id'] ?? 0) !== $quizId) {
    $result = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $submittedAnswers = $_POST['reponses'] ?? null;

    if (!is_array($submittedAnswers) || $questions === []
        || count($submittedAnswers) !== count($questions)) {
        $errors[] = 'Veuillez répondre à toutes les questions avant de soumettre le quiz.';
    } else {
        $expectedQuestionIds = [];
        foreach ($questions as $question) {
            $expectedQuestionIds[(string) $question['id']] = true;
        }

        foreach ($submittedAnswers as $questionKey => $answerIdInput) {
            if ((!is_string($questionKey) && !is_int($questionKey))
                || !isset($expectedQuestionIds[(string) $questionKey])
                || !is_string($answerIdInput)
                || filter_var(
                    $answerIdInput,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                ) === false) {
                $errors[] = 'Les réponses envoyées sont invalides. Rechargez le quiz.';
                break;
            }
        }

        if ($errors === []) {
            foreach ($questions as $question) {
                $questionKey = (string) $question['id'];
                $answerBelongsToQuestion = false;
                foreach ($question['reponses'] as $answer) {
                    if ((int) $answer['id'] === (int) $submittedAnswers[$questionKey]) {
                        $answerBelongsToQuestion = true;
                        break;
                    }
                }

                if (!$answerBelongsToQuestion) {
                    $errors[] = 'Une réponse ne correspond pas à sa question. Rechargez le quiz.';
                    break;
                }
            }
        }
    }

    if ($errors === []) {
        $correctAnswersStatement = $pdo->prepare(
            'SELECT question.id AS question_id, reponse.id AS reponse_id,
                    reponse.texte AS reponse_texte
             FROM question
             INNER JOIN reponse ON reponse.question_id = question.id
             WHERE question.quiz_id = :quiz_id AND reponse.est_correcte = :correcte'
        );
        $correctAnswersStatement->execute([
            'quiz_id' => $quizId,
            'correcte' => 1,
        ]);
        $correctAnswers = [];
        foreach ($correctAnswersStatement->fetchAll() as $row) {
            $correctAnswers[(string) $row['question_id']] = [
                'id' => (int) $row['reponse_id'],
                'texte' => $row['reponse_texte'],
            ];
        }

        if (count($correctAnswers) !== count($questions)) {
            $errors[] = 'Ce quiz n’est pas encore prêt à être passé. Contactez l’enseignant.';
        } else {
            $score = 0;
            $feedback = [];
            foreach ($questions as $question) {
                $questionKey = (string) $question['id'];
                $selectedAnswerText = '';
                foreach ($question['reponses'] as $answer) {
                    if ((int) $answer['id'] === (int) $submittedAnswers[$questionKey]) {
                        $selectedAnswerText = $answer['texte'];
                        break;
                    }
                }

                $isCorrect = (int) $submittedAnswers[$questionKey] === $correctAnswers[$questionKey]['id'];
                if ($isCorrect) {
                    $score++;
                }
                $feedback[] = [
                    'question' => $question['enonce'],
                    'correcte' => $isCorrect,
                    'reponse_choisie' => $selectedAnswerText,
                    'bonne_reponse' => $correctAnswers[$questionKey]['texte'],
                ];
            }

            $pdo->beginTransaction();
            try {
                $resultStatement = $pdo->prepare(
                    'INSERT INTO resultat (utilisateur_id, quiz_id, score, total)
                     VALUES (:utilisateur_id, :quiz_id, :score, :total)'
                );
                $resultStatement->execute([
                    'utilisateur_id' => $student['id'],
                    'quiz_id' => $quizId,
                    'score' => $score,
                    'total' => count($questions),
                ]);
                $pdo->commit();
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }

            $_SESSION['quiz_submission_result'] = [
                'quiz_id' => $quizId,
                'score' => $score,
                'total' => count($questions),
                'titre' => $quiz['titre'],
                'feedback' => $feedback,
            ];
            redirect(app_url('pages/quiz.php?id=' . $quizId));
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7fb">
    <title><?= h($quiz['titre']) ?> — TechnoWorld Ed</title>
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
               href="<?= h(app_url('pages/cours-detail.php?id=' . (int) $quiz['cours_id'])) ?>">Retour au cours</a>
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
    <p class="tw-kicker mb-2"><?= h($quiz['cours_titre']) ?></p>
    <h1 class="h2 fw-bold mb-2"><?= h($quiz['titre']) ?></h1>
    <p class="text-secondary mb-4"><?= count($questions) ?> question<?= count($questions) === 1 ? '' : 's' ?> · Une seule réponse par question</p>

    <?php if (is_array($result)): ?>
        <div class="tw-result-banner mb-4" role="status">
            <span class="tw-result-score"><?= (int) $result['score'] ?> / <?= (int) $result['total'] ?></span>
            <div>
                <strong>Résultat enregistré</strong>
                <div>Vous avez obtenu <?= (int) $result['score'] ?> bonne<?= (int) $result['score'] === 1 ? '' : 's' ?>
                    réponse<?= (int) $result['score'] === 1 ? '' : 's' ?> sur <?= (int) $result['total'] ?>.</div>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($errors !== []): ?>
        <div class="alert alert-danger rounded-3" role="alert">
            <ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <?php if (is_array($result)): ?>
        <section aria-labelledby="correction-title">
            <div class="d-flex align-items-end justify-content-between gap-3 mb-3">
                <div>
                    <p class="tw-kicker mb-2">Correction</p>
                    <h2 class="h3 fw-bold mb-0" id="correction-title">Vos réponses</h2>
                </div>
                <a class="btn btn-outline-primary rounded-pill px-3"
                   href="<?= h(app_url('pages/quiz.php?id=' . $quizId)) ?>">Repasser le quiz</a>
            </div>
            <div class="d-grid gap-3">
                <?php foreach ($result['feedback'] as $index => $item): ?>
                    <article class="tw-correction-card <?= $item['correcte'] ? 'is-correct' : 'is-incorrect' ?>">
                        <div class="d-flex align-items-start gap-3">
                            <span class="tw-correction-icon" aria-hidden="true">
                                <?= $item['correcte'] ? '✓' : '×' ?>
                            </span>
                            <div class="flex-grow-1">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <h3 class="h5 fw-bold mb-0">
                                        <?= $index + 1 ?>. <?= h($item['question']) ?>
                                    </h3>
                                    <span class="tw-correction-status">
                                        <?= $item['correcte'] ? 'Bonne réponse' : 'Réponse incorrecte' ?>
                                    </span>
                                </div>
                                <p class="mb-1 mt-3">
                                    <strong>Votre réponse :</strong> <?= h($item['reponse_choisie']) ?>
                                </p>
                                <?php if (!$item['correcte']): ?>
                                    <p class="mb-0">
                                        <strong>Bonne réponse :</strong> <?= h($item['bonne_reponse']) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php elseif ($questions === []): ?>
        <div class="tw-empty-state">
            <h2 class="h5 fw-bold">Quiz en préparation</h2>
            <p class="text-secondary mb-0">Ce quiz ne contient pas encore de questions.</p>
        </div>
    <?php else: ?>
        <form method="post" action="<?= h(app_url('pages/quiz.php?id=' . $quizId)) ?>">
            <?= csrf_field() ?>
            <div class="d-grid gap-3">
                <?php foreach ($questions as $index => $question): ?>
                    <fieldset class="tw-question-card">
                        <legend class="h5 fw-bold">
                            <span class="tw-question-index"><?= $index + 1 ?></span>
                            <?= h($question['enonce']) ?>
                        </legend>
                        <div class="d-grid gap-2 mt-3">
                            <?php foreach ($question['reponses'] as $answer): ?>
                                <label class="tw-answer-option">
                                    <input class="form-check-input" type="radio"
                                           name="reponses[<?= (int) $question['id'] ?>]"
                                           value="<?= (int) $answer['id'] ?>" required>
                                    <span><?= h($answer['texte']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php endforeach; ?>
            </div>
            <button class="btn btn-tw-primary px-4 mt-4" type="submit">Soumettre mes réponses</button>
        </form>
    <?php endif; ?>
</main>
<footer class="tw-shell tw-footer py-4">
    TechnoWorld Ed <span class="mx-1">·</span> <?= h($quiz['cours_titre']) ?>
</footer>
</body>
</html>
