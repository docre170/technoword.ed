<?php
// Crée un compte apprenant après validation des champs et du jeton CSRF.
require __DIR__ . '/../includes/bootstrap.php';

if (current_user() !== null) {
    redirect(app_url('index.php'));
}

$errors = [];
$nom = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $nomInput = $_POST['nom'] ?? '';
    $emailInput = $_POST['email'] ?? '';
    $password = $_POST['mot_de_passe'] ?? '';
    $passwordConfirmation = $_POST['confirmation'] ?? '';

    $nom = is_string($nomInput) ? trim($nomInput) : '';
    $email = is_string($emailInput) ? trim($emailInput) : '';

    $nomLength = preg_match_all('/./us', $nom);
    if ($nomLength === false || $nomLength < 1 || $nomLength > 100) {
        $errors[] = 'Le nom doit contenir entre 1 et 100 caractères.';
    }

    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Veuillez saisir une adresse e-mail valide (254 caractères maximum).';
    }

    if (!is_string($password) || strlen($password) < 8 || strlen($password) > 255) {
        $errors[] = 'Le mot de passe doit contenir entre 8 et 255 caractères.';
    }

    if (!is_string($passwordConfirmation) || !is_string($password)
        || !hash_equals($password, $passwordConfirmation)) {
        $errors[] = 'La confirmation du mot de passe ne correspond pas.';
    }

    if ($errors === []) {
        $statement = $pdo->prepare('SELECT id FROM utilisateur WHERE email = :email');
        $statement->execute(['email' => $email]);

        if ($statement->fetch()) {
            $errors[] = 'Cette adresse e-mail est déjà utilisée.';
        } else {
            try {
                $statement = $pdo->prepare(
                    'INSERT INTO utilisateur (nom, email, mot_de_passe, role)
                     VALUES (:nom, :email, :mot_de_passe, :role)'
                );
                $statement->execute([
                    'nom' => $nom,
                    'email' => $email,
                    'mot_de_passe' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => 'apprenant',
                ]);

                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $pdo->lastInsertId();
                redirect(app_url('index.php'));
            } catch (PDOException $exception) {
                $sqlState = $exception->errorInfo[0] ?? (string) $exception->getCode();
                if ($sqlState !== '23000') {
                    throw $exception;
                }

                $errors[] = 'Cette adresse e-mail est déjà utilisée.';
            }
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
    <title>Inscription — TechnoWorld Ed</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= h(app_url('assets/css/app.css')) ?>" rel="stylesheet">
    <script>document.documentElement.dataset.theme = localStorage.getItem('tw-theme') || 'light';</script>
    <script src="<?= h(app_url('assets/js/app.js')) ?>" defer></script>
</head>
<body>
<header class="tw-topbar">
    <div class="tw-shell d-flex align-items-center justify-content-between gap-3">
        <a class="tw-brand" href="<?= h(app_url('pages/register.php')) ?>">
            <span class="tw-brand-mark" aria-hidden="true">TW</span>
            <span>TechnoWorld <span class="text-primary">Ed</span></span>
        </a>
        <button class="btn btn-outline-secondary rounded-pill px-3 tw-theme-toggle"
                type="button" data-theme-toggle aria-pressed="false">Thème sombre</button>
    </div>
</header>
<main class="tw-shell tw-main">
    <div class="row align-items-stretch justify-content-center g-4 g-lg-5">
        <section class="col-lg-5 d-flex align-items-center">
            <div class="py-3">
                <p class="tw-kicker mb-3">Un espace pour progresser</p>
                <h1 class="tw-title">Faites grandir <span class="tw-title-accent">vos idées.</span></h1>
                <p class="tw-lead mt-4 mb-0">
                    Créez votre compte et rejoignez une communauté universitaire tournée vers
                    l’informatique et la biomédecine.
                </p>
            </div>
        </section>
        <section class="col-lg-5">
            <div class="tw-panel">
                <div class="tw-panel-body">
                    <h2 class="h3 fw-bold mb-1">Créer un compte</h2>
                    <p class="text-secondary mb-4">L'inscription crée un compte apprenant.</p>
                    <?php if ($errors !== []): ?>
                        <div class="alert alert-danger rounded-3" role="alert">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= h($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <form method="post" action="register.php">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="nom">Nom</label>
                            <input class="form-control tw-form-control" id="nom" name="nom" type="text"
                                   maxlength="100" required value="<?= h($nom) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="email">Adresse e-mail</label>
                            <input class="form-control tw-form-control" id="email" name="email" type="email"
                                   maxlength="254" required value="<?= h($email) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="mot_de_passe">Mot de passe</label>
                            <input class="form-control tw-form-control" id="mot_de_passe" name="mot_de_passe"
                                   type="password" minlength="8" maxlength="255" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="confirmation">Confirmer le mot de passe</label>
                            <input class="form-control tw-form-control" id="confirmation" name="confirmation"
                                   type="password" minlength="8" maxlength="255" required>
                        </div>
                        <button class="btn btn-tw-primary w-100" type="submit">Créer mon compte</button>
                    </form>
                    <p class="text-center text-secondary mt-4 mb-0">
                        Déjà inscrit ? <a class="fw-semibold" href="login.php">Se connecter</a>
                    </p>
                </div>
            </div>
        </section>
    </div>
</main>
<footer class="tw-shell tw-footer py-4">TechnoWorld Ed <span class="mx-1">·</span> Plateforme universitaire</footer>
</body>
</html>
