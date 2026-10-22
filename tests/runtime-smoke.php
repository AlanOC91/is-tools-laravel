<?php

use App\Jobs\DeliverRequest;
use App\Models\Delivery;
use App\Services\PdfService;
use App\Services\SubmissionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Run from the application root, inside the verification container.
// Uses a real MariaDB transaction and real Gotenberg; never sends SMTP mail.
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment(['local', 'testing'])) {
    throw new RuntimeException('Runtime smoke tests are restricted to local/testing environments.');
}
config(['mail.default' => 'array', 'is-tools.support_email' => 'support@example.test', 'is-tools.ilab_email' => 'lab@example.test']);
$request = null;
DB::beginTransaction();
try {
    $request = app(SubmissionService::class)->submit('ilab', [
        'first_name' => 'Runtime Verification', 'last_name' => Str::uuid()->toString(),
        'staff_grade' => 'Test', 'staff_location' => 'Local verification only', 'contact_number' => '000',
        'contact_email' => 'verification@example.test', 'req_from' => '2026-10-22', 'line_manager' => 'Test Manager',
        'consent' => '1',
        'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII=',
    ], null);
    foreach (Delivery::where('request_id', $request->id)->get() as $delivery) {
        (new DeliverRequest($delivery->id))->handle(app(PdfService::class));
        if ($delivery->fresh()->state !== 'sent') {
            throw new RuntimeException('Delivery did not complete.');
        }
    }
    $request->load('submission');
    $pdf = Storage::disk('documents')->get($request->submission->pdf_path);
    if (! str_starts_with($pdf, '%PDF-') || strlen($pdf) < 5000) {
        throw new RuntimeException('Invalid PDF output.');
    }
    fwrite(STDOUT, 'PASS: MariaDB submission, signature, real Gotenberg PDF ('.strlen($pdf).' bytes), support and iLab delivery. No SMTP mail sent.'.PHP_EOL);
} finally {
    $paths = $request ? $request->submission()->first(['signature_path', 'pdf_path']) : null;
    DB::rollBack();
    foreach (array_filter([$paths?->signature_path, $paths?->pdf_path]) as $path) {
        Storage::disk('documents')->delete($path);
    }
    fwrite(STDOUT, 'Verification rows rolled back; generated verification files removed.'.PHP_EOL);
}
