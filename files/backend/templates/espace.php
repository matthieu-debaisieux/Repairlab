<?php
/** @var string $title */
/** @var array $user */
/** @var string $csrf */
/** @var ?array $dashboard */
$dashboard = $dashboard ?? null;
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$isStaff = in_array($user['role'], ['TECHNICIEN', 'ADMIN'], true);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($title) ?></title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 900px; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
        form.inline { display: inline; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 1.5rem; }
        th, td { border-bottom: 1px solid #ccc; padding: .4rem .5rem; text-align: left; }
        .kpis { display: flex; gap: 1rem; flex-wrap: wrap; margin: 1rem 0; }
        .kpi { border: 1px solid #ccc; border-radius: .5rem; padding: .75rem 1rem; }
        .kpi strong { display: block; font-size: 1.6rem; }
    </style>
</head>
<body>
<h1><?= $e($title) ?></h1>

<p>
    Connecté en tant que <strong><?= $e($user['name']) ?></strong> (<?= $e($user['role']) ?>).
</p>

<form class="inline" method="post" action="/logout">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <button type="submit">Se déconnecter</button>
</form>

<p>
    <a href="/">Catalogue</a>
    <?php if ($isStaff): ?> · <a href="/technicien">Tableau de bord technicien</a><?php endif; ?>
</p>

<?php if (!empty($flashError)): ?>
    <p class="error" role="alert"><?= $e($flashError) ?></p>
<?php endif; ?>

<?php if (!empty($flashSuccess)): ?>
    <p class="success" role="status"><?= $e($flashSuccess) ?></p>
<?php endif; ?>

<?php if ($dashboard !== null): ?>
    <div class="kpis">
        <div class="kpi"><strong><?= (int) $dashboard['kpis']['pending_reservations_count'] ?></strong>Demandes en attente</div>
        <div class="kpi"><strong><?= (int) $dashboard['kpis']['open_incidents_count'] ?></strong>Incidents actifs</div>
        <div class="kpi"><strong><?= (int) $dashboard['kpis']['out_of_service_count'] ?></strong>Machines hors service</div>
    </div>

    <?php $myReservations = $myReservations ?? []; ?>
    <h2>Mes réservations</h2>
    <?php if ($myReservations === []): ?>
        <p>Vous n'avez aucune réservation enregistrée.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Machine</th>
                    <th>Début</th>
                    <th>Fin</th>
                    <th>Statut</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($myReservations as $resa): ?>
                <?php
                $idResa = (int) ($resa['id_reservation'] ?? 0);
                $statut = (string) ($resa['statut'] ?? '');
                $peutAnnuler = in_array($statut, ['DEMANDEE', 'CONFIRMEE'], true);
                ?>
                <tr>
                    <td><?= $e($resa['equipement_nom'] ?? '') ?></td>
                    <td><?= $e($resa['date_debut'] ?? '') ?></td>
                    <td><?= $e($resa['date_fin'] ?? '') ?></td>
                    <td><?= $e($statut) ?></td>
                    <td>
                        <?php if ($peutAnnuler): ?>
                            <form class="inline" method="post" action="/reservations/<?= $idResa ?>/annuler">
                                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                <button type="submit">Annuler</button>
                            </form>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2>Demandes en attente de validation</h2>
    <?php if ($dashboard['pending_reservations'] === []): ?>
        <p>Aucune demande en attente.</p>
    <?php else: ?>
        <table>
            <thead><tr>
                <th>Machine</th>
                <th>Adhérent</th>
                <th>Début</th>
                <th>Fin</th>
            </tr></thead>
            <tbody>
            <?php foreach ($dashboard['pending_reservations'] as $r): ?>
                <?php $idResa = (int) ($r['id_reservation'] ?? 0); ?>
                <tr>
                    <td><?= $e($r['equipement_nom']) ?></td>
                    <td><?= $e($r['utilisateur_nom']) ?></td>
                    <td><?= $e($r['date_debut']) ?></td>
                    <td><?= $e($r['date_fin']) ?></td>
                    <?php if ($isStaff): ?>
                        <td>
                            <form class="inline" method="post" action="/technicien/reservations/<?= $idResa ?>/confirmer">
                                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                <button type="submit">Confirmer</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2>Incidents actifs</h2>
    <?php if ($dashboard['open_incidents'] === []): ?>
        <p>Aucun incident actif.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>N°</th><th>Titre</th><th>Machine</th><th>État</th><th>Bloquant</th><th>lien</th></tr></thead>
            <tbody>
            <?php foreach ($dashboard['open_incidents'] as $i): ?>
                <tr>
                    <td><?= (int) $i['id_incident'] ?></td>
                    <td><?= $e($i['titre']) ?></td>
                    <td><?= $e($i['equipement_nom']) ?></td>
                    <td><?= $e($i['statut']) ?></td>
                    <td><?= (int) $i['est_bloquant'] === 1 ? 'Oui' : 'Non' ?></td>
                    <td>
                        <a href="/incidents/<?= (int) ($inc['id'] ?? $inc['id_incident'] ?? 0) ?>">Voir l'incident</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2>Machines hors service</h2>
    <?php if ($dashboard['out_of_service_equipments'] === []): ?>
        <p>Toutes les machines sont en service.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($dashboard['out_of_service_equipments'] as $m): ?>
                <li><?= $e($m['nom']) ?> (<?= $e($m['localisation']) ?>)</li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
<?php endif; ?>
</body>
</html>
