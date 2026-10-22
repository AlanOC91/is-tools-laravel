<?php

namespace App\Jobs;

use App\Models\Delivery;
use App\Services\FormCatalog;
use App\Services\PdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Message;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DeliverRequest implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 100;

    public int $uniqueFor = 600;

    public function __construct(public int $deliveryId) {}

    public function uniqueId(): string
    {
        return (string) $this->deliveryId;
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(PdfService $pdf): void
    {
        try {
            DB::transaction(function () use ($pdf) {
                $delivery = Delivery::whereKey($this->deliveryId)->lockForUpdate()->firstOrFail();
                if ($delivery->state === 'sent') {
                    return;
                }
                if (! filter_var($delivery->recipient, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Configure a valid delivery recipient (SUPPORT_EMAIL / ILAB_EMAIL).');
                }
                $request = $delivery->request;
                $submission = $request->submission()->lockForUpdate()->firstOrFail();
                $form = FormCatalog::get($submission->form);
                $attachment = ($form['pdf'] ?? false) ? $pdf->generate($submission) : null;
                $body = view('emails.request', compact('request', 'form'))->render();
                Mail::html($body, function (Message $message) use ($delivery, $attachment) {
                    $message->to($delivery->recipient)->subject($delivery->subject);
                    foreach (['cc', 'bcc'] as $kind) {
                        if ($delivery->$kind) {
                            $message->$kind(array_filter(array_map('trim', explode(',', $delivery->$kind))));
                        }
                    }
                    if ($attachment) {
                        $message->attach(Storage::disk('documents')->path($attachment), ['mime' => 'application/pdf']);
                    }
                });
                $delivery->update(['state' => 'sent', 'sent_at' => now(), 'attempts' => $delivery->attempts + 1, 'last_error' => null]);
            });
        } catch (Throwable $e) {
            Delivery::whereKey($this->deliveryId)->update(['state' => 'failed', 'attempts' => DB::raw('attempts + 1'), 'last_error' => mb_substr($e->getMessage(), 0, 2000)]);
            throw $e;
        }
    }
}
