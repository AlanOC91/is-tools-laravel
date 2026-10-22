<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class RequestController extends Controller
{
    public function index(Request $request)
    {
        $mine = $request->routeIs('requests.mine');
        $filters = $request->validate(['q' => 'nullable|string|max:255', 'state' => ['nullable', Rule::in(['incomplete', 'completed', 'rejected'])], 'type' => 'nullable|string|max:255']);
        $base = ServiceRequest::query()->when($mine, fn ($q) => $q->where('requested_by', $request->user()->badge_id));
        $counts = (clone $base)->selectRaw('state, count(*) as total')->groupBy('state')->pluck('total', 'state');
        $types = (clone $base)->distinct()->orderBy('type')->pluck('type');
        $items = $base->with(['requester', 'signature', 'submission'])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('message', 'like', '%'.$term.'%')->orWhere('requested_by', 'like', '%'.$term.'%')->orWhere('id', $term)))
            ->when($filters['state'] ?? null, fn ($q, $v) => $q->where('state', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->orderByDesc('submitted_date')->orderByDesc('id')->paginate(18)->withQueryString();

        return view('requests.index', compact('items', 'counts', 'types', 'mine'));
    }

    public function show(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeRecord($request, $serviceRequest);

        return view('requests.show', ['item' => $serviceRequest->load('signature', 'submission', 'requester')]);
    }

    public function update(Request $request, ServiceRequest $serviceRequest)
    {
        $data = $request->validate(['state' => ['required', Rule::in(['completed', 'rejected'])], 'notes' => 'nullable|string|max:255', 'asset_tag' => 'nullable|string|max:255']);
        DB::transaction(function () use ($request, $serviceRequest, $data) {
            $item = ServiceRequest::whereKey($serviceRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($item->state === 'incomplete', 409, 'This request has already been processed.');
            if ($item->type === 'asset request' && $data['state'] === 'completed') {
                $request->validate(['asset_tag' => 'required|string|max:255']);
            }
            $item->update(['state' => $data['state'], 'approved_by' => $request->user()->name, 'completed_date' => now(),
                'notes' => $item->type === 'asset request' && $data['state'] === 'completed' ? $data['asset_tag'] : ($data['notes'] ?? null)]);
            Audit::record('request.'.$data['state'], $item->id);
        });

        return redirect()->route('requests.show', $serviceRequest)->with('success', 'Request marked '.$data['state'].'.');
    }

    public function signature(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeRecord($request, $serviceRequest);
        $sig = $serviceRequest->signature;
        abort_unless($sig, 404);
        foreach ([$sig->type, $sig->sigSlug] as $part) {
            abort_if(! $part || preg_match('~[/\\\\\x00]|\.\.~', $part), 404);
        }
        $path = 'signatures/'.$sig->type.'/'.$sig->sigSlug.'.png';
        abort_unless(Storage::disk('documents')->exists($path), 404, 'The signature file has not been transferred from the legacy server.');
        Audit::record('signature.viewed', $serviceRequest->id);

        return Storage::disk('documents')->response($path, null, ['Content-Type' => 'image/png']);
    }

    public function pdf(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeRecord($request, $serviceRequest);
        $path = $serviceRequest->submission?->pdf_path;
        abort_unless($path && Storage::disk('documents')->exists($path), 404);
        Audit::record('pdf.downloaded', $serviceRequest->id);

        return Storage::disk('documents')->download($path);
    }

    private function authorizeRecord(Request $request, ServiceRequest $item): void
    {
        abort_unless($request->user()->can('admin') || strcasecmp($item->requested_by, $request->user()->badge_id) === 0, 403);
    }
}
