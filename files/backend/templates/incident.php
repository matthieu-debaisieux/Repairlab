<?php
/** @var array<string, mixed> $incident */
/** @var array<int, array<string, mixed>> $history */
/** @var array<int, string> $actions */
/** @var array<string, mixed> $user */
/** @var string $csrf */
/** @var ?string $flashError */
/** @var ?string $flashSuccess */

$flashError = $flashError ?? null;
$flashSuccess = $flashSuccess ?? null;
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$id = (int) $incident['id'];
$isStaff = in_array((string) ($user['role'] ?? ''), ['TECHNICIEN', 'ADMIN'], true);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Incident #<?= $id ?> — <?= $e($incident['title']) ?></title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 960px; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
        nav { display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; }
        form.box { display: grid; gap: .75rem; max-width: 480px; padding: 1rem; border: 1px solid #ccc; border-radius: .5rem; margin-bottom: 1rem; }
        input, select, button { padding: .6rem .8rem; font: inherit; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { border-bottom: 1px solid #ddd; padding: .65rem; text-align: left; }
        .error { padding: .75rem; border: 1px solid #b00020; color: #b00020; border-radius: .25rem; }
        .success { padding: .75rem; border: 1px solid #1b6e2d; color: #1b6e2d; border-radius: .25rem; }
    </style>
</head>
<body>
<nav>
    <a href="/equipements">Catalogue</a>
    <a href="/mon-espace">Espace personnel</a>
    <a href="/technicien">Tableau de bord technicien</a>
</nav>

<h1>Incident #<?= $id ?> — <?= $e($incident['title']) ?></h1>

<?php if (!empty($flashError)): ?>
    <p class="error" role="alert"><?= $e($flashError) ?></p>
<?php endif; ?>
<?php if (!empty($flashSuccess)): ?>
    <p class="success" role="status"><?= $e($flashSuccess) ?></p>
<?php endif; ?>

<ul>
    <li>Machine : <a href="/equipements/<?= (int) $incident['equipment_id'] ?>"><?= $e($incident['equipment']) ?></a></li>
    <li>État actuel : <strong><?= $e($incident['status']) ?></strong></li>
    <li>Panne bloquante (R3) : <strong><?= !empty($incident['blocking']) ? 'Oui' : 'Non' ?></strong></li>
    <li>Nature : <?= !empty($incident['technical']) ? 'Technique' : 'Autre' ?></li>
    <li>Signalé par : <?= $e($incident['author']) ?> le <?= $e($incident['reported_at']) ?></li>
    <?php if (!empty($incident['resolved_at'])): ?>
        <li>Résolu / clôturé le : <?= $e($incident['resolved_at']) ?></li>
    <?php endif; ?>
</ul>

<p><?= nl2br($e($incident['description'])) ?></p>

<?php if ($isStaff): ?>
    <h2>Actions technicien (R6 / R3)</h2>

    <?php if (!empty($actions)): ?>
        <form class="box" method="post" action="/incidents/<?= $id ?>/transition">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <label>
                Nouvel état
                <select name="nouvelEtat" required>
                    <?php foreach ($actions as $action): ?>
                        <option value="<?= $e($action) ?>"><?= $e($action) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Motif (obligatoire pour une réouverture vers OUVERT)
                <input type="text" name="motif" maxlength="255" placeholder="Ex. Panne réapparue après redémarrage">
            </label>
            <button type="submit">Appliquer la transition</button>
        </form>
    <?php endif; ?>

    <?php if (in_array((string) $incident['status'], ['OUVERT', 'EN_COURS'], true)): ?>
        <form class="box" method="post" action="/incidents/<?= $id ?>/bloquant">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="bloquant" value="<?= !empty($incident['blocking']) ? '0' : '1' ?>">
            <button type="submit">
                <?= !empty($incident['blocking']) ? 'Lever le blocage machine (R3)' : 'Déclarer en panne bloquante (R3)' ?>
            </button>
        </form>
    <?php endif; ?>
<?php endif; ?>

<h2>Historique des états (R6)</h2>
<?php if (empty($history)): ?>
    <p>Aucun changement enregistré.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Ancien état</th>
                <th>Nouvel état</th>
                <th>Auteur</th>
                <th>Motif</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($history as $entry): ?>
            <tr>
                <td><?= $e($entry['at']) ?></td>
                <td><?= $e($entry['from'] ?? '—') ?></td>
                <td><?= $e($entry['to']) ?></td>
                <td><?= $e($entry['author']) ?></td>
                <td><?= $e($entry['reason'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</body>
</html>