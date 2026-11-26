<?php

declare(strict_types=1);

use App\Domain\ReservationRefusee;
use App\Repository\CreneauPrioritaireRepository;
use App\Repository\ReservationRepository;
use App\Service\ReservationService;

$database = require __DIR__ . '/bootstrap.php';

$service = new ReservationService(
    new ReservationRepository($database),
    new CreneauPrioritaireRepository($database)
);

$passed = 0;
$failed = 0;

function check(string $name, bool $ok, string $details = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . ($details !== '' ? " ($details)" : '') . PHP_EOL;
}

/** Exécute l'action et retourne le refus métier levé, ou null si elle a réussi. */
function refus(callable $action): ?ReservationRefusee
{
    try {
        $action();
    } catch (ReservationRefusee $e) {
        return $e;
    }

    return null;
}

function nbReservations($database, int $idEquipement): int
{
    return (int) $database->count('RESERVATION', ['id_equipement' => $idEquipement]);
}

// Dates relatives, loin des données du jeu d'essai (J+1 à J+8) : le test ne dépend pas du jour d'import.
function jour(int $plus): string
{
    return (new DateTimeImmutable(sprintf('%+d day', $plus)))->format('Y-m-d');
}

// EV-MAT-01 : motifs métier stables pour les refus de réservation (R1, R3, socle).
const ID_PRUSA = 1;
const ID_SOUDURE = 4;      // aucun incident dans le jeu d'essai
const ID_PC = 5;
const ID_ADHERENT = 3;
const ID_TECHNICIEN = 2;

$reservations = [];
$creneaux = [];
$incidents = [];

echo "=== TESTS EV-MAT-01 : MOTIFS DE REFUS ===" . PHP_EOL . PHP_EOL;

try {
    // --- Machine en panne bloquante -----------------------------------------------------------
    $database->insert('INCIDENT', [
        'titre' => 'Panne de test automatique',
        'description' => 'Incident créé par test_ev_mat_01.php',
        'statut' => 'EN_COURS',
        'est_technique' => 1,
        'est_bloquant' => 1,
        'id_equipement' => ID_SOUDURE,
        'id_utilisateur' => ID_ADHERENT,
    ]);
    $incident = (int) $database->id();
    $incidents[] = $incident;

    $j = jour(31);
    $avant = nbReservations($database, ID_SOUDURE);
    $e = refus(fn () => $service->creer(ID_SOUDURE, ID_ADHERENT, 'ADHERENT', "$j 10:00:00", "$j 11:00:00"));
    check('Panne bloquante, adhérent -> PANNE_BLOQUANTE 409',
        $e !== null && $e->reason === 'PANNE_BLOQUANTE' && $e->httpStatus === 409,
        $e ? "{$e->reason}/{$e->httpStatus}" : 'aucun refus');
    check('Panne bloquante : aucune réservation créée', nbReservations($database, ID_SOUDURE) === $avant);

    $e = refus(fn () => $service->creer(ID_SOUDURE, ID_TECHNICIEN, 'TECHNICIEN', "$j 10:00:00", "$j 11:00:00"));
    check('Panne bloquante, technicien -> PANNE_BLOQUANTE aussi', $e !== null && $e->reason === 'PANNE_BLOQUANTE');

    // --- Ordre des motifs : les droits d'abord ------------------------------------------------
    $database->insert('CRENEAU_PRIORITAIRE', [
        'date_debut' => "$j 14:00:00", 'date_fin' => "$j 17:00:00",
        'libelle_atelier' => 'Atelier test EV-MAT-01', 'id_equipement' => ID_SOUDURE,
    ]);
    $creneaux[] = (int) $database->id();
    $e = refus(fn () => $service->creer(ID_SOUDURE, ID_ADHERENT, 'ADHERENT', "$j 15:00:00", "$j 16:00:00"));
    check('Panne ET atelier, adhérent -> ATELIER_PRIORITAIRE (droits d\'abord)', $e !== null && $e->reason === 'ATELIER_PRIORITAIRE');

    // --- Retour à la normale après résolution --------------------------------------------------
    $database->update('INCIDENT', ['statut' => 'RESOLU', 'date_resolution' => date('Y-m-d H:i:s')], ['id_incident' => $incident]);
    $ok = $service->creer(ID_SOUDURE, ID_ADHERENT, 'ADHERENT', "$j 10:00:00", "$j 11:00:00");
    $reservations[] = (int) $ok['id'];
    check('Incident résolu : la réservation redevient possible', ($ok['status'] ?? null) === 'CONFIRMEE');

    // --- Créneau prioritaire inaccessible à un adhérent ----------------------------------------
    $j2 = jour(32);
    $database->insert('CRENEAU_PRIORITAIRE', [
        'date_debut' => "$j2 09:00:00", 'date_fin' => "$j2 12:00:00",
        'libelle_atelier' => 'Atelier test EV-MAT-01 (Prusa)', 'id_equipement' => ID_PRUSA,
    ]);
    $creneaux[] = (int) $database->id();
    $avant = nbReservations($database, ID_PRUSA);
    $e = refus(fn () => $service->creer(ID_PRUSA, ID_ADHERENT, 'ADHERENT', "$j2 09:30:00", "$j2 10:30:00"));
    check('Créneau prioritaire, adhérent -> ATELIER_PRIORITAIRE 403',
        $e !== null && $e->reason === 'ATELIER_PRIORITAIRE' && $e->httpStatus === 403);
    check('Créneau prioritaire : aucune réservation créée', nbReservations($database, ID_PRUSA) === $avant);

    // --- Créneau libre et autorisé : accepté ---------------------------------------------------
    $libre = $service->creer(ID_PC, ID_ADHERENT, 'ADHERENT', "$j2 09:00:00", "$j2 10:00:00");
    $reservations[] = (int) $libre['id'];
    check('Créneau libre et autorisé -> CONFIRMEE', ($libre['status'] ?? null) === 'CONFIRMEE');

    // --- Créneau occupé -------------------------------------------------------------------------
    $e = refus(fn () => $service->creer(ID_PC, ID_TECHNICIEN, 'TECHNICIEN', "$j2 09:30:00", "$j2 10:30:00"));
    check('Créneau déjà pris -> CRENEAU_OCCUPE 409',
        $e !== null && $e->reason === 'CRENEAU_OCCUPE' && $e->httpStatus === 409);

    // --- Les trois motifs sont distincts, avec leur statut documenté ---------------------------
    $motifs = [];
    foreach (['ATELIER_PRIORITAIRE' => 403, 'PANNE_BLOQUANTE' => 409, 'CRENEAU_OCCUPE' => 409] as $code => $statut) {
        $m = ReservationRefusee::motif($code);
        $motifs[$m->getMessage()] = true;
        check("Contrat $code : HTTP $statut", $m->httpStatus === $statut && $m->reason === $code);
    }
    check('Un message distinct par motif', count($motifs) === 3);
} finally {
    if ($reservations !== []) {
        $database->delete('RESERVATION', ['id_reservation' => $reservations]);
    }
    if ($creneaux !== []) {
        $database->delete('CRENEAU_PRIORITAIRE', ['id_creneau' => $creneaux]);
    }
    foreach ($incidents as $id) {
        $database->delete('HISTORIQUE_INCIDENT', ['id_incident' => $id]);
        $database->delete('INCIDENT', ['id_incident' => $id]);
    }
    echo PHP_EOL . "Bilan : $passed PASS / $failed FAIL" . PHP_EOL;
}

exit($failed === 0 ? 0 : 1);