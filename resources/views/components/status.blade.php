@props(['value'])
<span class="status status-{{ $value }}"><span></span>{{ ['incomplete' => 'Pending', 'completed' => 'Completed', 'rejected' => 'Rejected', 'pending' => 'Queued', 'sent' => 'Sent', 'failed' => 'Failed'][$value] ?? ucfirst($value ?? 'Unknown') }}</span>
