<?php

declare(strict_types=1);

use App\Domain\ReservationRefusee;
use App\Repository\CreneauPrioritaireRepository;
use App\Repository\EquipmentRepository;
use App\Repository\ReservationRepository;
use App\Service\EquipmentService;
use App\Service\ReservationService;
use App\Service\TechnicienDashboardService;

$database = require __DIR__ . '/bootstrap.php';

$equipmentService = new EquipmentService(new EquipmentRepository($database));
$reservationService = new ReservationService(
    new ReservationRepository($database),
    new CreneauPrioritaireRepository($database)
);

$dashboardService = new TechnicienDashboardService($database);

$passed = 0;
$failed = 0;

function assertCase(string $name, bool $condition, string $details, int &$passed, int &$failed): void
{
    $condition ? $passed++ : $failed++;
    echo ($condition ? '[PASS] ' : '[FAIL] ') . "{$name} ({$details})" . PHP_EOL;
}

// Dates relatives et lointaines : indépendantes du jour d'exécution et du jeu d'essai.
$jour = (new DateTimeImmutable('+40 day'))->format('Y-m-d');
$jour2 = (new DateTimeImmutable('+41 day'))->format('Y-m-d');

echo '=== RECETTE - REPAIRLAB ===' . PHP_EOL . PHP_EOL;

$reservationsCreees = [];
$creneauCree = null;

try {
    // 1. Catalogue et filtres
    $catalogue = $equipmentService->list();
    assertCase('Catalogue public non vide', count($catalogue) > 0, 'count=' . count($catalogue), $passed, $failed);

    $enService = $equipmentService->list(['status' => 'EN_SERVICE']);
    $tousEnService = true;
    foreach ($enService as $item) {
        if (($item['status'] ?? '') !== 'EN_SERVICE') {
            $tousEnService = false;
        }
    }
    assertCase('Filtre catalogue status=EN_SERVICE', $tousEnService, 'count=' . count($enService), $passed, $failed);

    // 2. Réservation ordinaire, chevauchement, date passée
    $res1 = $reservationService->creer(1, 3, 'ADHERENT', "$jour 10:00:00", "$jour 12:00:00");
    $reservationsCreees[] = (int) $res1['id'];
    assertCase('Création réservation ordinaire', ($res1['status'] ?? null) === 'CONFIRMEE', 'status=' . ($res1['status'] ?? 'null'), $passed, $failed);

    $chevauchement = false;

    try {
        $reservationService->creer(1, 3, 'ADHERENT', "$jour 11:00:00", "$jour 13:00:00");
    } catch (ReservationRefusee $e) {
        $chevauchement = ($e->reason === 'CRENEAU_OCCUPE' && $e->httpStatus === 409);
    }
    assertCase('Refus chevauchement (409 CRENEAU_OCCUPE)', $chevauchement, 'attendu=409/CRENEAU_OCCUPE', $passed, $failed);

    $passe = false;
    try {
        $reservationService->creer(1, 3, 'ADHERENT', '2025-01-10 09:00:00', '2025-01-10 10:00:00');
    } catch (ReservationRefusee $e) {
        $passe = ($e->reason === 'DATE_PASSEE' && $e->httpStatus === 422);
    }
    assertCase('Refus date passée (422 DATE_PASSEE)', $passe, 'attendu=422/DATE_PASSEE', $passed, $failed);

    // 3. R1 : créneau prioritaire (adhérent refusé, technicien confirmé)
    $database->insert('CRENEAU_PRIORITAIRE', [
        'date_debut' => "$jour2 09:00:00",
        'date_fin' => "$jour2 11:00:00",
        'libelle_atelier' => 'Atelier Recette R1',
        'id_equipement' => 1,
    ]);
    $creaneuCree = (int) $database->id();

    $refuse = false;
    try {
        $reservationService->creer(1, 3, 'ADHERENT', "$jour2 09:30:00", "$jour2 10:30:00");
    } catch (ReservationRefusee $e) {
        $refuse = ($e->reason === 'ATELIER_PRIORITAIRE' && $e->httpStatus === 403);
    }
    assertCase('R1 créneau prioritaire ADHERENT -> ATELIER_PRIORITAIRE (403)', $refuse, 'attendu=403/ATELIER_PRIORITAIRE', $passed, $failed);

    $resTech = $reservationService->creer(1, 2, 'TECHNICIEN', "$jour2 09:30:00", "$jour2 10:30:00");
    $reservationsCreees[] = (int) $resTech['id'];
    assertCase('R1 créneau prioritaire TECHNICIEN -> CONFIRMEE', ($resTech['status'] ?? null) === 'CONFIRMEE', 'status=' . ($resTech['status'] ?? 'null'), $passed, $failed);

    // 4. Tableau de bord : une demande DEMANDEE insérée directement (aucun parcours n'en crée depuis EV-MAT-01)
    $database->insert('RESERVATION', [
        'date_debut' => "$jour2 15:00:00",
        'date_fin' => "$jour2 16:00:00",
        'statut' => 'DEMANDEE',
        'id_utilisateur' => 3,
        'id_equipement' => 6,
    ]);
    $reservationsCreees[] = (int) $database->id();
    $tableau = $dashboardService->getDashboardData();
    assertCase('Tableau de bord technicien liste la demande en attente',
        ($tableau['kpis']['pending_reservations_count'] ?? 0) >= 1,
        'pending=' . ($tableau['kpis']['pending_reservations_count'] ?? 0), $passed, $failed);

} finally {
    if ($reservationsCreees !== []) {
        $database->delete('RESERVATION', ['id_reservation' => $reservationsCreees]);
    if ($creneauCree !== null) {
        $database->delete('CRENEAU_PRIORITAIRE', ['id_creneau' => $creneauCree]);
    }
    echo PHP_EOL . "Bilan recette : {$passed} PASS / {$failed} FAIL" . PHP_EOL;
    }
}