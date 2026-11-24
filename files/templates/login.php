<?php
/** @var ?string $error */
/** @var string $email */
/** @var string $csrf */
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 480px; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
        form { display: grid; gap: .75rem; padding: 1rem; border: 1px solid #ccc; border-radius: .5rem; }
        input, button { padding: .65rem; font: inherit; }
        .error { padding: .75rem; border: 1px solid currentColor; }
    </style>
</head>
<body>
<h1>Connexion</h1>

<?php if ($error !== null): ?>
    <p class="error" role="alert">
        <?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
    </p>
<?php endif; ?>

<form method="post" action="/login">
    <input type="hidden" name="csrf"
           value="<?= htmlspecialchars($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

    <label>
        Adresse e-mail
        <input type="email" name="email" required maxlength="190" autocomplete="username"
               value="<?= htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    </label>

    <label>
        Mot de passe
        <input type="password" name="password" required maxlength="200" autocomplete="current-password">
    </label>

    <button type="submit">Se connecter</button>
</form>

<p><a href="/">Retour au catalogue</a></p>
</body>
</html>
