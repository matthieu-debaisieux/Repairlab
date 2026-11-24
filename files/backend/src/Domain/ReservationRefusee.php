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
    private const REFUS = [
        'DATES_INVALIDES'    => [422, 'La fin doit être postérieure au début.'],
        'DATE_PASSEE'        => [422, 'Impossible de réserver dans le passé.'],
        'EQUIPEMENT_INCONNU' => [404, 'Cet équipement n\'existe pas.'],
        'CONFLIT_CRENEAU'    => [409, 'Ce créneau est déjà pris pour cet équipement.'],
    ];

    private function __construct(
        public readonly string $reason,
        public readonly int $httpStatus,
        string $message
    ) {
        parent::__construct($message);
    }

    /** Retourne null si l'erreur n'est pas un refus métier connu (elle doit alors remonter telle quelle). */
    public static function fromPdo(PDOException $e): ?self
    {
        $state = (string) ($e->errorInfo[0] ?? '');
        $text = (string) ($e->errorInfo[2] ?? '');

        if ($state === '45000' && isset(self::REFUS[$text])) {
            [$status, $message] = self::REFUS[$text];

            return new self($text, $status, $message);
        }

        if ($state === '23000') {
            return new self('REFERENCE_INVALIDE', 422, 'Utilisateur ou équipement invalide.');
        }

        return null;
    }
}