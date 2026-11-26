<?php

declare(strict_types=1);

namespace App\Domain;

use PDOException;
use RuntimeException;

/**
 * Refus métier d'une réservation, remonté par la procédure stockée
 * (SQLSTATE 45000 + code dans le message) ou par une contrainte de la base.
 */
final class ReservationRefusee extends RuntimeException
{
    /** Motifs stables : code => [statut HTTP, message affiché à l'utilisateur]. */
    private const REFUS = [
        // Validation de la demande
        'DATES_INVALIDES' => [422, 'La date de fin doit être postérieure à la date de début.'],
        'DATE_PASSEE' => [422, 'Impossible de réserver dans le passé.'],
        'EQUIPEMENT_INCONNU' => [404, 'Cet équipement n\'existe pas.'],
        'REFERENCE_INVALIDE' => [422, 'Utilisateur ou équipement invalide.'],
        // EV-MAT-01 : les trois motifs de refus d'une réservation
        'ATELIER_PRIORITAIRE' => [403, 'Ce créneau est réservé à un atelier encadré. Choisissez un autre horaire ou contactez l\'accueil.'],
        'PANNE_BLOQUANTE' => [409, 'Cette machine est en panne et indisponible pour le moment.'],
        'CRENEAU_OCCUPE' => [409, 'Ce créneau est déjà réservé pour cette machine.'],
        // Gestion d'une réservation existante
        'RESERVATION_INTROUVABLE' => [404, 'Réservation introuvable.'],
        'ACCES_REFUSE' => [403, 'Vous n\'avez pas le droit d\'effectuer cette action.'],
        'TRANSITION_INVALIDE' => [422, 'Cette réservation ne peut plus être modifiée.'],
        'STATUT_INVALIDE' => [422, 'Cette réservation n\'est pas dans un état qui permet cette action.'],
    ];

    private function __construct(
        public readonly string $reason,
        public readonly int $httpStatus,
        string $message
    ) {
        parent::__construct($message);
    }

    /** Crée un refus à partir d'un code ; statut et message peuvent être précisés pour un cas particulier. */
    public static function motif(string $reason, ?int $httpStatus = null, ?string $message = null): self
    {
        [$defaultStatus, $defaultMessage] = self::REFUS[$reason] ?? [409, 'Réservation impossible.'];

        return new self($reason, $httpStatus ?? $defaultStatus, $message ?? $defaultMessage);
    }
    
    /** Retourne null si l'erreur n'est pas un refus métier connu (elle doit alors remonter telle quelle). */
    public static function fromPdo(PDOException $e): ?self
    {
        $state = (string) ($e->errorInfo[0] ?? '');
        $text = (string) ($e->errorInfo[2] ?? '');

        if ($state === '45000' && isset(self::REFUS[$text])) {
            return self::motif($text);
        }

        if ($state === '23000') {
            return self::motif('REFERENCE_INVALIDE');
        }

        return null;
    }
}