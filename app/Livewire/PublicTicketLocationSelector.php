<?php

namespace App\Livewire;

use App\Models\Facility;
use App\Models\Region;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

class PublicTicketLocationSelector extends Component
{
    public string $regionId = '';
    public string $districtName = '';
    public string $facilityId = '';
    public string $districtSearch = '';
    public string $facilitySearch = '';

    public function mount(
        mixed $selectedRegionId = null,
        mixed $selectedDistrictName = null,
        mixed $selectedFacilityId = null,
    ): void {
        $this->regionId = $selectedRegionId !== null ? (string) $selectedRegionId : '';
        $this->districtName = $selectedDistrictName !== null ? (string) $selectedDistrictName : '';
        $this->facilityId = $selectedFacilityId !== null ? (string) $selectedFacilityId : '';

        if ($this->facilityId !== '') {
            $facility = Facility::query()
                ->select('id', 'region_id', 'district_name')
                ->find((int) $this->facilityId);

            if ($facility) {
                if ($this->regionId === '') {
                    $this->regionId = (string) $facility->region_id;
                }

                if ($this->districtName === '' && $facility->district_name) {
                    $this->districtName = $facility->district_name;
                }
            }
        }

        $this->normalizeSelections();
    }

    public function updatedRegionId(): void
    {
        $this->districtName = '';
        $this->facilityId = '';
        $this->districtSearch = '';
        $this->facilitySearch = '';
    }

    public function updatedDistrictName(): void
    {
        $this->facilityId = '';
        $this->facilitySearch = '';
        $this->normalizeSelections();
    }

    public function updatedDistrictSearch(): void
    {
        if ($this->districtSearch === '') {
            return;
        }

        $match = $this->districtOptions()
            ->first(fn (string $district) => mb_strtolower($district) === mb_strtolower($this->districtSearch));

        if ($match !== null) {
            $this->districtName = $match;
        }
    }

    public function updatedFacilitySearch(): void
    {
        if ($this->facilitySearch === '') {
            return;
        }

        $match = $this->facilityOptions()
            ->first(fn (Facility $facility) => mb_strtolower($facility->name) === mb_strtolower($this->facilitySearch));

        if ($match) {
            $this->facilityId = (string) $match->id;
        }
    }

    public function render(): View
    {
        return view('livewire.public-ticket-location-selector', [
            'regions' => Region::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'districtOptions' => $this->districtOptions(),
            'facilityOptions' => $this->facilityOptions(),
        ]);
    }

    private function districtOptions(): Collection
    {
        return Facility::query()
            ->where('active', true)
            ->when($this->regionId !== '', fn ($query) => $query->where('region_id', (int) $this->regionId))
            ->whereNotNull('district_name')
            ->where('district_name', '!=', '')
            ->when(
                trim($this->districtSearch) !== '',
                fn ($query) => $query->where('district_name', 'like', '%'.trim($this->districtSearch).'%')
            )
            ->distinct()
            ->orderBy('district_name')
            ->pluck('district_name')
            ->values();
    }

    private function facilityOptions(): Collection
    {
        return Facility::query()
            ->where('active', true)
            ->when($this->regionId !== '', fn ($query) => $query->where('region_id', (int) $this->regionId))
            ->when($this->districtName !== '', fn ($query) => $query->where('district_name', $this->districtName))
            ->when(
                trim($this->facilitySearch) !== '',
                fn ($query) => $query->where('name', 'like', '%'.trim($this->facilitySearch).'%')
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function normalizeSelections(): void
    {
        if ($this->districtName !== '' && ! $this->districtOptions()->contains($this->districtName)) {
            $this->districtName = '';
        }

        if ($this->facilityId === '') {
            return;
        }

        $facilityExists = $this->facilityOptions()
            ->contains(fn (Facility $facility) => (string) $facility->id === $this->facilityId);

        if (! $facilityExists) {
            $this->facilityId = '';
        }
    }
}
