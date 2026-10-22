<?php

namespace App\Services;

use App\Models\Submission;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PdfService
{
    public function generate(Submission $submission): string
    {
        $disk = Storage::disk('documents');
        if ($submission->pdf_path && $disk->exists($submission->pdf_path)) {
            return $submission->pdf_path;
        }
        $form = FormCatalog::get($submission->form);
        $signature = $submission->signature_path ? 'data:image/png;base64,'.base64_encode($disk->get($submission->signature_path)) : null;
        $logo = is_file(public_path('images/logo.png')) ? 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/logo.png'))) : null;
        $html = view('pdf.request', compact('submission', 'form', 'signature', 'logo'))->render();
        $url = rtrim(config('is-tools.gotenberg_url'), '/');
        // Accept the old full endpoint as well as the service base URL.
        if (! str_ends_with($url, '/forms/chromium/convert/html')) {
            $url .= '/forms/chromium/convert/html';
        }
        $response = Http::timeout(60)->attach('files', $html, 'index.html')->post($url, [
            'printBackground' => 'true', 'paperWidth' => '8.27', 'paperHeight' => '11.7', 'marginTop' => '0.4', 'marginBottom' => '0.4',
        ])->throw();
        if (! str_starts_with($response->body(), '%PDF-')) {
            throw new RuntimeException('The PDF service did not return a PDF.');
        }
        $path = 'pdf/'.$submission->form.'/request-'.$submission->request_id.'.pdf';
        $disk->put($path, $response->body());
        $submission->update(['pdf_path' => $path]);

        return $path;
    }
}
