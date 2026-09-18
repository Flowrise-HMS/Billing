<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Modules\Billing\Filament\Clusters\Billing\Widgets\TillReconciliationTableWidget;
use Tests\TestCase;

class TillReconciliationTableWidgetTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Billing']);
    }

    private function buildPayload(string $status = 'open'): array
    {
        return [
            'summary_date' => now()->toDateString(),
            'branch_id' => '1',
            'opening_cash' => '200.00',
            'counted_closing' => '300.00',
            'cashiers' => [
                '1' => [
                    'cashier_name' => 'Kofi Mensah',
                    'opening_cash' => '200.00',
                    'cash_in' => '500.00',
                    'cash_refunds' => '-50.00',
                    'change_given' => '10.00',
                    'expected_closing' => '640.00',
                    'variance' => '-340.00',
                    'status' => $status,
                ],
            ],
        ];
    }

    public function test_renders_empty_when_no_payload(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(TillReconciliationTableWidget::class)
            ->assertSeeText('No till data');
    }

    public function test_renders_cashier_row_from_payload(): void
    {
        $payload = $this->buildPayload();

        Livewire::actingAs(User::factory()->create())
            // `closeoutPayload` is a #[Reactive] prop: it can only be supplied by the
            // parent (DailyCashCloseout), so mount with it rather than ->set() it.
            ->test(TillReconciliationTableWidget::class, ['closeoutPayload' => $payload])
            ->assertSeeText('Kofi Mensah')
            ->assertSeeText('200.00')
            ->assertSeeText('500.00')
            // Counted closing is an editable input, so its value is state rather than text.
            ->assertTableColumnStateSet('counted_closing', '300.00', record: '0');
    }

    public function test_open_status_shows_finalize_action_not_reopen(): void
    {
        $payload = $this->buildPayload(status: 'open');

        Livewire::actingAs(User::factory()->create())
            // `closeoutPayload` is a #[Reactive] prop: it can only be supplied by the
            // parent (DailyCashCloseout), so mount with it rather than ->set() it.
            ->test(TillReconciliationTableWidget::class, ['closeoutPayload' => $payload])
            ->assertActionVisible(TestAction::make('finalize')->table('0'))
            ->assertActionHidden(TestAction::make('reopen')->table('0'));
    }

    public function test_finalized_status_shows_reopen_action_not_finalize(): void
    {
        $payload = $this->buildPayload(status: 'finalized');

        Livewire::actingAs(User::factory()->create())
            // `closeoutPayload` is a #[Reactive] prop: it can only be supplied by the
            // parent (DailyCashCloseout), so mount with it rather than ->set() it.
            ->test(TillReconciliationTableWidget::class, ['closeoutPayload' => $payload])
            // "Finalize" would also match the "Finalized" status badge, so check the actions themselves.
            ->assertActionVisible(TestAction::make('reopen')->table('0'))
            ->assertActionHidden(TestAction::make('finalize')->table('0'));
    }

    public function test_variance_is_displayed(): void
    {
        $payload = $this->buildPayload();

        Livewire::actingAs(User::factory()->create())
            // `closeoutPayload` is a #[Reactive] prop: it can only be supplied by the
            // parent (DailyCashCloseout), so mount with it rather than ->set() it.
            ->test(TillReconciliationTableWidget::class, ['closeoutPayload' => $payload])
            ->assertSeeText('340.00');
    }
}
