<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Domain\ReservationRefusee;
use App\Repository\CreneauPrioritaireRepository;
use App\Repository\ReservationRepository;
use App\Service\ReservationService;
use Medoo\Medoo;

// 1. Connexion Medoo (adapte si tu as déjà un fichier de config/bootstrap)
$database = new Medoo([
    'type' => 'mysql',
    'host' => '127.0.0.1',
    'database' => 'bts_web', // <-- Mets le nom exact de ta base
    'username' => 'root',      // <-- Ton utilisateur MySQL
    'password' => 'root',          // <-- Ton mot de passe MySQL
    'charset' => 'utf8mb4',
    'error' => PDO::ERRMODE_EXCEPTION,
]);

$reservationRepo = new ReservationRepository($database);
$creneauRepo = new CreneauPrioritaireRepository($database);
$service = new ReservationService($reservationRepo, $creneauRepo);

// IDs existants dans ton jeu d'essai (à adapter si besoin)
$idEquipement = 1;
$idAdherent = 1;
$idTechnicien = 2;

// Dates futures relatives à demain pour ne jamais tomber sur DATE_PASSEE
$demain = (new DateTimeImmutable('+1 day'))->format('Y-m-d');

echo "=== DÉBUT DES TESTS RESERVATION (R1 & CONFLITS) ===\n\n";

$idsResaCrees = [];
$idCreneauTest = null;

try {
    // Préparation : on crée un créneau prioritaire demain de 09:00 à 12:00
    $database->insert('CRENEAU_PRIORITAIRE', [
        'date_debut' => "$demain 09:00:00",
        'date_fin' => "$demain 12:00:00",
        'libelle_atelier' => 'Atelier test automatique',
        'id_equipement' => $idEquipement,
    ]);
    $idCreneauTest = (int) $database->id();
    echo "[SETUP] Créneau prioritaire #$idCreneauTest créé ($demain 09:00 -> 12:00)\n\n";

    // ------------------------------------------------------------------
    // TEST 1 : Créneau ordinaire par un ADHERENT -> CONFIRMEE
    // ------------------------------------------------------------------
    $res1 = $service->creer(
        idEquipement: $idEquipement,
        idUtilisateur: $idAdherent,
        roleUtilisateur: 'ADHERENT',
        dateDebut: "$demain 06:00:00",
        dateFin: "$demain 07:00:00"
    );
    $idsResaCrees[] = (int) $res1['id'];
    echo "[TEST 1 - Ordinaire ADHERENT] Statut obtenu : {$res1['status']} (attendu : CONFIRMEE)\n";

    // ------------------------------------------------------------------
    // TEST 2 : Créneau prioritaire par un ADHERENT -> DEMANDEE (R1)
    // ------------------------------------------------------------------
    $res2 = $service->creer(
        idEquipement: $idEquipement,
        idUtilisateur: $idAdherent,
        roleUtilisateur: 'ADHERENT',
        dateDebut: "$demain 09:00:00",
        dateFin: "$demain 10:30:00"
    );
    $idsResaCrees[] = (int) $res2['id'];
    echo "[TEST 2 - Prioritaire ADHERENT] Statut obtenu : {$res2['status']} (attendu : DEMANDEE)\n";

    // ------------------------------------------------------------------
    // TEST 3 : Créneau prioritaire par un TECHNICIEN -> CONFIRMEE (R1)
    // ------------------------------------------------------------------
    $res3 = $service->creer(
        idEquipement: $idEquipement,
        idUtilisateur: $idTechnicien,
        roleUtilisateur: 'TECHNICIEN',
        dateDebut: "$demain 10:30:00",
        dateFin: "$demain 12:00:00"
    );
    $idsResaCrees[] = (int) $res3['id'];
    echo "[TEST 3 - Prioritaire TECHNICIEN] Statut obtenu : {$res3['status']} (attendu : CONFIRMEE)\n";

    // ------------------------------------------------------------------
    // TEST 4 : Conflit de créneau (15:00 -> 17:00 chevauche 14:00 -> 16:00)
    // ------------------------------------------------------------------
    try {
        $service->creer(
            idEquipement: $idEquipement,
            idUtilisateur: $idAdherent,
            roleUtilisateur: 'ADHERENT',
            dateDebut: "$demain 15:00:00",
            dateFin: "$demain 17:00:00"
        );
        echo "[TEST 4 - Conflit] ÉCHEC : la réservation aurait dû être refusée !\n";
    } catch (ReservationRefusee $e) {
        echo "[TEST 4 - Conflit] OK : refusée avec reason={$e->reason}, HTTP={$e->httpStatus} ({$e->getMessage()})\n";
    }

    // ------------------------------------------------------------------
    // TEST 5 : Date passée -> refus DATE_PASSEE (HTTP 422)
    // ------------------------------------------------------------------
    $hier = (new DateTimeImmutable('-1 day'))->format('Y-m-d');
    try {
        $service->creer(
            idEquipement: $idEquipement,
            idUtilisateur: $idAdherent,
            roleUtilisateur: 'ADHERENT',
            dateDebut: "$hier 10:00:00",
            dateFin: "$hier 11:00:00"
        );
        echo "[TEST 5 - Date passée] ÉCHEC : la réservation aurait dû être refusée !\n";
    } catch (ReservationRefusee $e) {
        echo "[TEST 5 - Date passée] OK : refusée avec reason={$e->reason}, HTTP={$e->httpStatus} ({$e->getMessage()})\n";
    }
} finally {
    // Nettoyage automatique des données de test
    if ($idsResaCrees !== []) {
        $database->delete('RESERVATION', ['id_reservation' => $idsResaCrees]);
    }
    if ($idCreneauTest !== null) {
        $database->delete('CRENEAU_PRIORITAIRE', ['id_creneau' => $idCreneauTest]);
    }
    echo "\n[CLEANUP] Données de test supprimées.\n";
}