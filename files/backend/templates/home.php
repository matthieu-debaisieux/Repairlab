<?php
/** @var array<int, array<string, mixed>> $equipment */
/** @var array{q?: string, category?: string, status?: string} $filters */
/** @var array<int, array<string, mixed>> $categories */
/** @var ?string $error */
/** @var ?string $flashError */
/** @var ?string $flashSuccess */

$filters = $filters ?? ['q' => '', 'category' => '', 'status' => ''];
$categories = $categories ?? [];
$error = $error ?? null;
$flashError = $flashError ?? null;
$flashSuccess = $flashSuccess ?? null;
$qValue = (string) ($filters['q'] ?? '');
$categoryValue = (string) ($filters['category'] ?? '');
$statusValue = strtoupper((string) ($filters['status'] ?? ''));

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Équipements - La Fabrique Commune</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 960px; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
        nav { display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; }
        form.reserver { display: grid; gap: .75rem; max-width: 420px; padding: 1rem; border: 1px solid #ccc; border-radius: .5rem; }
        form.filters { display: flex; flex-wrap: wrap; gap: .75rem; align-items: end; padding: 1rem; border: 1px solid #ccc; border-radius: .5rem; margin-bottom: 1.5rem; }
        form.filters label { display: flex; flex-direction: column; gap: .25rem; font-size: .9rem; }
        input, select, button, a.btn { padding: .6rem .8rem; font: inherit; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { border-bottom: 1px solid #ddd; padding: .65rem; text-align: left; }
        .error { padding: .75rem; border: 1px solid #b00020; color: #b00020; border-radius: .25rem; }
        .success { padding: .75rem; border: 1px solid #1b6e2d; color: #1b6e2d; border-radius: .25rem; }
        .badge-hs { color: #991b1b; font-weight: 600; }
        .badge-ok { color: #166534; font-weight: 600; }
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

<h1>Catalogue des équipements — La Fabrique Commune</h1>

<?php if ($error !== null): ?>
    <p class="error" role="alert"><?= $e($error) ?></p>
<?php endif; ?>

<?php if (!empty($flashError)): ?>
    <p class="error" role="alert"><?= $e($flashError) ?></p>
<?php endif; ?>

<?php if (!empty($flashSuccess)): ?>
    <p class="success" role="status"><?= $e($flashSuccess) ?></p>
<?php endif; ?>

<form class="filters" method="get" action="/equipements">
    <label>
        Recherche (nom, lieu)
        <input type="search" name="q" value="<?= $e($qValue) ?>" placeholder="Ex. Prusa, Découpe...">
    </label>

    <label>
        Catégorie
        <select name="category">
            <option value="">Toutes</option>
            <?php foreach ($categories as $cat): ?>
                <?php
                $catId = (string) ($cat['id_categorie'] ?? $cat['id'] ?? '');
                $catLabel = (string) ($cat['libelle'] ?? $cat['name'] ?? '');
                ?>
                <option value="<?= $e($catId) ?>" <?= $categoryValue === $catId ? 'selected' : '' ?>>
                    <?= $e($catLabel) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>
        Statut technique
        <select name="status">
            <option value="">Tous</option>
            <option value="EN_SERVICE" <?= $statusValue === 'EN_SERVICE' ? 'selected' : '' ?>>EN_SERVICE</option>
            <option value="HORS_SERVICE" <?= $statusValue === 'HORS_SERVICE' ? 'selected' : '' ?>>HORS_SERVICE</option>
        </select>
    </label>

    <button type="submit">Filtrer</button>
    <a class="btn" href="/equipements">Réinitialiser</a>
</form>

<?php if (empty($equipment)): ?>
    <p>Aucun équipement ne correspond aux critères sélectionnés.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Nom</th>
                <th>Catégorie</th>
                <th>Statut</th>
                <th>Localisation</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($equipment as $item): ?>
            <?php
            $id = (int) ($item['id'] ?? 0);
            $equipmentStatus = (string) ($item['status'] ?? '');
            $isHs = strtoupper($equipmentStatus) === 'HORS_SERVICE';
            ?>
            <tr>
                <td>
                    <a href="/equipements/<?= $id ?>"><?= $e($item['name'] ?? '') ?></a>
                </td>
                <td>
                    <?= $e($item['category'] ?? '') ?>
                </td>
                <td>
                    <span class="<?= $isHs ? 'badge-hs' : 'badge-ok' ?>">
                        <?= $e($equipmentStatus) ?>
                    </span>
                </td>
                <td>
                    <?= $e($item['localisation'] ?? '') ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</body>
</html>