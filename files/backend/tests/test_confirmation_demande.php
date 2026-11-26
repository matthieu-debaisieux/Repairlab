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

function checkConfirm(string $name, bool $ok, string $details = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . ($details !== '' ? " ($details)" : '') . PHP_EOL;
}

function refusConfirm(callable $action): ?ReservationRefusee
{
    try {
        $action();
    } catch (ReservationRefusee $e) {
        return $e;
    }

    return null;
}

function jourConfirm(int $plus): string
{
    return (new DateTimeImmutable(sprintf('%+d day', $plus)))->format('Y-m-d');
}

const ID_EQUIPEMENT_CONF = 1;
const ID_ADHERENT_CONF = 3;
const ID_TECHNICIEN_CONF = 2;

$reservationsTest = [];
$jour = jourConfirm(45);

echo "=== TESTS CONFIRMATION DEMANDE PAR TECHNICIEN ===" . PHP_EOL . PHP_EOL;

try {
    // 1. Création d'une demande en attente (DEMANDEE)
    $database->insert('RESERVATION', [
        'date_debut' => "$jour 09:00:00",
        'date_fin' => "$jour 11:00:00",
        'statut' => 'DEMANDEE',
        'id_utilisateur' => ID_ADHERENT_CONF,
        'id_equipement' => ID_EQUIPEMENT_CONF,
    ]);
    $idDemande = (int) $database->id();
    $reservationsTest[] = $idDemande;

    // 2. Refus si l'appelant n'est pas technicien/admin (ADHERENT -> ACCES_REFUSE 403)
    $eAdherent = refusConfirm(fn () => $service->confirmerDemande($idDemande, 'ADHERENT'));
    checkConfirm(
        'Refus pour ADHERENT -> ACCES_REFUSE 403',
        $eAdherent !== null && $eAdherent->reason === 'ACCES_REFUSE' && $eAdherent->httpStatus === 403,
        $eAdherent ? "{$eAdherent->reason}/{$eAdherent->httpStatus}" : 'aucun refus'
    );

    $statutApresRefus = (string) $database->get('RESERVATION', 'statut', ['id_reservation' => $idDemande]);
    checkConfirm('Statut inchangé après refus ADHERENT (DEMANDEE)', $statutApresRefus === 'DEMANDEE', "statut=$statutApresRefus");

    // 3. Confirmation nominale par un TECHNICIEN -> CONFIRMEE
    $confirmee = $service->confirmerDemande($idDemande, 'TECHNICIEN');
    checkConfirm(
        'Confirmation par TECHNICIEN -> CONFIRMEE',
        ($confirmee['status'] ?? null) === 'CONFIRMEE',
        'status=' . ($confirmee['status'] ?? 'null')
    );

    // 4. Refus si le statut n'est plus DEMANDEE -> STATUT_INVALIDE 422
    $eDejaConfirmee = refusConfirm(fn () => $service->confirmerDemande($idDemande, 'TECHNICIEN'));
    checkConfirm(
        'Refus si déjà CONFIRMEE -> STATUT_INVALIDE 422',
        $eDejaConfirmee !== null && $eDejaConfirmee->reason === 'STATUT_INVALIDE' && $eDejaConfirmee->httpStatus === 422,
        $eDejaConfirmee ? "{$eDejaConfirmee->reason}/{$eDejaConfirmee->httpStatus}" : 'aucun refus'
    );

    // 5. Refus si conflit avec une réservation CONFIRMEE existante -> CRENEAU_OCCUPE 409
    $jourConflit = jourConfirm(95);

    $database->insert('RESERVATION', [
        'date_debut' => "$jourConflit 14:00:00",
        'date_fin' => "$jourConflit 16:00:00",
        'statut' => 'DEMANDEE',
        'id_utilisateur' => ID_ADHERENT_CONF,
        'id_equipement' => ID_EQUIPEMENT_CONF,
    ]);
    $idDemandeConflit = (int) $database->id();
    $reservationsTest[] = $idDemandeConflit;

    $database->insert('RESERVATION', [
        'date_debut' => "$jourConflit 15:00:00",
        'date_fin' => "$jourConflit 17:00:00",
        'statut' => 'CONFIRMEE',
        'id_utilisateur' => ID_TECHNICIEN_CONF,
        'id_equipement' => ID_EQUIPEMENT_CONF,
    ]);
    $reservationsTest[] = (int) $database->id();

    $eConflit = refusConfirm(fn () => $service->confirmerDemande($idDemandeConflit, 'TECHNICIEN'));

    // 6. Refus si réservation introuvable
    $eIntrouvable = refusConfirm(fn () => $service->confirmerDemande(999999, 'TECHNICIEN'));
    checkConfirm(
        'Refus si réservation introuvable -> RESERVATION_INTROUVABLE',
        $eIntrouvable !== null && $eIntrouvable->reason === 'RESERVATION_INTROUVABLE',
        $eIntrouvable ? $eIntrouvable->reason : 'aucun refus'
    );
} finally {
    if ($reservationsTest !== []) {
        $database->delete('RESERVATION', ['id_reservation' => $reservationsTest]);
    }
    echo PHP_EOL . "Bilan confirmation : $passed PASS / $failed FAIL" . PHP_EOL;
}

exit($failed === 0 ? 0 : 1);
