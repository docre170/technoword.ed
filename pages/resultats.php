<?php
// Affiche l'historique des quiz et le classement calculé à partir des meilleurs scores.
require __DIR__ . '/../includes/bootstrap.php';
$student = require_role('apprenant');

$historyStatement = $pdo->prepare(
    'SELECT resultat.score, resultat.total, resultat.date_passage,
            quiz.titre AS quiz_titre, cours.titre AS cours_titre
     FROM resultat
     INNER JOIN quiz ON quiz.id = resultat.quiz_id
     INNER JOIN cours ON cours.id = quiz.cours_id
     WHERE resultat.utilisateur_id = :utilisateur_id
     ORDER BY resultat.date_passage DESC, resultat.id DESC'
);
$historyStatement->execute(['utilisateur_id' => $student['id']]);
$history = $historyStatement->fetchAll();

$rankingStatement = $pdo->prepare(
    'SELECT utilisateur_id, nom, SUM(meilleur_score) AS points
     FROM (
         SELECT utilisateur.id AS utilisateur_id, utilisateur.nom,
                resultat.quiz_id, MAX(resultat.score) AS meilleur_score
         FROM utilisateur
         INNER JOIN resultat ON resultat.utilisateur_id = utilisateur.id
         WHERE utilisateur.role = :role
         GROUP BY utilisateur.id, utilisateur.nom, resultat.quiz_id
     ) AS meilleurs_scores
     GROUP BY utilisateur_id, nom
     ORDER BY points DESC, nom ASC, utilisateur_id ASC'
);
$rankingStatement->execute(['role' => 'apprenant']);
$ranking = $rankingStatement->fetchAll();

$studentRank = null;
foreach ($ranking as $index => $rankedUser) {
    if ((int) $rankedUser['utilisateur_id'] === (int) $student['id']) {
        $studentRank = $index + 1;
        break;
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7fb">
    <title>Mes résultats — TechnoWorld Ed</title>
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
        <p class="tw-kicker mb-2">Votre parcours</p>
        <h1 class="tw-title">Mes <span class="tw-title-accent">résultats.</span></h1>
        <p class="tw-lead mt-3 mb-0">
            Retrouvez vos passages et comparez les meilleurs scores au classement.
        </p>
    </section>

    <div class="row g-4 align-items-start">
        <section class="col-lg-7" aria-labelledby="history-title">
            <div class="tw-section-heading mb-3">
                <div>
                    <p class="tw-kicker mb-2">Historique personnel</p>
                    <h2 class="h3 fw-bold mb-0" id="history-title">Passages de quiz</h2>
                </div>
            </div>
            <?php if ($history === []): ?>
                <div class="tw-empty-state">
                    <h3 class="h5 fw-bold">Aucun résultat pour le moment</h3>
                    <p class="text-secondary mb-0">Passez un quiz pour voir votre score ici.</p>
                </div>
            <?php else: ?>
                <div class="d-grid gap-3">
                    <?php foreach ($history as $result): ?>
                        <article class="tw-result-row">
                            <div>
                                <h3 class="h5 fw-bold mb-1"><?= h($result['quiz_titre']) ?></h3>
                                <p class="text-secondary mb-1"><?= h($result['cours_titre']) ?></p>
                                <time class="small text-secondary" datetime="<?= h($result['date_passage']) ?>">
                                    <?= h(date('d/m/Y à H:i', strtotime($result['date_passage']))) ?>
                                </time>
                            </div>
                            <span class="tw-result-score"><?= (int) $result['score'] ?> / <?= (int) $result['total'] ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <aside class="col-lg-5" aria-labelledby="ranking-title">
            <div class="tw-ranking-panel">
                <p class="tw-kicker mb-2">Classement apprenants</p>
                <h2 class="h3 fw-bold mb-1" id="ranking-title">Meilleurs scores</h2>
                <p class="small text-secondary mb-3">Somme des meilleurs scores par quiz.</p>
                <?php if ($studentRank !== null): ?>
                    <div class="tw-rank-highlight mb-3">
                        Votre rang <strong>#<?= (int) $studentRank ?></strong>
                        <span class="ms-auto"><?= (int) $ranking[$studentRank - 1]['points'] ?> points</span>
                    </div>
                <?php endif; ?>
                <?php if ($ranking === []): ?>
                    <p class="text-secondary mb-0">Le classement apparaîtra après les premiers quiz.</p>
                <?php else: ?>
                    <ol class="tw-ranking-list">
                        <?php foreach ($ranking as $index => $rankedUser): ?>
                            <li class="<?= (int) $rankedUser['utilisateur_id'] === (int) $student['id'] ? 'is-current' : '' ?>">
                                <span class="tw-ranking-position"><?= $index + 1 ?></span>
                                <span class="tw-ranking-name"><?= h($rankedUser['nom']) ?></span>
                                <strong><?= (int) $rankedUser['points'] ?> pts</strong>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</main>
<footer class="tw-shell tw-footer py-4">TechnoWorld Ed <span class="mx-1">·</span> Mes résultats</footer>
</body>
</html>
