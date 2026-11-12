<?php
// Permet à l'administrateur de créer, renommer et supprimer des catégories.
require __DIR__ . '/../../includes/bootstrap.php';
require_role('admin');

$success = $_SESSION['admin_category_flash'] ?? '';
unset($_SESSION['admin_category_flash']);
$errors = [];
$nameValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $action = $_POST['action'] ?? '';
    if (!is_string($action)) {
        http_response_code(400);
        exit('Action invalide.');
    }

    $categoryIdInput = $_POST['categorie_id'] ?? '';
    $categoryId = is_string($categoryIdInput)
        ? filter_var($categoryIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;

    if ($action === 'creer' || $action === 'modifier') {
        $nameInput = $_POST['nom'] ?? '';
        $nameValue = is_string($nameInput) ? trim($nameInput) : '';
        $nameLength = preg_match_all('/./us', $nameValue);
        if ($nameLength === false || $nameLength < 2 || $nameLength > 100) {
            $errors[] = 'Le nom de la catégorie doit contenir entre 2 et 100 caractères.';
        }

        if ($action === 'modifier') {
            if ($categoryId === false) {
                http_response_code(400);
                exit('Identifiant de catégorie invalide.');
            }
            $exists = $pdo->prepare('SELECT id FROM categorie WHERE id = :id');
            $exists->execute(['id' => $categoryId]);
            if (!$exists->fetchColumn()) {
                http_response_code(404);
                exit('Catégorie introuvable.');
            }
        }

        if ($errors === []) {
            try {
                if ($action === 'creer') {
                    $statement = $pdo->prepare(
                        'INSERT INTO categorie (nom) VALUES (:nom)'
                    );
                    $statement->execute(['nom' => $nameValue]);
                    $_SESSION['admin_category_flash'] = 'Catégorie créée.';
                } else {
                    $statement = $pdo->prepare(
                        'UPDATE categorie SET nom = :nom WHERE id = :id'
                    );
                    $statement->execute(['nom' => $nameValue, 'id' => $categoryId]);
                    $_SESSION['admin_category_flash'] = 'Catégorie modifiée.';
                }
                redirect(app_url('pages/admin/categories.php'));
            } catch (PDOException $exception) {
                $sqlState = $exception->errorInfo[0] ?? (string) $exception->getCode();
                if ($sqlState !== '23000') {
                    throw $exception;
                }
                $errors[] = 'Une catégorie porte déjà ce nom.';
            }
        }
    } elseif ($action === 'supprimer') {
        if ($categoryId === false) {
            http_response_code(400);
            exit('Identifiant de catégorie invalide.');
        }

        try {
            $statement = $pdo->prepare(
                'DELETE FROM categorie WHERE id = :id'
            );
            $statement->execute(['id' => $categoryId]);
            if ($statement->rowCount() !== 1) {
                http_response_code(404);
                exit('Catégorie introuvable.');
            }
            $_SESSION['admin_category_flash'] = 'Catégorie supprimée.';
            redirect(app_url('pages/admin/categories.php'));
        } catch (PDOException $exception) {
            $sqlState = $exception->errorInfo[0] ?? (string) $exception->getCode();
            if ($sqlState !== '23000') {
                throw $exception;
            }
            $errors[] = 'Cette catégorie est utilisée par un ou plusieurs cours et ne peut pas être supprimée.';
        }
    } else {
        http_response_code(400);
        exit('Action inconnue.');
    }
}

$categoriesStatement = $pdo->prepare(
    'SELECT categorie.id, categorie.nom, COUNT(cours.id) AS nombre_cours
     FROM categorie
     LEFT JOIN cours ON cours.categorie_id = categorie.id
     GROUP BY categorie.id, categorie.nom
     ORDER BY categorie.nom'
);
$categoriesStatement->execute();
$categories = $categoriesStatement->fetchAll();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7fb">
    <title>Catégories — TechnoWorld Ed</title>
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
        <p class="tw-kicker mb-2">Organisation du catalogue</p>
        <h1 class="tw-title">Gérer les <span class="tw-title-accent">catégories.</span></h1>
        <p class="tw-lead mt-3 mb-0">Les catégories utilisées par un cours ne peuvent pas être supprimées.</p>
    </section>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success rounded-3" role="status"><?= h($success) ?></div>
    <?php endif; ?>
    <?php if ($errors !== []): ?>
        <div class="alert alert-danger rounded-3" role="alert">
            <?php foreach ($errors as $error): ?><p class="mb-0"><?= h($error) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="row g-4 align-items-start">
        <section class="col-lg-5">
            <div class="tw-panel">
                <div class="tw-panel-body">
                    <h2 class="h5 fw-bold mb-3">Ajouter une catégorie</h2>
                    <form method="post" action="<?= h(app_url('pages/admin/categories.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="creer">
                        <label class="form-label" for="nouvelle-categorie">Nom</label>
                        <input class="form-control tw-form-control mb-3" id="nouvelle-categorie"
                               name="nom" maxlength="100" minlength="2" required value="<?= h($nameValue) ?>">
                        <button class="btn btn-tw-primary w-100" type="submit">Ajouter</button>
                    </form>
                    <a class="tw-back-link mt-4" href="<?= h(app_url('index.php')) ?>">
                        <span aria-hidden="true">←</span> Retour à l’administration
                    </a>
                </div>
            </div>
        </section>
        <section class="col-lg-7" aria-labelledby="categories-title">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 class="h4 fw-bold mb-0" id="categories-title">Catégories existantes</h2>
                <span class="tw-count-badge"><?= count($categories) ?></span>
            </div>
            <div class="d-grid gap-3">
                <?php foreach ($categories as $category): ?>
                    <article class="tw-account-card">
                        <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3">
                            <div>
                                <span class="fw-semibold"><?= h($category['nom']) ?></span>
                                <span class="small text-secondary ms-2">
                                    <?= (int) $category['nombre_cours'] ?> cours
                                </span>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <form class="d-flex gap-2" method="post"
                                      action="<?= h(app_url('pages/admin/categories.php')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="modifier">
                                    <input type="hidden" name="categorie_id" value="<?= (int) $category['id'] ?>">
                                    <label class="visually-hidden" for="categorie-<?= (int) $category['id'] ?>">Nom de catégorie</label>
                                    <input class="form-control form-control-sm" id="categorie-<?= (int) $category['id'] ?>"
                                           name="nom" maxlength="100" required value="<?= h($category['nom']) ?>">
                                    <button class="btn btn-sm btn-outline-primary rounded-pill" type="submit">Renommer</button>
                                </form>
                                <form method="post" action="<?= h(app_url('pages/admin/categories.php')) ?>"
                                      onsubmit="return confirm('Supprimer cette catégorie ?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="supprimer">
                                    <input type="hidden" name="categorie_id" value="<?= (int) $category['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger rounded-pill"
                                            type="submit" <?= (int) $category['nombre_cours'] > 0 ? 'disabled title="Catégorie utilisée par des cours"' : '' ?>>
                                        Supprimer
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</main>
<footer class="tw-shell tw-footer py-4">TechnoWorld Ed <span class="mx-1">·</span> Administration</footer>
</body>
</html>
