@extends('layouts.app', ['title' => 'A-LIS Remote Backup Keys'])

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <style>
        .select2-container .select2-selection--single {
            min-height: 2.9rem;
            border: 1px solid var(--bs-border-color);
            border-radius: 0.75rem;
            padding: 0.45rem 0.75rem;
            display: flex;
            align-items: center;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 1.5rem;
            padding-left: 0;
            color: var(--cis-ink);
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 100%;
            right: 0.65rem;
        }

        .select2-dropdown {
            border-color: var(--bs-border-color);
            border-radius: 0.9rem;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(18, 59, 93, 0.12);
        }

        .select2-search--dropdown .select2-search__field {
            border-radius: 0.65rem;
            border-color: var(--bs-border-color);
        }
    </style>
@endpush

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <p class="text-uppercase text-muted fw-semibold small mb-1">Infrastructure</p>
            <h1 class="h2 mb-0">A-LIS Remote Backup Keys</h1>
        </div>
    </div>

    <livewire:alis-remote-backup-keys-manager />
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        (function () {
            function bindFacilitySelect(element) {
                const livewireRoot = element.closest('[wire\\:id]');

                if (! livewireRoot || ! window.jQuery || ! window.jQuery.fn.select2) {
                    return;
                }

                const $element = window.jQuery(element);

                if ($element.hasClass('select2-hidden-accessible')) {
                    $element.select2('destroy');
                }

                const modal = element.closest('.modal');

                $element.select2({
                    width: '100%',
                    placeholder: element.dataset.placeholder || 'Search facility',
                    allowClear: true,
                    dropdownParent: modal ? window.jQuery(modal) : window.jQuery(document.body),
                });

                const selectedValue = element.dataset.selectedValue || '';
                $element.val(selectedValue).trigger('change.select2');

                $element.off('change.select2-livewire').on('change.select2-livewire', function () {
                    const component = window.Livewire.find(livewireRoot.getAttribute('wire:id'));

                    if (component) {
                        component.set('facilityId', $element.val() || '');
                    }
                });
            }

            function initFacilitySelect2(root) {
                const scope = root || document;

                scope.querySelectorAll('.js-facility-select2').forEach(function (element) {
                    bindFacilitySelect(element);
                });
            }

            function bootFacilitySelect2() {
                initFacilitySelect2(document);

                if (window.Livewire && typeof window.Livewire.hook === 'function') {
                    window.Livewire.hook('morph.updated', function ({ el }) {
                        initFacilitySelect2(el);
                    });
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bootFacilitySelect2, { once: true });
            } else {
                bootFacilitySelect2();
            }

            document.addEventListener('livewire:initialized', bootFacilitySelect2, { once: true });
        })();
    </script>
@endpush
