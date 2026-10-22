<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function data()
    {
        return view('admin.data', ['departments' => DB::table('departments')->orderBy('name')->get(), 'software' => DB::table('software')->orderBy('name')->get(),
            'changes' => DB::table('data_log')->orderByDesc('id')->limit(30)->get(),
            'groups' => DB::table('security_groups as g')->join('security_groups_users as u', 'g.id', '=', 'u.group_id')->select('g.name')->selectRaw('COUNT(*) as total')->groupBy('g.name')->orderBy('g.name')->get()]);
    }

    public function updateData(Request $request, string $table)
    {
        abort_unless(in_array($table, ['software', 'departments'], true), 404);
        $data = $request->validate(['action' => ['required', Rule::in(['add', 'update', 'delete'])], 'id' => 'required_unless:action,add|nullable|integer|min:1',
            'name' => ['required_unless:action,delete', 'nullable', 'string', 'max:255', Rule::unique($table, 'name')->ignore($request->input('id'))]]);
        DB::transaction(function () use ($table, $data, $request) {
            $old = null;
            if ($data['action'] !== 'add') {
                $record = DB::table($table)->where('id', $data['id'])->lockForUpdate()->first();
                abort_unless($record, 404);
                $old = $record->name;
            }
            $new = $data['action'] === 'delete' ? null : $data['name'];
            match ($data['action']) {
                'add' => DB::table($table)->insert(['name' => $new]),
                'update' => DB::table($table)->where('id', $data['id'])->update(['name' => $new]),
                'delete' => DB::table($table)->where('id', $data['id'])->delete(),
            };
            DB::table('data_log')->insert(['badge_id' => $request->user()->badge_id, 'table_name' => $table,
                'action' => $data['action'], 'old_value' => $old, 'new_value' => $new, 'change_timestamp' => now()]);
        });

        return redirect()->route('admin.data')->with('success', 'Lookup data updated.');
    }

    public function deliveries()
    {
        return view('admin.deliveries', ['deliveries' => Delivery::orderByDesc('id')->paginate(30)]);
    }

    public function retry(Delivery $delivery)
    {
        abort_if($delivery->state === 'sent', 409, 'This notification has already been sent.');
        $recipient = $delivery->recipient ?: config('is-tools.'.($delivery->channel === 'ilab' ? 'ilab_email' : 'support_email'));
        $delivery->update(['state' => 'pending', 'last_error' => null, 'recipient' => $recipient ?? '']);
        Audit::record('delivery.retry', $delivery->id);

        return back()->with('success', 'Notification queued for another delivery attempt.');
    }

    public function documents()
    {
        $files = collect(Storage::disk('documents')->allFiles())->filter(fn ($p) => str_ends_with(strtolower($p), '.pdf'))->sort()->values();

        return view('admin.documents', compact('files'));
    }

    public function document(string $path)
    {
        abort_if(str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, "\0") || ! preg_match('/\.(pdf|png)$/i', $path), 404);
        abort_unless(Storage::disk('documents')->exists($path), 404);
        Audit::record('archive.downloaded', $path);

        return Storage::disk('documents')->download($path);
    }

    public function health()
    {
        try {
            $up = Http::timeout(3)->get(rtrim(preg_replace('~/forms/chromium/convert/html/?$~', '', config('is-tools.gotenberg_url')), '/').'/health')->successful();
        } catch (\Throwable) {
            $up = false;
        }

        return response()->json(['status' => $up ? 'up' : 'down']);
    }

    public function testEmail(Request $request)
    {
        $data = $request->validate(['recipient' => 'required|email:rfc|max:255']);
        try {
            Mail::raw('This is a test message from St. John\'s Hospital IS Tools.', fn ($message) => $message->to($data['recipient'])->subject('IS Tools — email delivery test'));
            Audit::record('mail.test', $data['recipient']);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['recipient' => 'The mail server could not deliver the test message. Check the server log and mail configuration.']);
        }

        return back()->with('success', 'Test message submitted to the configured mail transport.');
    }
}
