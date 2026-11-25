<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Domain\ReservationRefusee;
use App\Repository\CreneauPrioritaireRepository;
use App\Repository\EquipmentRepository;
use App\Repository\ReservationRepository;
use App\Service\EquipmentService;
use App\Service\ReservationService;
use App\Service\TechnicienDashboardService;
use Medoo\Medoo;

$database = new Medoo([
    'type'     => 'mysql',
    'host'     => '127.0.0.1',
    'database' => 'bts_web',
    'username' => 'root',
    'password' => 'root',
    'charset'  => 'utf8mb4',
    'error'    => PDO::ERRMODE_EXCEPTION,
]);

$equipmentRepo = new EquipmentRepository($database);
$equipmentService = new EquipmentService($equipmentRepo);

$creneauRepo = new CreneauPrioritaireRepository($database);
$reservationRepo = new ReservationRepository($database);
$reservationService = new ReservationService($reservationRepo, $creneauRepo);

$dashboardService = new TechnicienDashboardService($database);

$passed = 0;
$failed = 0;

function assertCase(string $name, bool $condition, string $details, int &$passed, int &$failed): void
{
    if ($condition) {
        $passed++;
        echo "[PASS] {$name} ({$details})" . PHP_EOL;
    } else {
        $failed++;
        echo "[FAIL] {$name} ({$details})" . PHP_EOL;
    }
}

echo '=== RECETTE MARDI - REPAIRLAB ===' . PHP_EOL . PHP_EOL;

$createdReservationIds = [];
$createdCreneauId = null;

try {
    // 1. Catalogue & filtres
    $catalogAll = $equipmentService->list();
    assertCase(
        'Catalogue public non vide',
        count($catalogAll) > 0,
        'count=' . count($catalogAll),
        $passed,
        $failed
    );

    $catalogFiltered = $equipmentService->list(['status' => 'EN_SERVICE']);
    $allInService = true;
    foreach ($catalogFiltered as $item) {
        if (($item['status'] ?? '') !== 'EN_SERVICE') {
            $allInService = false;
            break;
        }
    }
    assertCase(
        'Filtre catalogue status=EN_SERVICE',
        $allInService,
        'count=' . count($catalogFiltered),
        $passed,
        $failed
    );

    // 2. Réservation ordinaire + conflit + date passée
    $start = '2026-11-26 10:00:00';
    $end   = '2026-11-26 12:00:00';

    $res1 = $reservationService->creer(1, 1, 'ADHERENT', $start, $end);
    if (isset($res1['id_reservation'])) {
        $createdReservationIds[] = (int) $res1['id_reservation'];
    }
    assertCase(
        'Création réservation ordinaire',
        ($res1['statut'] ?? null) === 'CONFIRMEE',
        'statut=' . ($res1['statut'] ?? 'null'),
        $passed,
        $failed
    );

    $conflictCaught = false;
    try {
        $reservationService->creer(1, 1, 'ADHERENT', '2026-11-26 11:00:00', '2026-11-26 13:00:00');
    } catch (ReservationRefusee $e) {
        $conflictCaught = ($e->reason === 'CONFLIT_CRENEAU' && $e->httpStatus === 409);
    }
    assertCase(
        'Refus chevauchement (409 CONFLIT_CRENEAU)',
        $conflictCaught,
        'attendu=409/CONFLIT_CRENEAU',
        $passed,
        $failed
    );

    $pastCaught = false;
    try {
        $reservationService->creer(1, 1, 'ADHERENT', '2025-01-10 09:00:00', '2025-01-10 10:00:00');
    } catch (ReservationRefusee $e) {
        $pastCaught = ($e->reason === 'DATE_PASSEE' && $e->httpStatus === 422);
    }
    assertCase(
        'Refus date passée (422 DATE_PASSEE)',
        $pastCaught,
        'attendu=422/DATE_PASSEE',
        $passed,
        $failed
    );

    // 3. R1 : créneau prioritaire (ADHERENT -> DEMANDEE, TECHNICIEN -> CONFIRMEE)
    $database->insert('CRENEAU_PRIORITAIRE', [
        'date_debut' => '2026-11-27 09:00:00',
        'date_fin' => '2026-11-27 11:00:00',
        'libelle_atelier' => 'Atelier Recette R1',
        'id_equipement' => 1,
    ]);
    $createdCreneauId = (int) $database->id();

    $resPrioAdherent = $reservationService->creer(1, 1, 'ADHERENT', '2026-11-27 09:30:00', '2026-11-27 10:30:00');
    if (isset($resPrioAdherent['id_reservation'])) {
        $createdReservationIds[] = (int) $resPrioAdherent['id_reservation'];
    }
    assertCase(
        'R1 créneau prioritaire ADHERENT -> DEMANDEE',
        ($resPrioAdherent['statut'] ?? null) === 'DEMANDEE',
        'statut=' . ($resPrioAdherent['statut'] ?? 'null'),
        $passed,
        $failed
    );

    $resPrioTech = $reservationService->creer(1, 2, 'TECHNICIEN', '2026-11-27 13:00:00', '2026-11-27 14:00:00');
    if (isset($resPrioTech['id_reservation'])) {
        $createdReservationIds[] = (int) $resPrioTech['id_reservation'];
    }
    assertCase(
        'R1 créneau prioritaire TECHNICIEN -> CONFIRMEE',
        ($resPrioTech['statut'] ?? null) === 'CONFIRMEE',
        'statut=' . ($resPrioTech['statut'] ?? 'null'),
        $passed,
        $failed
    );

    // 4. Données tableau de bord technicien
    $dashboard = $dashboardService->getDashboardData();
    assertCase(
        'Tableau de bord technicien inclut la DEMANDEE',
        ($dashboard['kpis']['pending_reservations_count'] ?? 0) >= 1,
        'pending=' . ($dashboard['kpis']['pending_reservations_count'] ?? 0),
        $passed,
        $failed
    );
} finally {
    if ($createdReservationIds !== []) {
        $database->delete('RESERVATION', ['id_reservation' => $createdReservationIds]);
    }
    if ($createdCreneauId !== null) {
        $database->delete('CRENEAU_PRIORITAIRE', ['id_creneau' => $createdCreneauId]);
    }
    echo PHP_EOL . "Bilan recette : {$passed} PASS / {$failed} FAIL" . PHP_EOL;
}