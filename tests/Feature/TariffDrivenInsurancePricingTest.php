<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Core\Database\Factories\ServiceFactory;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Models\TariffItem;
use Modules\Insurance\Services\DefaultInsurancePricingService;
use Modules\Patient\Database\Factories\PatientFactory;
use Tests\TestCase;

class TariffDrivenInsurancePricingTest extends TestCase
{
    use DatabaseTransactions;

    private DefaultInsurancePricingService $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Insurance']);
        $this->resolver = $this->app->make(DefaultInsurancePricingService::class);
    }

    public function test_resolves_tariff_via_service_nhis_code_metadata(): void
    {
        $payer = Payer::factory()->create(['type' => 'nhis']);
        $patient = PatientFactory::new()->create();
        $service = ServiceFactory::new()->create([
            'price' => 200.00,
            'metadata' => ['nhis_code' => 'CONS01'],
        ]);

        PatientPolicy::factory()->create([
            'patient_id' => $patient->id,
            'payer_id' => $payer->id,
            'is_active' => true,
        ]);

        TariffItem::factory()->create([
            'payer_id' => $payer->id,
            'external_code' => 'CONS01',
            'item_type' => 'service',
            'price' => 150.00,
            'is_active' => true,
        ]);

        $result = $this->resolver->resolveForItem(
            patientId: (string) $patient->id,
            itemType: 'service',
            externalCode: (string) $service->id,
            fallbackAmount: '200.00',
        );

        $this->assertEquals('150.00', $result['insurer_amount']);
        $this->assertEquals('50.00', $result['patient_amount']);
        $this->assertEquals('150.00', $result['tariff_price']);
    }

    public function test_falls_back_to_full_amount_when_tariff_not_found(): void
    {
        $payer = Payer::factory()->create(['type' => 'nhis']);
        $patient = PatientFactory::new()->create();
        $service = ServiceFactory::new()->create([
            'price' => 200.00,
            'metadata' => ['nhis_code' => 'NONEXISTENT'],
        ]);

        PatientPolicy::factory()->create([
            'patient_id' => $patient->id,
            'payer_id' => $payer->id,
            'is_active' => true,
        ]);

        $result = $this->resolver->resolveForItem(
            patientId: (string) $patient->id,
            itemType: 'service',
            externalCode: (string) $service->id,
            fallbackAmount: '200.00',
        );

        $this->assertEquals('0.00', $result['insurer_amount']);
        $this->assertEquals('200.00', $result['patient_amount']);
        $this->assertNull($result['tariff_price']);
    }

    public function test_falls_back_to_raw_service_id_when_no_nhis_code(): void
    {
        $payer = Payer::factory()->create(['type' => 'nhis']);
        $patient = PatientFactory::new()->create();
        $service = ServiceFactory::new()->create([
            'price' => 200.00,
            'metadata' => [],
        ]);

        PatientPolicy::factory()->create([
            'patient_id' => $patient->id,
            'payer_id' => $payer->id,
            'is_active' => true,
        ]);

        TariffItem::factory()->create([
            'payer_id' => $payer->id,
            'external_code' => (string) $service->id,
            'item_type' => 'service',
            'price' => 180.00,
            'is_active' => true,
        ]);

        $result = $this->resolver->resolveForItem(
            patientId: (string) $patient->id,
            itemType: 'service',
            externalCode: (string) $service->id,
            fallbackAmount: '200.00',
        );

        $this->assertEquals('180.00', $result['insurer_amount']);
        $this->assertEquals('20.00', $result['patient_amount']);
    }

    public function test_returns_zero_when_no_active_policy(): void
    {
        $patient = PatientFactory::new()->create();
        $service = ServiceFactory::new()->create([
            'price' => 200.00,
            'metadata' => ['nhis_code' => 'CONS01'],
        ]);

        $result = $this->resolver->resolveForItem(
            patientId: (string) $patient->id,
            itemType: 'service',
            externalCode: (string) $service->id,
            fallbackAmount: '200.00',
        );

        $this->assertEquals('0.00', $result['insurer_amount']);
        $this->assertEquals('200.00', $result['patient_amount']);
        $this->assertNull($result['tariff_price']);
    }
}
