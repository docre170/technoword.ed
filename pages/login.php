<?php
// Authentifie un utilisateur à l'aide de son adresse e-mail et de son mot de passe.
require __DIR__ . '/../includes/bootstrap.php';

if (current_user() !== null) {
    redirect(app_url('index.php'));
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $emailInput = $_POST['email'] ?? '';
    $password = $_POST['mot_de_passe'] ?? '';
    $email = is_string($emailInput) ? trim($emailInput) : '';

    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || !is_string($password) || strlen($password) < 1 || strlen($password) > 255) {
        $error = 'Adresse e-mail ou mot de passe invalide.';
    } else {
        $statement = $pdo->prepare(
            'SELECT id, mot_de_passe FROM utilisateur WHERE email = :email'
        );
        $statement->execute(['email' => $email]);
        $account = $statement->fetch();

        if ($account && password_verify($password, $account['mot_de_passe'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $account['id'];
            redirect(app_url('index.php'));
        }

        $error = 'Adresse e-mail ou mot de passe incorrect.';
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7fb">
    <title>Connexion — TechnoWorld Ed</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= h(app_url('assets/css/app.css')) ?>" rel="stylesheet">
    <script>document.documentElement.dataset.theme = localStorage.getItem('tw-theme') || 'light';</script>
    <script src="<?= h(app_url('assets/js/app.js')) ?>" defer></script>
</head>
<body>
<header class="tw-topbar">
    <div class="tw-shell d-flex align-items-center justify-content-between gap-3">
        <a class="tw-brand" href="<?= h(app_url('pages/login.php')) ?>">
            <span class="tw-brand-mark" aria-hidden="true">TW</span>
            <span>TechnoWorld <span class="text-primary">Ed</span></span>
        </a>
        <button class="tw-theme-toggle" type="button" data-theme-toggle
                data-state="light" aria-pressed="false">
            <span class="tw-toggle-icon" aria-hidden="true">☾</span>
            <span class="tw-toggle-label">Thème sombre</span>
            <span class="tw-toggle-track" aria-hidden="true"><span class="tw-toggle-thumb"></span></span>
        </button>
    </div>
</header>
<main class="tw-shell tw-main">
    <div class="row align-items-stretch justify-content-center g-4 g-lg-5">
        <section class="col-lg-5 d-flex align-items-center">
            <div class="py-3">
                <p class="tw-kicker mb-3">Heureux de vous revoir</p>
                <h1 class="tw-title">Reprenez là où <span class="tw-title-accent">vous en êtes.</span></h1>
                <p class="tw-lead mt-4 mb-0">
                    Connectez-vous à votre espace d’apprentissage en informatique et biomédecine.
                </p>
            </div>
        </section>
        <section class="col-lg-5">
            <div class="tw-panel">
                <div class="tw-panel-body">
                    <h2 class="h3 fw-bold mb-1">Connexion</h2>
                    <p class="text-secondary mb-4">Saisissez vos identifiants pour continuer.</p>
                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger rounded-3" role="alert"><?= h($error) ?></div>
                    <?php endif; ?>
                    <form method="post" action="login.php">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="email">Adresse e-mail</label>
                            <input class="form-control tw-form-control" id="email" name="email" type="email"
                                   maxlength="254" required value="<?= h($email) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="mot_de_passe">Mot de passe</label>
                            <input class="form-control tw-form-control" id="mot_de_passe" name="mot_de_passe"
                                   type="password" maxlength="255" required>
                        </div>
                        <button class="btn btn-tw-primary w-100" type="submit">Se connecter</button>
                    </form>
                    <p class="text-center text-secondary mt-4 mb-0">
                        Pas encore de compte ? <a class="fw-semibold" href="register.php">S'inscrire</a>
                    </p>
                </div>
            </div>
        </section>
    </div>
</main>
<footer class="tw-shell tw-footer py-4">TechnoWorld Ed <span class="mx-1">·</span> Plateforme universitaire</footer>
</body>
</html>
