<?php
/** @var array<string, mixed> $user */
/** @var array<int, array<string, mixed>> $reservations */
/** @var array<int, array<string, mixed>> $incidents */
/** @var string $csrf */
/** @var ?string $flashError */
/** @var ?string $flashSuccess */

$flashError = $flashError ?? null;
$flashSuccess = $flashSuccess ?? null;
$reservations = $reservations ?? [];
$incidents = $incidents ?? [];
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mon espace — RepairLab</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 960px; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
        nav { display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; align-items: center; }
        form.inline { display: inline; }
        .section-head { display: flex; justify-content: space-between; align-items: center; gap: .75rem; flex-wrap: wrap; margin-top: 1.5rem; }
        .mini-filter { display: flex; gap: .4rem; align-items: center; font-size: .9rem; }
        .mini-filter select, .mini-filter input { padding: .3rem .5rem; font: inherit; }
        table { width: 100%; border-collapse: collapse; margin-top: .6rem; margin-bottom: 1.5rem; }
        th, td { border-bottom: 1px solid #ddd; padding: .6rem; text-align: left; }
        button { padding: .4rem .7rem; font: inherit; cursor: pointer; }
        .error { padding: .75rem; border: 1px solid #b00020; color: #b00020; border-radius: .25rem; }
        .success { padding: .75rem; border: 1px solid #1b6e2d; color: #1b6e2d; border-radius: .25rem; }
    </style>
</head>
<body>
<nav>
    <a href="/equipements">Catalogue</a>
    <a href="/mon-espace">Espace personnel</a>
    <?php if (in_array((string) ($user['role'] ?? ''), ['TECHNICIEN', 'ADMIN'], true)): ?>
        <a href="/technicien">Tableau de bord technicien</a>
    <?php endif; ?>
    <form class="inline" method="post" action="/logout">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <button type="submit">Se déconnecter</button>
    </form>
</nav>

<h1>Mon espace</h1>
<p>Connecté en tant que <strong><?= $e($user['nom'] ?? $user['email'] ?? 'Utilisateur') ?></strong> (<?= $e($user['role'] ?? '') ?>).</p>

<?php if (!empty($flashError)): ?>
    <p class="error" role="alert"><?= $e($flashError) ?></p>
<?php endif; ?>
<?php if (!empty($flashSuccess)): ?>
    <p class="success" role="status"><?= $e($flashSuccess) ?></p>
<?php endif; ?>

<div class="section-head">
    <h2>Mes réservations</h2>
    <div class="mini-filter">
        <select id="res-statut" aria-label="Filtrer les réservations">
            <option value="ACTIVES" selected>Actives</option>
            <option value="TOUTES">Toutes</option>
            <option value="CONFIRMEE">CONFIRMEE</option>
            <option value="EN_ATTENTE">EN_ATTENTE</option>
            <option value="ANNULEE">ANNULEE</option>
            <option value="TERMINEE">TERMINEE</option>
        </select>
        <input id="res-q" type="search" placeholder="Machine..." aria-label="Rechercher une réservation">
    </div>
</div>

<?php if (empty($reservations)): ?>
    <p>Aucune réservation enregistrée pour le moment. <a href="/equipements">Parcourir les équipements</a>.</p>
<?php else: ?>
    <table id="table-reservations">
        <thead>
            <tr>
                <th>#</th>
                <th>Équipement</th>
                <th>Début</th>
                <th>Fin</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($reservations as $res): ?>
            <?php
            $resId = (int) ($res['id'] ?? $res['id_reservation'] ?? 0);
            $equipId = (int) ($res['equipment_id'] ?? $res['id_equipement'] ?? 0);
            $equipName = (string) ($res['equipment'] ?? $res['equipement'] ?? $res['equipement_nom'] ?? ('Équipement #' . $equipId));
            $debut = (string) ($res['debut'] ?? $res['date_debut'] ?? '');
            $fin = (string) ($res['fin'] ?? $res['date_fin'] ?? '');
            $statut = strtoupper((string) ($res['statut'] ?? $res['status'] ?? ''));
            ?>
            <tr data-statut="<?= $e($statut) ?>">
                <td><?= $resId ?></td>
                <td>
                    <?php if ($equipId > 0): ?>
                        <a href="/equipements/<?= $equipId ?>"><?= $e($equipName) ?></a>
                    <?php else: ?>
                        <?= $e($equipName) ?>
                    <?php endif; ?>
                </td>
                <td><?= $e($debut) ?></td>
                <td><?= $e($fin) ?></td>
                <td><strong><?= $e($statut) ?></strong></td>
                <td>
                    <?php if ($resId > 0 && !in_array($statut, ['ANNULEE', 'TERMINEE'], true)): ?>
                        <form class="inline" method="post" action="/reservations/<?= $resId ?>/annuler">
                            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                            <button type="submit">Annuler</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<div class="section-head">
    <h2>Mes incidents</h2>
    <div class="mini-filter">
        <select id="inc-statut" aria-label="Filtrer les incidents">
            <option value="ACTIFS" selected>Non clôturés</option>
            <option value="TOUS">Tous</option>
            <option value="OUVERT">OUVERT</option>
            <option value="EN_COURS">EN_COURS</option>
            <option value="RESOLU">RESOLU</option>
            <option value="CLOTURE">CLOTURE</option>
        </select>
        <input id="inc-q" type="search" placeholder="Titre, machine..." aria-label="Rechercher un incident">
    </div>
</div>

<?php if (empty($incidents)): ?>
    <p>Aucun incident listé ici. Tu peux ouvrir directement un ticket via <a href="/incidents/1">/incidents/{id}</a> ou signaler un problème depuis la fiche d'un équipement.</p>
<?php else: ?>
    <table id="table-incidents">
        <thead>
            <tr>
                <th>#</th>
                <th>Titre</th>
                <th>Équipement</th>
                <th>État</th>
                <th>Lien</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($incidents as $inc): ?>
            <?php
            $incId = (int) ($inc['id'] ?? $inc['id_incident'] ?? 0);
            $incTitle = (string) ($inc['title'] ?? $inc['titre'] ?? ('Incident #' . $incId));
            $incEquip = (string) ($inc['equipment'] ?? $inc['equipement'] ?? '');
            $incStatus = strtoupper((string) ($inc['status'] ?? $inc['statut'] ?? ''));
            ?>
            <tr data-statut="<?= $e($incStatus) ?>">
                <td><?= $incId ?></td>
                <td><?= $e($incTitle) ?></td>
                <td><?= $e($incEquip) ?></td>
                <td><strong><?= $e($incStatus) ?></strong></td>
                <td>
                    <?php if ($incId > 0): ?>
                        <a href="/incidents/<?= $incId ?>">Voir l'incident</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<script>
(() => {
    function bindFilter(selectId, inputId, tableId, hiddenStates) {
        const sel = document.getElementById(selectId);
        const inp = document.getElementById(inputId);
        const tbl = document.getElementById(tableId);
        if (!tbl) return;
        const rows = Array.from(tbl.querySelectorAll('tbody tr'));

        const run = () => {
            const mode = sel ? sel.value : 'TOUS';
            const q = inp ? inp.value.trim().toLowerCase() : '';
            rows.forEach((tr) => {
                const st = (tr.dataset.statut || '').toUpperCase();
                const txt = tr.textContent.toLowerCase();
                const okStatut =
                    (mode === 'ACTIVES' || mode === 'ACTIFS') ? !hiddenStates.includes(st) :
                    (mode === 'TOUS' || mode === 'TOUTES') ? true :
                    st === mode;
                const okText = q === '' || txt.includes(q);
                tr.style.display = (okStatut && okText) ? '' : 'none';
            });
        };

        if (sel) sel.addEventListener('change', run);
        if (inp) inp.addEventListener('input', run);
        run();
    }

    bindFilter('res-statut', 'res-q', 'table-reservations', ['ANNULEE', 'TERMINEE']);
    bindFilter('inc-statut', 'inc-q', 'table-incidents', ['CLOTURE']);
})();
</script>
</body>
</html>