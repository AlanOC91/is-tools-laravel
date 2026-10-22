<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\RequestController;
use App\Services\FormCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/login', [AuthController::class, 'create'])->middleware('guest')->name('login');
Route::post('/login', [AuthController::class, 'store'])->middleware(['guest', 'throttle:login']);
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');
Route::get('/forms', [FormController::class, 'index'])->name('forms.index');
Route::get('/forms/{form}', [FormController::class, 'show'])->where('form', implode('|', array_keys(FormCatalog::all())))->name('forms.show');
Route::post('/forms/{form}', [FormController::class, 'store'])->where('form', implode('|', array_keys(FormCatalog::all())))->middleware('throttle:submissions')->name('forms.store');
Route::get('/apps', fn () => view('links', ['section' => 'apps', 'title' => 'Hospital applications', 'description' => 'Your shortcuts to tools across the hospital.']))->name('apps');
Route::view('/about', 'about')->name('about');
Route::view('/healthwellbeing', 'wellbeing')->name('wellbeing');

Route::middleware('auth')->group(function () {
    Route::get('/my-requests', [RequestController::class, 'index'])->name('requests.mine');
    Route::get('/requests/{serviceRequest}', [RequestController::class, 'show'])->whereNumber('serviceRequest')->name('requests.show');
    Route::get('/requests/{serviceRequest}/signature', [RequestController::class, 'signature'])->name('requests.signature');
    Route::get('/requests/{serviceRequest}/pdf', [RequestController::class, 'pdf'])->name('requests.pdf');
    Route::get('/meetings', [MeetingController::class, 'index'])->name('meetings.index');
    Route::get('/meetings/create', [MeetingController::class, 'create'])->name('meetings.create');
    Route::post('/meetings', [MeetingController::class, 'store'])->middleware('throttle:submissions')->name('meetings.store');
    Route::match(['get', 'post'], '/meeting-report', [MeetingController::class, 'report'])->name('meetings.report');
    Route::get('/meetings/{meeting}', [MeetingController::class, 'show'])->whereNumber('meeting')->name('meetings.show');
    Route::get('/directory/search', DirectoryController::class)->middleware('throttle:60,1')->name('directory.search');
    Route::get('/department-folders', fn () => response()->json(FormCatalog::options(['options' => 'folders'])))->name('folders');
});

Route::middleware(['auth', 'can:admin'])->group(function () {
    Route::get('/requests', [RequestController::class, 'index'])->name('requests.index');
    Route::patch('/requests/{serviceRequest}', [RequestController::class, 'update'])->name('requests.update');
    Route::get('/data', [AdminController::class, 'data'])->name('admin.data');
    Route::post('/data/{table}', [AdminController::class, 'updateData'])->name('admin.data.update');
    Route::get('/deliveries', [AdminController::class, 'deliveries'])->name('admin.deliveries');
    Route::post('/deliveries/{delivery}/retry', [AdminController::class, 'retry'])->name('admin.deliveries.retry');
    Route::get('/document-archive', [AdminController::class, 'documents'])->name('admin.documents');
    Route::get('/documents/{path}', [AdminController::class, 'document'])->where('path', '.*')->name('admin.document');
    Route::get('/service-health', [AdminController::class, 'health'])->name('admin.health');
    Route::get('/office365', fn () => view('links', ['section' => 'office365', 'title' => 'Microsoft 365', 'description' => 'Administration shortcuts for the IS team.']))->name('office365');
});

// Retain bookmarks without retaining executable legacy PHP scripts in public/.
foreach (['index' => '/', 'login' => '/login', 'forms' => '/forms', 'meetings' => '/meetings', 'meeting' => '/meetings/create',
    'requests' => '/requests', 'data' => '/data', 'apps' => '/apps', 'office365' => '/office365', 'about' => '/about', 'healthwellbeing' => '/healthwellbeing'] as $old => $new) {
    Route::get('/'.$old.'.php', fn () => redirect($new, 301));
}
Route::get('/meeting_view.php', fn (Request $r) => redirect()->route('meetings.show', ['meeting' => (int) $r->query('id')]))->middleware('auth');
Route::match(['get', 'post'], '/meeting-report.php', [MeetingController::class, 'report'])->middleware('auth');
foreach (array_keys(FormCatalog::all()) as $slug) {
    Route::get('/forms/'.$slug.'.php', fn () => redirect()->route('forms.show', $slug, 301));
    Route::post('/api/forms/'.$slug.'.php', [FormController::class, 'store'])->defaults('form', $slug)->middleware('throttle:submissions');
}
Route::post('/api/account/login.php', [AuthController::class, 'store'])->middleware('throttle:login');
Route::get('/api/data/users/search.php', DirectoryController::class)->middleware(['auth', 'throttle:60,1']);
Route::get('/api/network/listFolders.php', fn () => response()->json(FormCatalog::options(['options' => 'folders'])))->middleware('auth');
Route::get('/api/health/service.php', [AdminController::class, 'health'])->middleware(['auth', 'can:admin']);
// Old GET approval/rejection links deliberately have no mutation route; updates require POST + CSRF.
