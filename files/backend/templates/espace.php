<?php
/** @var string $title */
/** @var array $user */
/** @var string $csrf */
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 480px; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
        form { display: grid; gap: .75rem; padding: 1rem; border: 1px solid #ccc; border-radius: .5rem; }
        input, button { padding: .65rem; font: inherit; }
        .error { padding: .75rem; border: 1px solid currentColor; }
    </style>
</head>
<body>
<h1><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>

<p>
    Connecté en tant que
    <strong><?= htmlspecialchars($user['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
    (<?= htmlspecialchars($user['role'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>).
</p>

<form method="post" action="/logout">
    <input type="hidden" name="csrf"
           value="<?= htmlspecialchars($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <button type="submit">Se déconnecter</button>
</form>

<p><a href="/">Catalogue</a> · <a href="/technicien">Espace technicien</a></p>
</body>
</html>
