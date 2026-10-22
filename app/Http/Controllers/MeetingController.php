<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeetingController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => 'nullable|string|max:255']);
        $meetings = Meeting::withCount('attendees')->when($data['q'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q
            ->where('presenter_name', 'like', '%'.$v.'%')->orWhere('presenter_name_2', 'like', '%'.$v.'%')
            ->orWhere('presenting_topic', 'like', '%'.$v.'%')->orWhere('meeting_name', 'like', '%'.$v.'%')))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('meetings.index', compact('meetings'));
    }

    public function create()
    {
        return view('meetings.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['presenter_name' => 'required|string|max:255', 'presenter_name_2' => 'nullable|string|max:255',
            'presenting_topic' => 'required|string|max:255', 'meeting_name' => 'nullable|string|max:255',
            'created_at' => 'nullable|date', 'user_name' => 'required|array|min:1|max:500', 'user_name.*' => 'required|string|max:255']);
        $meeting = DB::transaction(function () use ($data) {
            $attendees = array_values(array_unique(array_map('trim', $data['user_name'])));
            unset($data['user_name']);
            $data['created_at'] = $data['created_at'] ?? now();
            $meeting = Meeting::create($data);
            $meeting->attendees()->createMany(array_map(fn ($name) => ['user_name' => $name], $attendees));
            Audit::record('meeting.created', $meeting->id, ['attendees' => count($attendees)]);

            return $meeting;
        });

        return redirect()->route('meetings.show', $meeting)->with('success', 'Meeting attendance saved.');
    }

    public function show(Meeting $meeting)
    {
        return view('meetings.show', ['meeting' => $meeting->load('attendees')]);
    }

    public function report(Request $request)
    {
        $result = null;
        $sessions = collect();
        if ($request->filled('staff_name') || $request->filled('start_date') || $request->filled('end_date')) {
            $data = $request->validate(['staff_name' => 'required|string|max:255', 'start_date' => 'required|date_format:Y-m-d', 'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date']);
            $sessions = Meeting::with('attendees')->whereBetween('created_at', [$data['start_date'].' 00:00:00', $data['end_date'].' 23:59:59'])
                ->where(fn ($q) => $q->where('presenter_name', $data['staff_name'])->orWhere('presenter_name_2', $data['staff_name'])
                    ->orWhereHas('attendees', fn ($q) => $q->where('user_name', $data['staff_name'])))->orderBy('created_at')->get();
            $result = ['attended' => 0, 'presented' => 0, 'unique' => $sessions->count()];
            foreach ($sessions as $meeting) {
                $match = fn ($name) => mb_strtolower($name ?? '') === mb_strtolower($data['staff_name']);
                if ($meeting->attendees->contains(fn ($a) => $match($a->user_name))) {
                    $result['attended']++;
                }
                if ($match($meeting->presenter_name) || $match($meeting->presenter_name_2)) {
                    $result['presented']++;
                }
            }
            $result['total'] = $result['attended'] + $result['presented'];
            if ($request->input('export') === 'csv') {
                Audit::record('meeting.report.exported', $data['staff_name'], $data);

                return response()->streamDownload(function () use ($sessions, $data, $result) {
                    $stream = fopen('php://output', 'w');
                    $write = function ($row) use ($stream) {
                        fputcsv($stream, array_map(fn ($cell) => preg_match('/^[=+@\-\t\r]/u', (string) $cell) ? "'".$cell : $cell, $row), ',', '"', '');
                    };
                    $write(['Staff member', $data['staff_name']]);
                    $write(['From', $data['start_date'], 'Until', $data['end_date']]);
                    $write(['Attended', $result['attended'], 'Presented', $result['presented'], 'Total', $result['total']]);
                    $write(['ID', 'Date', 'Meeting', 'Topic', 'Presenter', 'Secondary presenter']);
                    foreach ($sessions as $m) {
                        $write([$m->id, $m->created_at?->format('Y-m-d H:i'), $m->meeting_name, $m->presenting_topic, $m->presenter_name, $m->presenter_name_2]);
                    }
                    fclose($stream);
                }, 'meeting-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
            }
        }

        return view('meetings.report', compact('result', 'sessions'));
    }
}
