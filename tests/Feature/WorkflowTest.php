<?php

namespace Tests\Feature;

use App\Jobs\DeliverRequest;
use App\Models\Delivery;
use App\Models\Meeting;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\DirectoryService;
use App\Services\FormCatalog;
use App\Services\PdfService;
use App\Services\SafeHtml;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
        config(['is-tools.support_email' => 'support@example.test', 'is-tools.ilab_email' => 'lab@example.test']);
        DB::table('departments')->insert(['name' => 'Medicine']);
        DB::table('software')->insert(['name' => 'Clinical System']);
    }

    private function staff(string $badge = 'doctor', bool $admin = false): User
    {
        $user = User::create(['badge_id' => $badge, 'username' => $badge, 'email' => $badge.'@example.test']);
        if ($admin) {
            $id = DB::table('security_groups')->insertGetId(['name' => 'I.S. Staff']);
            DB::table('security_groups_users')->insert(['user_id' => $badge, 'group_id' => $id]);
        }

        return $user;
    }

    private function signature(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII=';
    }

    private function payload(string $slug): array
    {
        $data = [];
        foreach (FormCatalog::get($slug)['fields'] as $key => $field) {
            $data[$key] = match ($field['type']) {
                'select' => FormCatalog::options($field)[0],
                'checkboxes' => [FormCatalog::options($field)[0]],
                'date' => '2026-10-22', 'email' => 'staff@example.test', default => 'Example '.$key,
            };
        }
        if (FormCatalog::get($slug)['signature'] ?? false) {
            $data += ['signature' => $this->signature(), 'consent' => '1'];
        }

        return $data;
    }

    public function test_all_guest_pages_render_and_old_form_bookmarks_redirect(): void
    {
        foreach (['/', '/forms', '/forms/ad', '/forms/nimis', '/forms/ilab', '/login', '/apps', '/about', '/healthwellbeing'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/forms/ad.php')->assertRedirect('/forms/ad');
        $this->get('/meetings')->assertRedirect('/login');
        $this->get('/forms/software')->assertRedirect('/login');
    }

    public function test_all_authenticated_pages_render_with_legacy_group_permissions(): void
    {
        $this->actingAs($this->staff('admin', true));
        foreach (['/', '/my-requests', '/requests', '/meetings', '/meetings/create', '/meeting-report', '/data', '/deliveries', '/document-archive', '/office365'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach (array_keys(FormCatalog::all()) as $slug) {
            $this->get('/forms/'.$slug)->assertOk();
        }
    }

    public function test_guest_and_staff_cannot_access_admin_data_or_submit_restricted_forms(): void
    {
        $this->post('/forms/software', $this->payload('software'))->assertForbidden();
        $this->actingAs($this->staff());
        foreach (['/requests', '/data', '/deliveries', '/office365', '/document-archive', '/forms/cyber-incident-report'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/forms/cyber-incident-report', $this->payload('cyber-incident-report'))->assertForbidden();
        $this->post('/data/software', ['action' => 'add', 'name' => 'Forbidden'])->assertForbidden();
        $this->assertDatabaseMissing('software', ['name' => 'Forbidden']);
    }

    public function test_every_form_retains_legacy_request_types_and_saves_a_durable_notification(): void
    {
        $admin = $this->staff('admin', true);
        foreach (FormCatalog::all() as $slug => $form) {
            if ($form['access'] === 'guest') {
                Auth::logout();
            } else {
                $this->actingAs($admin);
            }
            $this->post('/forms/'.$slug, $this->payload($slug))->assertSessionHasNoErrors()->assertRedirect('/forms/'.$slug);
            $item = ServiceRequest::latest('id')->first();
            $this->assertSame($form['type'], $item->type);
            $this->assertSame($form['access'] === 'guest' ? 'System Request' : 'admin', $item->requested_by);
            $this->assertSame('incomplete', $item->state);
            $this->assertSame($slug, $item->submission->form);
            $this->assertArrayNotHasKey('signature', $item->submission->payload);
            $this->assertDatabaseHas('is_tools_deliveries', ['request_id' => $item->id, 'state' => 'pending']);
            if ($form['signature'] ?? false) {
                Storage::disk('documents')->assertExists($item->submission->signature_path);
            }
        }
        $this->assertDatabaseCount('requests', 10);
        $this->assertDatabaseCount('is_tools_deliveries', 11);
    }

    public function test_validation_blocks_missing_signatures_invalid_images_and_date_ranges(): void
    {
        $data = $this->payload('ad');
        unset($data['signature']);
        $this->post('/forms/ad', $data)->assertSessionHasErrors('signature');
        $data['signature'] = 'data:image/png;base64,not-png';
        $this->post('/forms/ad', $data)->assertSessionHasErrors('signature');
        $this->actingAs($this->staff());
        $data = $this->payload('assetreq');
        $data['req_to'] = '2025-01-01';
        $this->post('/forms/assetreq', $data)->assertSessionHasErrors('req_to');
        $this->assertDatabaseCount('requests', 0);
    }

    public function test_ilab_duplicate_check_uses_existing_records_and_normalizes_case_and_whitespace(): void
    {
        $data = $this->payload('ilab');
        DB::table('ilab_requests')->insert(['forename' => strtolower($data['first_name']), 'surname' => strtolower($data['last_name'])]);
        $data['first_name'] = '  '.$data['first_name'].'  ';
        $this->post('/forms/ilab', $data)->assertSessionHasErrors('first_name');
        $this->assertDatabaseCount('requests', 0);
        $this->assertDatabaseCount('ilab_requests', 1);
    }

    public function test_ad_department_field_is_retained_and_historical_messages_are_sanitized(): void
    {
        $data = $this->payload('ad');
        $data['from'] = $data['from_dep'];
        unset($data['from_dep']);
        $data['extra_requirements'] = '<script>alert(1)</script>';
        $this->post('/api/forms/ad.php', $data)->assertSessionHasNoErrors();
        $this->assertStringContainsString('Medicine', ServiceRequest::first()->message);
        $html = SafeHtml::clean('<script>alert(1)</script><img src=x onerror=alert(1)><a href="javascript:alert(1)">bad</a><strong>History</strong>');
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('<strong>History</strong>', $html);
    }

    public function test_only_admin_can_process_requests_asset_completion_requires_tag_and_processing_is_final(): void
    {
        $staff = $this->staff();
        $admin = $this->staff('admin', true);
        $item = ServiceRequest::create(['type' => 'asset request', 'message' => 'Loan', 'requested_by' => $staff->badge_id]);
        $this->actingAs($staff)->patch('/requests/'.$item->id, ['state' => 'completed'])->assertForbidden();
        $this->actingAs($admin)->patch('/requests/'.$item->id, ['state' => 'completed'])->assertSessionHasErrors('asset_tag');
        $this->patch('/requests/'.$item->id, ['state' => 'completed', 'asset_tag' => 'SJL-123'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('requests', ['id' => $item->id, 'state' => 'completed', 'notes' => 'SJL-123', 'approved_by' => 'admin']);
        $this->patch('/requests/'.$item->id, ['state' => 'rejected'])->assertStatus(409);
        $this->get('/api/requests/approve.php?request='.$item->id)->assertNotFound();
    }

    public function test_staff_can_view_only_their_requests_and_signatures_while_admin_can_view_legacy_files(): void
    {
        $owner = $this->staff('owner');
        $other = $this->staff('other');
        $admin = $this->staff('admin', true);
        $item = ServiceRequest::create(['type' => 'asset request', 'message' => 'Legacy', 'requested_by' => $owner->badge_id]);
        $item->signature()->create(['type' => 'assetreq', 'sigSlug' => 'legacy-person-2025-02-24-12-17-30']);
        Storage::disk('documents')->put('signatures/assetreq/legacy-person-2025-02-24-12-17-30.png', base64_decode(substr($this->signature(), 22)));
        $this->actingAs($other)->get('/requests/'.$item->id)->assertForbidden();
        $this->get('/requests/'.$item->id.'/signature')->assertForbidden();
        $this->actingAs($owner)->get('/requests/'.$item->id)->assertOk();
        $this->get('/requests/'.$item->id.'/signature')->assertOk();
        $this->actingAs($admin)->get('/requests/'.$item->id.'/signature')->assertOk();
        $item->signature->update(['sigSlug' => '../../.env']);
        $this->get('/requests/'.$item->id.'/signature')->assertNotFound();
    }

    public function test_meetings_save_both_presenters_and_attendees_atomically(): void
    {
        $this->actingAs($this->staff());
        $this->post('/meetings', ['presenter_name' => 'Presenter', 'presenter_name_2' => 'Second presenter', 'presenting_topic' => 'Teaching',
            'meeting_name' => 'NCHD', 'user_name' => ['Doctor One', 'Doctor Two', 'Doctor One']])->assertSessionHasNoErrors()->assertRedirect('/meetings/1');
        $this->assertDatabaseCount('meetings', 1);
        $this->assertDatabaseCount('meeting_users', 2);
        $this->assertDatabaseHas('meetings', ['presenter_name_2' => 'Second presenter']);
        $this->post('/meetings', ['presenter_name' => 'Presenter', 'presenting_topic' => 'Teaching', 'user_name' => []])->assertSessionHasErrors('user_name');
        $this->assertDatabaseCount('meetings', 1);
        $this->get('/meetings/1')->assertOk()->assertSee('Doctor Two');
    }

    public function test_reports_count_secondary_presenters_distinct_attendance_and_both_date_boundaries(): void
    {
        $this->actingAs($this->staff());
        foreach (['2026-10-01 00:00:00', '2026-10-31 23:59:59', '2026-11-01 00:00:00'] as $date) {
            $meeting = Meeting::create(['presenter_name' => 'Other', 'presenter_name_2' => 'Doctor', 'presenting_topic' => '=Danger', 'created_at' => $date]);
            $meeting->attendees()->createMany([['user_name' => 'Doctor'], ['user_name' => 'Doctor']]);
        }
        $query = http_build_query(['staff_name' => 'Doctor', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31']);
        $this->get('/meeting-report?'.$query)->assertOk()->assertViewHas('result', ['attended' => 2, 'presented' => 2, 'unique' => 2, 'total' => 4]);
        $response = $this->get('/meeting-report?'.$query.'&export=csv')->assertOk();
        $this->assertStringContainsString("'=Danger", $response->streamedContent());
        $this->get('/meeting-report?staff_name=Doctor&start_date=2026-10-31&end_date=2026-10-01')->assertSessionHasErrors('end_date');
    }

    public function test_login_uses_ldap_preserves_user_id_and_refreshes_admin_membership(): void
    {
        $user = $this->staff('existing', true);
        $this->mock(DirectoryService::class, function ($mock) {
            $mock->shouldReceive('authenticate')->with('existing', '  password  ')->once()->andReturn([
                'samaccountname' => ['existing'], 'displayname' => ['Existing Doctor'], 'memberof' => ['count' => 0],
            ]);
            $mock->shouldReceive('email')->andReturn('existing@example.test');
        });
        $this->post('/login', ['username' => 'existing', 'password' => '  password  ', 'remember' => '1'])->assertSessionHasNoErrors()->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertSame($user->id, User::where('badge_id', 'existing')->first()->id);
        $this->assertFalse($user->fresh()->isAdministrator());
        $this->assertNotEmpty($user->fresh()->remember_token);
        $this->assertDatabaseCount('verified_logins', 1);
        $this->assertDatabaseHas('login_log', ['badge_id' => 'existing']);
    }

    public function test_directory_outage_has_no_local_authentication_fallback(): void
    {
        $this->mock(DirectoryService::class, fn ($mock) => $mock->shouldReceive('authenticate')->andThrow(new \RuntimeException('Directory offline')));
        $this->post('/login', ['username' => 'admin', 'password' => 'password'])->assertStatus(503)->assertSee('Directory sign-in is unavailable');
        $this->assertGuest();
    }

    public function test_notification_failure_keeps_request_and_retry_delivers_pdf_with_signature(): void
    {
        $this->post('/forms/ilab', $this->payload('ilab'))->assertSessionHasNoErrors();
        $delivery = Delivery::first();
        Http::fakeSequence()->push('unavailable', 503)->push('%PDF-test', 200, ['Content-Type' => 'application/pdf']);
        try {
            (new DeliverRequest($delivery->id))->handle(new PdfService);
            $this->fail('PDF outage should fail delivery.');
        } catch (RequestException) {
        }
        $this->assertDatabaseCount('requests', 1);
        $this->assertDatabaseHas('is_tools_deliveries', ['id' => $delivery->id, 'state' => 'failed']);
        Mail::fake();
        (new DeliverRequest($delivery->id))->handle(new PdfService);
        $this->assertDatabaseHas('is_tools_deliveries', ['id' => $delivery->id, 'state' => 'sent', 'attempts' => 2]);
        Storage::disk('documents')->assertExists('pdf/ilab/request-1.pdf');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/forms/chromium/convert/html'));
        (new DeliverRequest($delivery->id))->handle(new PdfService);
        $this->assertSame(2, $delivery->fresh()->attempts);
    }

    public function test_lookup_mutations_are_audited_and_legacy_rows_survive_migration_replay(): void
    {
        $this->actingAs($this->staff('admin', true))->post('/data/software', ['action' => 'add', 'name' => 'New clinical tool'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('data_log', ['table_name' => 'software', 'action' => 'add', 'new_value' => 'New clinical tool']);
        $migration = require database_path('migrations/2026_10_22_000001_adopt_legacy_schema.php');
        $migration->up();
        $migration->down();
        $this->assertDatabaseHas('software', ['name' => 'New clinical tool']);
        $this->assertDatabaseHas('verified_logins', ['badge_id' => 'admin']);
    }
}
