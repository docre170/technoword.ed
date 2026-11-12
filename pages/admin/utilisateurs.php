<?php
// Permet à l'administrateur de créer des comptes apprenant ou enseignant.
require __DIR__ . '/../../includes/bootstrap.php';
$admin = require_role('admin');

$errors = [];
$success = $_SESSION['admin_user_flash'] ?? '';
unset($_SESSION['admin_user_flash']);
$nom = '';
$email = '';
$role = 'apprenant';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $actionInput = $_POST['action'] ?? 'creer_compte';
    if (!is_string($actionInput)) {
        http_response_code(400);
        exit('Action invalide.');
    }

    if ($actionInput === 'modifier_role' || $actionInput === 'supprimer_compte') {
        $targetIdInput = $_POST['utilisateur_id'] ?? '';
        $targetId = is_string($targetIdInput)
            ? filter_var($targetIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        if ($targetId === false) {
            http_response_code(400);
            exit('Identifiant de compte invalide.');
        }

        $targetStatement = $pdo->prepare(
            'SELECT id, role FROM utilisateur WHERE id = :id'
        );
        $targetStatement->execute(['id' => $targetId]);
        $targetUser = $targetStatement->fetch();
        if (!$targetUser) {
            http_response_code(404);
            exit('Compte introuvable.');
        }

        $adminCountStatement = $pdo->prepare(
            'SELECT COUNT(*) FROM utilisateur WHERE role = :role'
        );
        $adminCountStatement->execute(['role' => 'admin']);
        $adminCount = (int) $adminCountStatement->fetchColumn();

        if ($actionInput === 'modifier_role') {
            $newRoleInput = $_POST['nouveau_role'] ?? '';
            if (!is_string($newRoleInput)
                || !in_array($newRoleInput, ['apprenant', 'enseignant', 'admin'], true)) {
                http_response_code(400);
                exit('Rôle invalide.');
            }
            if ((int) $targetUser['id'] === (int) $admin['id'] && $newRoleInput !== 'admin') {
                $errors[] = 'Vous ne pouvez pas retirer votre propre rôle administrateur.';
            } elseif ($targetUser['role'] === 'admin' && $newRoleInput !== 'admin' && $adminCount <= 1) {
                $errors[] = 'Le dernier administrateur ne peut pas être rétrogradé.';
            } else {
                $updateStatement = $pdo->prepare(
                    'UPDATE utilisateur SET role = :role WHERE id = :id'
                );
                $updateStatement->execute(['role' => $newRoleInput, 'id' => $targetId]);
                $_SESSION['admin_user_flash'] = 'Rôle du compte mis à jour.';
                redirect(app_url('pages/admin/utilisateurs.php'));
            }
        } else {
            if ((int) $targetUser['id'] === (int) $admin['id']) {
                $errors[] = 'Vous ne pouvez pas supprimer votre propre compte administrateur.';
            } elseif ($targetUser['role'] === 'admin' && $adminCount <= 1) {
                $errors[] = 'Le dernier administrateur ne peut pas être supprimé.';
            } else {
                $deleteStatement = $pdo->prepare(
                    'DELETE FROM utilisateur WHERE id = :id'
                );
                $deleteStatement->execute(['id' => $targetId]);
                $_SESSION['admin_user_flash'] = 'Compte supprimé.';
                redirect(app_url('pages/admin/utilisateurs.php'));
            }
        }
    } elseif ($actionInput !== 'creer_compte') {
        http_response_code(400);
        exit('Action inconnue.');
    }

    if ($actionInput === 'creer_compte') {
    $nomInput = $_POST['nom'] ?? '';
    $emailInput = $_POST['email'] ?? '';
    $roleInput = $_POST['role'] ?? '';
    $password = $_POST['mot_de_passe'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';

    $nom = is_string($nomInput) ? trim($nomInput) : '';
    $email = is_string($emailInput) ? trim($emailInput) : '';
    $role = is_string($roleInput) ? $roleInput : '';

    $nomLength = preg_match_all('/./us', $nom);
    if ($nomLength === false || $nomLength < 1 || $nomLength > 100) {
        $errors[] = 'Le nom doit contenir entre 1 et 100 caractères.';
    }

    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Veuillez saisir une adresse e-mail valide (254 caractères maximum).';
    }

    if (!in_array($role, ['apprenant', 'enseignant'], true)) {
        $errors[] = 'Le rôle doit être apprenant ou enseignant.';
    }

    if (!is_string($password) || strlen($password) < 8 || strlen($password) > 255) {
        $errors[] = 'Le mot de passe doit contenir entre 8 et 255 caractères.';
    }

    if (!is_string($confirmation) || !is_string($password)
        || !hash_equals($password, $confirmation)) {
        $errors[] = 'La confirmation du mot de passe ne correspond pas.';
    }

    if ($errors === []) {
        $statement = $pdo->prepare(
            'SELECT id FROM utilisateur WHERE email = :email'
        );
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
                    'role' => $role,
                ]);
                $_SESSION['admin_user_flash'] = 'Le compte '
                    . ($role === 'enseignant' ? 'enseignant' : 'apprenant')
                    . ' a été créé.';
                redirect(app_url('pages/admin/utilisateurs.php'));
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
}

$usersStatement = $pdo->prepare(
    'SELECT id, nom, email, role, created_at
     FROM utilisateur
     ORDER BY role, nom, id'
);
$usersStatement->execute();
$users = $usersStatement->fetchAll();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7fb">
    <title>Créer un compte — TechnoWorld Ed</title>
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
    <section class="tw-admin-hero mb-4">
        <p class="tw-kicker mb-2">Administration des accès</p>
        <h1 class="tw-title">Gérer les <span class="tw-title-accent">comptes.</span></h1>
        <p class="tw-lead mt-3 mb-0">
            Créez des apprenants ou enseignants, modifiez les rôles et supprimez les comptes nécessaires.
        </p>
    </section>

    <div class="row g-4 align-items-start">
        <div class="col-lg-5">
            <section class="tw-panel">
                <div class="tw-panel-body">
                    <?php if ($success !== ''): ?>
                        <div class="alert alert-success rounded-3" role="status"><?= h($success) ?></div>
                    <?php endif; ?>
                    <?php if ($errors !== []): ?>
                        <div class="alert alert-danger rounded-3" role="alert">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <form method="post" action="<?= h(app_url('pages/admin/utilisateurs.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="creer_compte">
                        <div class="mb-3">
                            <label class="form-label" for="nom">Nom complet</label>
                            <input class="form-control tw-form-control" id="nom" name="nom"
                                   type="text" maxlength="100" required value="<?= h($nom) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="email">Adresse e-mail</label>
                            <input class="form-control tw-form-control" id="email" name="email"
                                   type="email" maxlength="254" required value="<?= h($email) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="role">Rôle du compte</label>
                            <select class="form-select tw-form-control" id="role" name="role" required>
                                <option value="apprenant" <?= $role === 'apprenant' ? 'selected' : '' ?>>Apprenant</option>
                                <option value="enseignant" <?= $role === 'enseignant' ? 'selected' : '' ?>>Enseignant</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="mot_de_passe">Mot de passe temporaire</label>
                            <input class="form-control tw-form-control" id="mot_de_passe" name="mot_de_passe"
                                   type="password" minlength="8" maxlength="255" required>
                            <div class="form-text">Au moins 8 caractères.</div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="confirmation">Confirmer le mot de passe</label>
                            <input class="form-control tw-form-control" id="confirmation" name="confirmation"
                                   type="password" minlength="8" maxlength="255" required>
                        </div>
                        <button class="btn btn-tw-primary w-100" type="submit">Créer le compte</button>
                    </form>
                    <a class="tw-back-link mt-4" href="<?= h(app_url('index.php')) ?>">
                        <span aria-hidden="true">←</span> Retour à l’administration
                    </a>
                </div>
            </section>
        </div>
        <section class="col-lg-7" aria-labelledby="accounts-title">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                <h2 class="h4 fw-bold mb-0" id="accounts-title">Comptes existants</h2>
                <span class="tw-count-badge"><?= count($users) ?></span>
            </div>
            <div class="d-grid gap-3">
                <?php foreach ($users as $account): ?>
                    <article class="tw-account-card">
                        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
                            <div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <h3 class="h6 fw-bold mb-0"><?= h($account['nom']) ?></h3>
                                    <span class="tw-role"><?= h($account['role']) ?></span>
                                    <?php if ((int) $account['id'] === (int) $admin['id']): ?>
                                        <span class="small text-secondary">Vous</span>
                                    <?php endif; ?>
                                </div>
                                <p class="small text-secondary mb-0 mt-1"><?= h($account['email']) ?></p>
                            </div>
                            <div class="d-flex flex-wrap gap-2 align-items-start">
                                <form class="d-flex gap-2" method="post"
                                      action="<?= h(app_url('pages/admin/utilisateurs.php')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="modifier_role">
                                    <input type="hidden" name="utilisateur_id" value="<?= (int) $account['id'] ?>">
                                    <label class="visually-hidden" for="role-<?= (int) $account['id'] ?>">Nouveau rôle</label>
                                    <select class="form-select form-select-sm" id="role-<?= (int) $account['id'] ?>"
                                            name="nouveau_role" aria-label="Rôle pour <?= h($account['nom']) ?>">
                                        <?php foreach (['apprenant' => 'Apprenant', 'enseignant' => 'Enseignant', 'admin' => 'Admin'] as $roleValue => $roleLabel): ?>
                                            <option value="<?= h($roleValue) ?>"
                                                <?= $account['role'] === $roleValue ? 'selected' : '' ?>>
                                                <?= h($roleLabel) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-sm btn-outline-primary rounded-pill" type="submit">Modifier</button>
                                </form>
                                <?php if ((int) $account['id'] !== (int) $admin['id']): ?>
                                    <form method="post" action="<?= h(app_url('pages/admin/utilisateurs.php')) ?>"
                                          onsubmit="return confirm('Supprimer ce compte et ses données associées ?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="supprimer_compte">
                                        <input type="hidden" name="utilisateur_id" value="<?= (int) $account['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger rounded-pill" type="submit">Supprimer</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</main>
<footer class="tw-shell tw-footer py-4">
    TechnoWorld Ed <span class="mx-1">·</span> Administration
</footer>
</body>
</html>
