<?php

namespace Modules\Billing\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Notifications\Concerns\BuildsLinePaymentUrl;
use Modules\Core\Enums\ServiceCategoryCode;
use Modules\Core\Notifications\Concerns\ResolvesNotificationChannels;

class InvoiceLineOrderedNotification extends Notification implements ShouldQueue
{
    use BuildsLinePaymentUrl, Queueable, ResolvesNotificationChannels;

    public function __construct(protected InvoiceLine $line) {}

    public function via(object $notifiable): array
    {
        return $this->settingsChannels($notifiable, 'invoice_line_ordered_mail', 'invoice_line_ordered_sms');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $line = $this->line->loadMissing(['invoice', 'service.category']);
        $invoice = $line->invoice;

        $serviceName = $line->service?->name ?? $line->description ?? __('Service');
        $checkoutUrl = $this->linePaymentUrl($line);

        return (new MailMessage)
            ->subject(__(':kind ordered', ['kind' => $this->orderKindLabel($line)]))
            ->view('billing::emails.invoice-line-ordered', [
                'line' => $line,
                'invoice' => $invoice,
                'serviceName' => $serviceName,
                'orderKind' => $this->orderKindLabel($line),
                'amountDue' => $line->remainingAmount(),
                'checkoutUrl' => $checkoutUrl,
            ]);
    }

    public function toSms(object $notifiable): string
    {
        $line = $this->line->loadMissing(['invoice', 'service.category']);

        $serviceName = $line->service?->name ?? $line->description ?? __('Service');
        $checkoutUrl = $this->linePaymentUrl($line);

        $message = __(':kind ordered: :service (:currency :amount).', [
            'kind' => $this->orderKindLabel($line),
            'service' => $serviceName,
            'currency' => $line->invoice->currency,
            'amount' => $line->remainingAmount(),
        ]);

        if ($checkoutUrl) {
            $message .= ' '.__('Pay now: :url', ['url' => $checkoutUrl]);
        }

        return $message;
    }

    protected function orderKindLabel(InvoiceLine $line): string
    {
        $code = $line->service?->category?->code;

        return match (true) {
            $code === ServiceCategoryCode::LAB => __('Lab test'),
            $code === ServiceCategoryCode::MED, $code === ServiceCategoryCode::PHA => __('Medication'),
            $code === ServiceCategoryCode::RAD, $code === ServiceCategoryCode::DIA => __('Diagnostic scan'),
            default => __('Service'),
        };
    }
}
