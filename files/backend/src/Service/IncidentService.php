<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\IncidentRefuse;
use App\Repository\IncidentRepository;

/**
 * Règles d'un incident (R6 / R3) : signalement, procédure d'états, réouverture tracée, qualification « bloquant ».
 *
 * Procédure : OUVERT -> EN_COURS -> RESOLU -> CLOTURE.
 * Seul un technicien (ou un administrateur) fait avancer un incident.
 * Le seul retour en arrière est la réouverture (depuis RESOLU ou CLOTURE) : motif obligatoire, tracé par un trigger.
 */
final class IncidentService
{
    private const NEXT = [
        'OUVERT' => 'EN_COURS',
        'EN_COURS' => 'RESOLU',
        'RESOLU' => 'CLOTURE',
    ];
    private const RANK = ['OUVERT' => 0, 'EN_COURS' => 1, 'RESOLU' => 2, 'CLOTURE' => 3];
    private const REOUVRABLES = ['RESOLU', 'CLOTURE'];
    private const ACTIFS = ['OUVERT', 'EN_COURS'];

    public function __construct(private readonly IncidentRepository $incidents)
    {
    }

    /** Tout utilisateur connecté peut signaler un problème. */
    public function signaler(
        int $idUtilisateur,
        int $idEquipement,
        string $titre,
        string $description,
        string $nature
    ): array {
        $titre = trim($titre);
        $description = trim($description);

        if ($titre === '' || mb_strlen($titre) > 150) {
            throw IncidentRefuse::motif('VALIDATION_ERROR', 'Le titre est obligatoire (150 caractères maximum).');
        }
        if ($description === '' || mb_strlen($description) > 5000) {
            throw IncidentRefuse::motif('VALIDATION_ERROR', 'La description est obligatoire (5000 caractères maximum).');
        }
        if (!in_array($nature, ['TECHNIQUE', 'AUTRE'], true)) {
            throw IncidentRefuse::motif('VALIDATION_ERROR', 'La nature doit être TECHNIQUE ou AUTRE.');
        }
        if (!$this->incidents->equipementExiste($idEquipement)) {
            throw IncidentRefuse::motif('EQUIPEMENT_INCONNU');
        }

        $id = $this->incidents->create($idEquipement, $idUtilisateur, $titre, $description, $nature === 'TECHNIQUE');

        return $this->incidents->find($id) ?? ['id' => $id];
    }

    /**
     * Consultation : le personnel voit tous les incidents, un adhérent seulement les siens.
     *
     * @return array{incident: array, history: array, actions: array<int, string>}
     */
    public function consulter(int $id, int $idUtilisateur, string $role): array
    {
        $incident = $this->incidents->find($id) ?? throw IncidentRefuse::motif('INCIDENT_INTROUVABLE');

        if (!$this->estPersonnel($role) && (int) $incident['author_id'] !== $idUtilisateur) {
            throw IncidentRefuse::motif('ACCES_REFUSE', 'Vous ne pouvez consulter que vos propres incidents.');
        }

        return [
            'incident' => $incident,
            'history' => $this->incidents->history($id),
            'actions' => $this->estPersonnel($role) ? $this->actionsPossibles((string) $incident['status']) : [],
        ];
    }

    /** États vers lesquels le personnel peut faire passer un incident dans son état actuel. */
    public function actionsPossibles(string $statutActuel): array
    {
        $actions = [];
        if (isset(self::NEXT[$statutActuel])) {
            $actions[] = self::NEXT[$statutActuel];
        }
        if (in_array($statutActuel, self::REOUVRABLES, true)) {
            $actions[] = 'OUVERT';
        }

        return $actions;
    }

    public function changerEtat(int $id, string $nouvelEtat, ?string $motif, int $idUtilisateur, string $role): array
    {
        if (!$this->estPersonnel($role)) {
            throw IncidentRefuse::motif('ACCES_REFUSE');
        }
        if (!isset(self::RANK[$nouvelEtat])) {
            throw IncidentRefuse::motif('VALIDATION_ERROR', 'État inconnu : OUVERT, EN_COURS, RESOLU ou CLOTURE attendu.');
        }

        $incident = $this->incidents->find($id) ?? throw IncidentRefuse::motif('INCIDENT_INTROUVABLE');
        $actuel = (string) $incident['status'];
        $motif = $motif === null ? null : trim($motif);

        if ($nouvelEtat === 'OUVERT') {
            if (!in_array($actuel, self::REOUVRABLES, true)) {
                throw IncidentRefuse::motif('TRANSITION_INTERDITE', "L'incident est déjà actif (état $actuel) : il n'y a rien à rouvrir.");
            }
            if ($motif === null || mb_strlen($motif) < 3) {
                throw IncidentRefuse::motif('MOTIF_REQUIS');
            }
        } elseif ((self::NEXT[$actuel] ?? null) !== $nouvelEtat) {
            $message = self::RANK[$nouvelEtat] < self::RANK[$actuel]
                ? 'Vous ne pouvez pas revenir en arrière : rouvrez l\'incident avec un motif.'
                : "Transition interdite : $actuel vers $nouvelEtat. Les états se suivent dans l'ordre OUVERT, EN_COURS, RESOLU, CLOTURE.";
            throw IncidentRefuse::motif('TRANSITION_INTERDITE', $message);
        }

        if (mb_strlen((string) $motif) > 255) {
            throw IncidentRefuse::motif('VALIDATION_ERROR', 'Le motif ne doit pas dépasser 255 caractères.');
        }

        if (!$this->incidents->changeStatus($id, $actuel, $nouvelEtat, $idUtilisateur, $motif === '' ? null : $motif)) {
            throw IncidentRefuse::motif('ETAT_MODIFIE');
        }

        return $this->incidents->find($id) ?? $incident;
    }

    /** R3 : un technicien décide si un incident actif bloque les réservations de la machine. */
    public function definirBloquant(int $id, bool $bloquant, string $role): array
    {
        if (!$this->estPersonnel($role)) {
            throw IncidentRefuse::motif('ACCES_REFUSE');
        }

        $incident = $this->incidents->find($id) ?? throw IncidentRefuse::motif('INCIDENT_INTROUVABLE');

        if (!in_array((string) $incident['status'], self::ACTIFS, true)) {
            throw IncidentRefuse::motif('INCIDENT_CLOS');
        }

        $this->incidents->setBlocking($id, $bloquant);

        return $this->incidents->find($id) ?? $incident;
    }

    private function estPersonnel(string $role): bool
    {
        return in_array($role, ['TECHNICIEN', 'ADMIN'], true);
    }
}