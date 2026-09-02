<?php

namespace Modules\Billing\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Modules\Billing\Services\PatientBalanceQueryService;

/**
 * Compact outstanding-balance card for a patient, contributed to the Clinical
 * Workspace's patient_banner slot via the PageWidgetsRegistry (Clinical never
 * imports Billing). Visibility is tied to the view_patient_balance permission.
 */
class PatientBillingSummaryWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'billing::filament.widgets.patient-billing-summary';

    public ?string $patientId = null;

    public static function canView(): bool
    {
        return Auth::user()?->can('view_patient_balance') ?? false;
    }

    #[Computed]
    public function outstandingBalance(): ?string
    {
        if (blank($this->patientId) || ! static::canView()) {
            return null;
        }

        return app(PatientBalanceQueryService::class)->openBalanceForPatient($this->patientId);
    }
}
