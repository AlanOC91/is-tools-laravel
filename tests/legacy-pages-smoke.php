<?php

// Read-only rendering check against the isolated imported export.
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment(['local', 'testing'])) {
    throw new RuntimeException('Use only the local verification database.');
}
$badge = DB::table('security_groups_users as m')->join('security_groups as g', 'g.id', '=', 'm.group_id')->where('g.name', 'I.S. Staff')->value('m.user_id');
$admin = User::where('badge_id', $badge)->firstOrFail();
$meetingId = DB::table('meetings')->value('id');
$requestId = DB::table('requests')->value('id');
DB::beginTransaction();
try {
    foreach (['/', '/requests', '/requests/'.$requestId, '/meetings', '/meetings/'.$meetingId, '/data', '/deliveries', '/document-archive', '/office365', '/meeting-report'] as $path) {
        Auth::setUser($admin);
        $response = $app->handle(Request::create('https://localhost:3071'.$path));
        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException($path.' returned '.$response->getStatusCode());
        }
        fwrite(STDOUT, 'PASS '.$path.PHP_EOL);
    }
} finally {
    DB::rollBack();
}
