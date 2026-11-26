<?php
    /** @var array $equipment */
    /** @var array $slots */
    /** @var ?array $user */
    /** @var string $csrf */
    /** @var ?string $flashError */
    /** @var ?string $flashSuccess */
    $flashError = $flashError ?? null;
    $flashSuccess = $flashSuccess ?? null;
    $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>

<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($equipment['name']) ?> - La Fabrique Commune</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 960px; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
        nav { display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; }
        form.reserver { display: grid; gap: .75rem; max-width: 420px; padding: 1rem; border: 1px solid #ccc; border-radius: .5rem; }
        input, select, button { padding: .65rem; font: inherit; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { border-bottom: 1px solid #ddd; padding: .65rem; text-align: left; }
        .error { padding: .75rem; border: 1px solid #b00020; color: #b00020; border-radius: .25rem; }
        .success { padding: .75rem; border: 1px solid #1b6e2d; color: #1b6e2d; border-radius: .25rem; }
    </style>
</head>
<body>
    
<nav>
    <a href="/equipements">Catalogue</a>
    <a href="/login">Connexion</a>
    <a href="/mon-espace">Espace personnel</a>
    <a href="/technicien">Tableau de bord technicien</a>
    <a href="/api/equipment">API</a>
</nav>

<p><a href="/equipements">&larr; Retour au catalogue</a></p>
<h1><?= $e($equipment['name']) ?></h1>

<?php if (!empty($flashError)): ?>
    <p class="error" role="alert"><?= $e($flashError) ?></p>
<?php endif; ?>
<?php if (!empty($flashSuccess)): ?>
    <p class="success" role="status"><?= $e($flashSuccess) ?></p>
<?php endif; ?>

<ul>
    <li>Catégorie : <?= $e($equipment['category'] ?? '') ?></li>
    <li>Localisation : <?= $e($equipment['localisation'] ?? '') ?></li>
    <li>Statut technique : <?= $e($equipment['status']) ?></li>
</ul>

<h2>Créneaux déjà réservés</h2>
<?php if ($slots === []): ?>
    <p>Aucun créneau réservé à venir.</p>
<?php else: ?>
    <table>
        <thead><tr><th>Début</th><th>Fin</th></tr></thead>
        <tbody>
        <?php foreach ($slots as $slot): ?>
            <tr><td><?= $e($slot['start']) ?></td><td><?= $e($slot['end']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<h2>Réserver cette machine</h2>
<?php if ($user === null): ?>
    <p><a href="/login">Connectez-vous</a> pour réserver.</p>
<?php else: ?>
    <form class="reserver" method="post" action="/reservations">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="equipmentId" value="<?= (int) $equipment['id'] ?>">
        <label>Début
            <input type="datetime-local" name="debut" required>
        </label>
        <label>Fin
            <input type="datetime-local" name="fin" required>
        </label>
        <button type="submit">Réserver</button>
    </form>
<?php endif; ?>
</body>
</html>