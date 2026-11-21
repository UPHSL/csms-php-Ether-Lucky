<?php

namespace App\Services;

use App\Repositories\ResidentRepository;
use Illuminate\Support\Collection;

class ResidentQueryService
{
    public function __construct(
        private ResidentRepository $repository
    ) {}

    public function listResidents(): Collection
    {
        return $this->repository->findAll();
    }

    public function searchResidents(
        ?string $searchTerm
    ): Collection {
        $normalizedSearchTerm =
            trim(
                $searchTerm ?? ''
            );

        if ($normalizedSearchTerm === '') {
            return $this->listResidents();
        }

        return $this
            ->repository
            ->searchByName(
                $normalizedSearchTerm
            );
    }
}
