# Billing module

**In one sentence:** Billing turns care activities into invoices, tracks payments, and manages gateway/webhook flows so facilities know what is owed, paid, and still outstanding.

## Why this module exists

Clinical documentation alone does not close a patient journey. Facilities also need:

- invoice creation and issuance,
- line-level charge tracking,
- payment capture and allocation,
- unpaid reminders and reconciliation.

This module contains those financial workflows.

## Where Billing fits in FlowRise

- Uses **Core** context (branches, permissions, shared infrastructure).
- Uses **Patient** context for balances and patient-facing notifications.
- Syncs with **Clinical/Appointment** generated work through event/listener patterns and related billing line updates.
- Is a dependency target for **Insurance** claim orchestration.

```mermaid
flowchart LR
  Core[Core]
  Patient[Patient]
  Clinical[Clinical]
  Appointment[Appointment]
  Billing[Billing]
  Insurance[Insurance]
  Core --> Billing
  Patient --> Billing
  Clinical --> Billing
  Appointment --> Billing
  Billing --> Insurance
```

## What you can do with it

- Create and issue invoices with detailed invoice lines.
- Record and allocate payments (full or partial).
- Generate receipts, invoice PDFs, and revenue exports.
- Manage per-branch payment gateway settings.
- Process payment webhooks and checkout session flows.
- Trigger reminders/notifications for unpaid bills.

## How it works (simple)

1. Chargeable activity is added as invoice lines (manually or through sync listeners).
2. Totals and status are computed through billing services.
3. Payment intents and gateway flows confirm payment events.
4. Allocations update invoice balance and paid/partially-paid state.
5. Notifications, reports, and downstream modules (like Insurance) consume billing outcomes.

## What is inside this folder

| Path | Purpose |
|------|---------|
| `app/Models/` | Invoice, invoice line, payment, payment intent/allocation, webhook events. |
| `app/Services/` | Totals, issuance, checkout, recording, balance, receipts, reporting. |
| `app/Gateways/` | Payment gateway manager plus provider drivers (Paystack/Stripe/Flutterwave/Hubtel). |
| `app/Events/` + `app/Listeners/` | Lifecycle events and sync/finalization listeners. |
| `app/Filament/` | Billing plugin, cluster, and relation managers. |
| `app/Http/Controllers/` | API/web endpoints for checkout, payment status, webhooks, exports, PDFs. |
| `app/Notifications/` + `app/Mail/` | Patient-facing billing notices and mail templates. |

## Dependencies

- `flowrise-hms/core`
- `flowrise-hms/patient`
- `flowrise-hms/appointment`
- `barryvdh/laravel-dompdf`

Current rollout: [module status](../../docs/shared/module-status.md).

## For developers

- **Namespace:** `Modules\Billing\...`
- **Service provider:** `Modules\Billing\Providers\BillingServiceProvider`
- Billing is already event-driven in several places (invoice line sync, encounter finalization, unpaid notices). Extend through events/listeners before adding hard controller coupling.

## Useful commands

```bash
php artisan module:migrate Billing
php artisan test Modules/Billing
```
