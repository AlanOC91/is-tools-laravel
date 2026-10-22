<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SubmissionService
{
    public function submit(string $slug, array $data, ?User $actor): ServiceRequest
    {
        $form = FormCatalog::get($slug);
        $signature = $data['signature'] ?? null;
        unset($data['signature'], $data['website']);
        $path = null;
        try {
            return DB::transaction(function () use ($slug, $form, $data, $signature, $actor, &$path) {
                // Keep the legacy guest identity, including its existing foreign-key relationships.
                $system = User::firstOrCreate(['badge_id' => 'System Request'], ['username' => 'System Request']);
                User::whereKey($system->id)->lockForUpdate()->first();
                if ($slug === 'ilab' && DB::table('ilab_requests')
                    ->whereRaw('LOWER(TRIM(forename)) = ?', [mb_strtolower(trim($data['first_name']))])
                    ->whereRaw('LOWER(TRIM(surname)) = ?', [mb_strtolower(trim($data['last_name']))])->exists()) {
                    throw ValidationException::withMessages(['first_name' => 'An iLab account has already been requested for this name. Please contact I.S. Support on extension '.config('is-tools.support_extension').'.']);
                }
                $requester = $form['access'] === 'guest' ? $system : $actor;
                $message = '<p><strong>'.e($form['title']).'</strong></p>';
                if ($actor) {
                    $message .= '<p>Submitted by: '.e($actor->name).'</p>';
                }
                foreach ($form['fields'] as $key => $field) {
                    $value = $data[$key] ?? null;
                    if ($value === null || $value === '' || $value === []) {
                        continue;
                    }
                    $message .= '<p>'.e($field['label']).': <strong>'.nl2br(e(is_array($value) ? implode(', ', $value) : $value)).'</strong></p>';
                }
                $request = ServiceRequest::create(['type' => $form['type'], 'requested_by' => $requester->badge_id,
                    'message' => $message, 'state' => 'incomplete', 'submitted_date' => now()]);
                if ($signature) {
                    $sigSlug = Str::uuid()->toString();
                    $path = 'signatures/'.$slug.'/'.$sigSlug.'.png';
                    Storage::disk('documents')->put($path, base64_decode(substr($signature, 22), true));
                    $request->signature()->create(['type' => $slug, 'sigSlug' => $sigSlug]);
                }
                $request->submission()->create(['form' => $slug, 'payload' => $data, 'signature_path' => $path]);
                if ($slug === 'ilab') {
                    DB::table('ilab_requests')->insert(['forename' => $data['first_name'], 'surname' => $data['last_name'], 'date_sent' => now()]);
                }
                $this->delivery($request, config('is-tools.support_email') ?? '', 'IS Tools — '.$form['title'], $slug === 'cyber-incident-report' ? config('is-tools.igo_email') : null);
                if ($slug === 'ilab') {
                    $this->delivery($request, config('is-tools.ilab_email') ?? '', 'iLab Account Request — '.$data['first_name'].' '.$data['last_name'], config('is-tools.ilab_cc'), config('is-tools.ilab_bcc'));
                }
                Audit::record('request.submitted', $request->id, ['form' => $slug]);

                return $request;
            });
        } catch (Throwable $e) {
            if ($path) {
                Storage::disk('documents')->delete($path);
            }
            throw $e;
        }
    }

    private function delivery(ServiceRequest $request, string $to, string $subject, ?string $cc = null, ?string $bcc = null): void
    {
        Delivery::create(['request_id' => $request->id, 'recipient' => $to, 'subject' => $subject, 'cc' => $cc ?: null, 'bcc' => $bcc ?: null]);
    }
}
