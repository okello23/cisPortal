<?php

namespace App\Support;

use App\Models\Facility;
use App\Models\Region;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class FacilitySyncService
{
    private const FACILITY_ENDPOINT = 'https://irrds.cphl.go.ug/api/facilities';

    public function syncFromIrrds(int $limit = 100): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $fetched = 0;
        $page = 1;

        do {
            $response = Http::timeout(60)->get(self::FACILITY_ENDPOINT, [
                'limit' => $limit,
                'page' => $page,
                'search' => '',
                'includeInactive' => 'false',
            ])->throw();

            $payload = $response->json();
            $items = Arr::get($payload, 'data', $payload);
            $pagination = Arr::get($payload, 'pagination', []);

            foreach ($items as $item) {
                $item = $this->normalizeFacilityItem($item);
                $fetched++;

                if (! is_array($item)) {
                    $skipped++;
                    continue;
                }

                $region = $this->resolveRegion($item);
                $attributes = $this->mapFacilityAttributes($item, $region?->id);

                if (blank($attributes['name']) || blank($attributes['code']) || blank($attributes['external_id'])) {
                    $skipped++;
                    continue;
                }

                $facility = Facility::query()
                    ->where('source_system', 'irrds')
                    ->where('external_id', $attributes['external_id'])
                    ->first();

                if (! $facility && ! empty($attributes['code'])) {
                    $facility = Facility::query()->where('code', $attributes['code'])->first();
                }

                if ($facility) {
                    $facility->fill($attributes);
                    $facility->updated_by = Auth::id();
                    $facility->save();
                    $updated++;
                } else {
                    Facility::query()->create([
                        ...$attributes,
                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
                    ]);
                    $created++;
                }
            }

            $hasNextPage = (bool) Arr::get($pagination, 'hasNextPage', false);
            $page++;
        } while ($hasNextPage);

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'fetched' => $fetched];
    }

    private function normalizeFacilityItem(mixed $item): ?array
    {
        if (! is_array($item)) {
            return null;
        }

        if (array_is_list($item) && count($item) === 1 && is_array($item[0] ?? null)) {
            return $item[0];
        }

        return $item;
    }

    private function resolveRegion(array $item): ?Region
    {
        $resolvedRegion = Arr::get($item, 'hierarchies.resolvedLocation.region')
            ?? Arr::get($item, 'hierarchy.region')
            ?? Arr::get($item, 'region');

        if (! $resolvedRegion) {
            return null;
        }

        $code = Arr::get($resolvedRegion, 'code');
        $name = Arr::get($resolvedRegion, 'name');

        if (! $name) {
            return null;
        }

        $region = Region::query()
            ->where(function ($query) use ($code, $name) {
                if ($code) {
                    $query->where('code', $code)->orWhere('name', $name);
                    return;
                }

                $query->where('name', $name);
            })
            ->first();

        if ($region) {
            if ($code && ! $region->code) {
                $region->code = $code;
                $region->updated_by = Auth::id();
                $region->save();
            }

            return $region;
        }

        return Region::query()->create([
            'name' => $name,
            'code' => $code,
            'active' => (bool) Arr::get($resolvedRegion, 'isActive', true),
            'sort_order' => 0,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);
    }

    private function mapFacilityAttributes(array $item, ?int $regionId): array
    {
        return [
            'region_id' => $regionId,
            'name' => Arr::get($item, 'name') ?? Arr::get($item, 'record.name'),
            'code' => Arr::get($item, 'code') ?? Arr::get($item, 'record.code'),
            'source_system' => 'irrds',
            'external_id' => Arr::get($item, 'id') ?? Arr::get($item, 'record.id'),
            'facility_type' => Arr::get($item, 'facilityType') ?? Arr::get($item, 'record.type'),
            'moh_id' => Arr::get($item, 'mohId') ?? Arr::get($item, 'linkedSystems.ministryOfHealth.savedMohId'),
            'nhlds_uuid' => Arr::get($item, 'nhldsUuid') ?? Arr::get($item, 'linkedSystems.nhlds.uuid'),
            'district_name' => Arr::get($item, 'hierarchies.resolvedLocation.district.name')
                ?? Arr::get($item, 'districtName')
                ?? Arr::get($item, 'district.name'),
            'subcounty_name' => Arr::get($item, 'hierarchies.resolvedLocation.subcounty.name')
                ?? Arr::get($item, 'mohSubcountyName'),
            'phone' => Arr::get($item, 'phone')
                ?? Arr::get($item, 'facilityContact')
                ?? Arr::get($item, 'record.contacts.phone'),
            'email' => Arr::get($item, 'email')
                ?? Arr::get($item, 'record.contacts.email'),
            'description' => $this->buildDescription($item),
            'active' => (bool) (Arr::get($item, 'isActive') ?? Arr::get($item, 'record.isActive', true)),
            'sort_order' => 0,
            'source_payload' => $item,
        ];
    }

    private function buildDescription(array $item): ?string
    {
        $parts = array_filter([
            Arr::get($item, 'facilityType') ?? Arr::get($item, 'record.type'),
            Arr::get($item, 'districtName') ?? Arr::get($item, 'hierarchies.resolvedLocation.district.name'),
            Arr::get($item, 'hierarchies.resolvedLocation.subcounty.name'),
        ]);

        return $parts ? implode(' | ', $parts) : null;
    }
}
