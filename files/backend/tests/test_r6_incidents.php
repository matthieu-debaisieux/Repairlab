<?php

declare(strict_types=1);

use App\Domain\IncidentRefuse;
use App\Repository\IncidentRepository;
use App\Service\IncidentService;

$database = require __DIR__ . '/bootstrap.php';

$repo = new IncidentRepository($database);
$service = new IncidentService($repo);

$passed = 0;
$failed = 0;

function checkR6(string $name, bool $ok, string $details = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . ($details !== '' ? " ($details)" : '') . PHP_EOL;
}

function refusR6(callable $action): ?IncidentRefuse
{
    try {
        $action();
    } catch (IncidentRefuse $e) {
        return $e;
    }

    return null;
}

const ID_EQUIPEMENT_TEST = 1;
const ID_ADHERENT_TEST = 3;
const ID_TECHNICIEN_TEST = 2;

$incidentsCrees = [];

echo "=== TESTS R6 & INC-MAT-01 : CYCLE DE VIE INCIDENT ===" . PHP_EOL . PHP_EOL;

try {
    // 1. Signalement par un adhérent -> état OUVERT + entrée initiale dans HISTORIQUE_INCIDENT
    $inc = $service->signaler(
        ID_ADHERENT_TEST,
        ID_EQUIPEMENT_TEST,
        'Test R6 Découpeuse',
        'Perte de puissance laser constatée.',
        'TECHNIQUE'
    );
    $idInc = (int) $inc['id'];
    $incidentsCrees[] = $idInc;

    checkR6('Signalement initial -> OUVERT', ($inc['status'] ?? '') === 'OUVERT');

    $histInit = $repo->history($idInc);
    checkR6('Trigger insertion -> 1 ligne dans HISTORIQUE_INCIDENT', count($histInit) === 1 && ($histInit[0]['to'] ?? '') === 'OUVERT');

    // 2. INC-MAT-01 : Tentative de saut d'étape OUVERT -> CLOTURE par un TECHNICIEN -> refusé (403)
    $e = refusR6(fn () => $service->changerEtat($idInc, 'CLOTURE', null, ID_TECHNICIEN_TEST, 'TECHNICIEN'));
    checkR6(
        'INC-MAT-01 : Saut OUVERT -> CLOTURE refusé (TRANSITION_INTERDITE / 403)',
        $e !== null && $e->reason === 'TRANSITION_INTERDITE' && $e->httpStatus === 403
    );
    $afterSkip = $repo->find($idInc);
    checkR6('INC-MAT-01 : État resté OUVERT en base', ($afterSkip['status'] ?? '') === 'OUVERT');

    // 3. INC-MAT-01 : Tentative de transition par un ADHERENT -> refusé (403 ACCES_REFUSE)
    $e = refusR6(fn () => $service->changerEtat($idInc, 'EN_COURS', null, ID_ADHERENT_TEST, 'ADHERENT'));
    checkR6(
        'INC-MAT-01 : Transition par ADHERENT refusée (ACCES_REFUSE / 403)',
        $e !== null && $e->reason === 'ACCES_REFUSE' && $e->httpStatus === 403
    );

    // 4. R3 : Qualification en panne bloquante par technicien (refusée à l'adhérent)
    $e = refusR6(fn () => $service->definirBloquant($idInc, true, 'ADHERENT'));
    checkR6('R3 : Qualification bloquante refusée à ADHERENT', $e !== null && $e->reason === 'ACCES_REFUSE');

    $bloquant = $service->definirBloquant($idInc, true, 'TECHNICIEN');
    checkR6('R3 : Qualification bloquante acceptée pour TECHNICIEN', ($bloquant['blocking'] ?? false) === true);

    // 5. Parcours nominal R6 : OUVERT -> EN_COURS -> RESOLU -> CLOTURE
    $s1 = $service->changerEtat($idInc, 'EN_COURS', 'Prise en charge atelier', ID_TECHNICIEN_TEST, 'TECHNICIEN');
    checkR6('Transition OUVERT -> EN_COURS', ($s1['status'] ?? '') === 'EN_COURS');

    $s2 = $service->changerEtat($idInc, 'RESOLU', 'Lentille nettoyée et recalibrée', ID_TECHNICIEN_TEST, 'TECHNICIEN');
    checkR6('Transition EN_COURS -> RESOLU', ($s2['status'] ?? '') === 'RESOLU');

    // Qualification bloquante impossible sur un incident non actif (RESOLU)
    $e = refusR6(fn () => $service->definirBloquant($idInc, true, 'TECHNICIEN'));
    checkR6('R3 : Blocage interdit sur incident RESOLU (INCIDENT_CLOS)', $e !== null && $e->reason === 'INCIDENT_CLOS');

    $s3 = $service->changerEtat($idInc, 'CLOTURE', 'Vérification OK', ID_TECHNICIEN_TEST, 'TECHNICIEN');
    checkR6('Transition RESOLU -> CLOTURE', ($s3['status'] ?? '') === 'CLOTURE');

    // 6. Réouverture : refusée sans motif, acceptée avec motif >= 3 caractères
    $e = refusR6(fn () => $service->changerEtat($idInc, 'OUVERT', '  ', ID_TECHNICIEN_TEST, 'TECHNICIEN'));
    checkR6('Réouverture sans motif refusée (MOTIF_REQUIS / 422)', $e !== null && $e->reason === 'MOTIF_REQUIS' && $e->httpStatus === 422);

    $reouvert = $service->changerEtat($idInc, 'OUVERT', 'Défaut revenu lors du test client', ID_TECHNICIEN_TEST, 'TECHNICIEN');
    checkR6('Réouverture avec motif -> OUVERT', ($reouvert['status'] ?? '') === 'OUVERT');

    // 7. Vérification complète de l'historique tracé par trigger (5 états : NULL->OUVERT, EN_COURS, RESOLU, CLOTURE, OUVERT)
    $histFinal = $repo->history($idInc);
    checkR6('Historique complet tracé (5 entrées)', count($histFinal) === 5, 'count=' . count($histFinal));
} finally {
    if ($incidentsCrees !== []) {
        $database->delete('HISTORIQUE_INCIDENT', ['id_incident' => $incidentsCrees]);
        $database->delete('INCIDENT', ['id_incident' => $incidentsCrees]);
    }
    echo PHP_EOL . "Bilan R6 : $passed PASS / $failed FAIL" . PHP_EOL;
}

exit($failed === 0 ? 0 : 1);