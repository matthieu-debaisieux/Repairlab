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

const ID_PRUSA = 1;
const ID_ADHERENT = 3;     // Camille Durand (ADHERENT)
const ID_TECHNICIEN = 2;   // Karim Benali (TECHNICIEN)

$jour = jour(30);
$reservationsCreees = [];
$creneauTest = null;

echo "=== TESTS RESERVATION : R1 ET SOCLE ===" . PHP_EOL . PHP_EOL;

try {
    // Préparation : on crée un créneau prioritaire demain de 09:00 à 12:00
    $database->insert('CRENEAU_PRIORITAIRE', [
        'date_debut' => "$jour 09:00:00",
        'date_fin' => "$jour 12:00:00",
        'libelle_atelier' => 'Atelier test automatique',
        'id_equipement' => ID_PRUSA,
    ]);
    $creneauTest = (int) $database->id();
    
    // 1. Créneau ordinaire, adhérent : confirmée
    $r1 = $service->creer(ID_PRUSA, ID_ADHERENT, 'ADHERENT', "$jour 06:00:00", "$jour 07:00:00");
    $reservationsCreees[] = (int) $r1['id'];
    check('Créneau ordinaire, adhérent -> CONFIRMEE', ($r1['status'] ?? null) === 'CONFIRMEE', 'status=' . ($r1['status'] ?? 'null'));

    // 2. Créneau prioritaire, adhérent : refusé, rien n'est créé (R1, EV-MAT-01)
    $avant = nbReservations($database, ID_PRUSA);
    $e = refus(fn () => $service->creer(ID_PRUSA, ID_ADHERENT, 'ADHERENT', "$jour 09:30:00", "$jour 10:30:00"));
    check('Créneau prioritaire, adhérent -> ATELIER_PRIORITAIRE 403',
        $e !== null && $e->reason === 'ATELIER_PRIORITAIRE' && $e->httpStatus === 403,
        $e ? "{$e->reason}/{$e->httpStatus}" : 'aucun refus');
    check('Créneau prioritaire refusé : aucune réservation créée', nbReservations($database, ID_PRUSA) === $avant);

    // 3. Créneau prioritaire, technicien : confirmée et rattachée au créneau (affectation à l'atelier)
    $r3 = $service->creer(ID_PRUSA, ID_TECHNICIEN, 'TECHNICIEN', "$jour 10:30:00", "$jour 11:30:00");
    $reservationsCreees[] = (int) $r3['id'];
    $creneauLie = (int) $database->get('RESERVATION', 'id_creneau', ['id_reservation' => (int) $r3['id']]);
    check('Créneau prioritaire, technicien -> CONFIRMEE', ($r3['status'] ?? null) === 'CONFIRMEE');
    check('Affectation à l\'atelier : id_creneau renseigné', $creneauLie === $creneauTest, "id_creneau=$creneauLie");

    // 4. Chevauchement avec la réservation du test 1 : refusé
    $e = refus(fn () => $service->creer(ID_PRUSA, ID_ADHERENT, 'ADHERENT', "$jour 06:30:00", "$jour 07:30:00"));
    check('Chevauchement -> CRENEAU_OCCUPE 409',
        $e !== null && $e->reason === 'CRENEAU_OCCUPE' && $e->httpStatus === 409,
        $e ? "{$e->reason}/{$e->httpStatus}" : 'aucun refus');

    // 5. Créneau collé à la fin d'une réservation : autorisé (07:00 = fin de la précédente)
    $r5 = $service->creer(ID_PRUSA, ID_ADHERENT, 'ADHERENT', "$jour 07:00:00", "$jour 08:00:00");
    $reservationsCreees[] = (int) $r5['id'];
    check('Créneau adjacent -> CONFIRMEE', ($r5['status'] ?? null) === 'CONFIRMEE');

    // 6. Date passée
    $hier = jour(-1);
    $e = refus(fn () => $service->creer(ID_PRUSA, ID_ADHERENT, 'ADHERENT', "$hier 10:00:00", "$hier 11:00:00"));
    check('Date passée -> DATE_PASSEE 422', $e !== null && $e->reason === 'DATE_PASSEE' && $e->httpStatus === 422);

    // 7. Fin avant début
    $e = refus(fn () => $service->creer(ID_PRUSA, ID_ADHERENT, 'ADHERENT', "$jour 15:00:00", "$jour 14:00:00"));
    check('Fin avant début -> DATES_INVALIDES 422', $e !== null && $e->reason === 'DATES_INVALIDES' && $e->httpStatus === 422);

    // 8. Équipement inconnu
    $e = refus(fn () => $service->creer(9999, ID_ADHERENT, 'ADHERENT', "$jour 15:00:00", "$jour 16:00:00"));
    check('Équipement inconnu -> EQUIPEMENT_INCONNU 404', $e !== null && $e->reason === 'EQUIPEMENT_INCONNU' && $e->httpStatus === 404);
} finally {
    if ($reservationsCreees !== []) {
        $database->delete('RESERVATION', ['id_reservation' => $reservationsCreees]);
    if ($creneauTest !== null) {
        $database->delete('CRENEAU_PRIORITAIRE', ['id_creneau' => $creneauTest]);
    }
    echo PHP_EOL . "Bilan : $passed PASS / $failed FAIL" . PHP_EOL;
}

exit($failed === 0 ? 0 : 1);
}