<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\ReservationRefusee;
use App\Http\HttpInputException;
use App\Http\JsonInput;
use App\Http\JsonResponder;
use App\Http\Session;
use App\Service\ReservationService;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ReservationController
{
    private const DATE_FORMATS = ['Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i'];

    public function __construct(
        private ReservationService $reservations,
        private Session $session
    ) {
    }

    /** POST /api/reservations : { "equipmentId": 1, "debut": "...", "fin": "..." } */
    public function create(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $user = $this->session->user();
        if ($user === null) {
            return JsonResponder::respond($response, [
                'error' => 'UNAUTHENTICATED',
                'message' => 'Connexion requise.',
            ], 401);
        }

        try {
            $input = JsonInput::readObject($request);
        } catch (HttpInputException $exception) {
            return JsonResponder::respond($response, [
                'error' => 'INVALID_REQUEST',
                'message' => $exception->getMessage(),
            ], $exception->status);
        }

        $equipmentId = $input['equipmentId'] ?? null;
        $equipmentId = (is_int($equipmentId) || (is_string($equipmentId) && ctype_digit($equipmentId)))
            ? (int) $equipmentId
            : 0;
        $start = $this->parseDate($input['debut'] ?? null);
        $end = $this->parseDate($input['fin'] ?? null);

        if ($equipmentId <= 0 || $start === null || $end === null) {
            return JsonResponder::respond($response, [
                'error' => 'VALIDATION_ERROR',
                'message' => 'equipmentId (entier), debut et fin (AAAA-MM-JJ HH:MM:SS) sont obligatoires.',
            ], 422);
        }

        try {
            $reservation = $this->reservations->creer(
                idEquipement: $equipmentId,
                idUtilisateur: (int) $user['id'],
                roleUtilisateur: (string) $user['role'],
                dateDebut: $start,
                dateFin: $end
            );
        } catch (ReservationRefusee $refus) {
            return JsonResponder::respond($response, [
                'error' => $refus->reason,
                'message' => $refus->getMessage(),
            ], $refus->httpStatus);
        } catch (InvalidArgumentException $exception) {
            return JsonResponder::respond($response, [
                'error' => 'VALIDATION_ERROR',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return JsonResponder::respond($response, $reservation, 201);
    }

    /** Accepte uniquement une date réelle et complète ; retourne le format SQL, ou null. */
    private function parseDate(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        foreach (self::DATE_FORMATS as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        return null;
    }
}
