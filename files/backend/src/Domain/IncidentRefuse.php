<?php

declare(strict_types=1);

namespace App\Domain;

use RuntimeException;

/**
 * Refus métier lié aux incidents (R6 / R3).
 * Même principe que ReservationRefusee : un code stable, un statut HTTP et un message,
 * partagés par le formulaire HTML et l'API.
 */
final class IncidentRefuse extends RuntimeException
{
    /** code => [statut HTTP, message par défaut]. */
    private const REFUS = [
        'INCIDENT_INTROUVABLE' => [404, 'Incident introuvable.'],
        'EQUIPEMENT_INCONNU' => [404, 'Cet équipement n\'existe pas.'],
        'ACCES_REFUSE' => [403, 'Seul un technicien ou un administrateur peut effectuer cette action.'],
        'TRANSITION_INTERDITE' => [403, 'Transition interdite.'],
        'MOTIF_REQUIS' => [422, 'Un motif est obligatoire pour rouvrir un incident.'],
        'INCIDENT_CLOS' => [422, 'Cet incident n\'est plus actif.'],
        'ETAT_MODIFIE' => [409, 'L\'incident a été modifié entre-temps : rechargez la page.'],
        'VALIDATION_ERROR' => [422, 'Données invalides.'],
    ];

    private function __construct(
        public readonly string $reason,
        public readonly int $httpStatus,
        string $message
    ) {
        parent::__construct($message);
    }

    public static function motif(string $reason, ?string $message = null): self
    {
        [$status, $default] = self::REFUS[$reason] ?? [422, 'Action impossible.'];

        return new self($reason, $status, $message ?? $default);
    }
}