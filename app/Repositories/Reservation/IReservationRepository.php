<?php

declare(strict_types=1);

namespace App\Repositories\Reservation;

use App\Http\Requests\ReservationRequest;

interface IReservationRepository
{
    public function store(ReservationRequest $request, int $user_id): bool;
}