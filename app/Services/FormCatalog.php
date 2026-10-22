<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class FormCatalog
{
    public static function all(): array
    {
        $text = fn ($label, $required = false, $type = 'text') => ['label' => $label, 'type' => $type, 'required' => $required];
        $choice = fn ($label, $options, $multiple = false, $required = false) => ['label' => $label, 'type' => $multiple ? 'checkboxes' : 'select', 'options' => $options, 'required' => $required];
        $person = ['first_name' => $text('First name', true), 'last_name' => $text('Last name', true)];
        $date = $text('Required from', true, 'date');
        $extra = $text('Additional requirements', false, 'textarea');
        $equipment = $choice('Equipment', ['Monitor', 'PC', 'Scanner', 'Printer', 'Keyboard', 'Mouse'], true);
        $software = $choice('Software required', 'software', true);
        $folder = $choice('Department folder access required', ['Yes'], true);

        return [
            'ad' => ['title' => 'Hospital login account', 'type' => 'active directory', 'icon' => 'user-round-plus', 'access' => 'guest', 'signature' => true,
                'description' => 'Request a hospital network login, email and department access.',
                'fields' => $person + ['staff_number' => $text('Staff number'), 'from_dep' => $choice('Joining department', 'departments', false, true),
                    'user_requirements' => $choice('Account requirements', ['Email', 'Department Folder F Drive Access'], true),
                    'extra_requirements' => $extra, 'contact_email' => $text('Contact email', true, 'email')]],
            'nimis' => ['title' => 'NIMIS radiology account', 'type' => 'nimis', 'icon' => 'scan-line', 'access' => 'guest', 'signature' => true,
                'description' => 'Apply for access to the national medical imaging system.',
                'fields' => $person + ['staff_number' => $text('Staff number'), 'org_role' => $text('Role in organisation'),
                    'medical_number' => $text('Medical Council number'), 'aba_number' => $text('An Bord Altranais number'),
                    'sjl_email' => $text("St. John's email", false, 'email'), 'hse_email' => $text('HSE email', false, 'email')]],
            'ilab' => ['title' => 'iLab bloods account', 'type' => 'lab', 'icon' => 'flask-conical', 'access' => 'guest', 'signature' => true, 'pdf' => true,
                'description' => 'Request access to the iLab (APEX) laboratory system.',
                'fields' => $person + ['staff_grade' => $text('Staff grade', true), 'staff_location' => $text('Staff location', true),
                    'contact_number' => $text('Contact number'), 'contact_email' => $text('Contact email', true, 'email'),
                    'req_from' => $date, 'line_manager' => $text('Line manager', true)]],
            'drive' => ['title' => 'Department folder access', 'type' => 'network', 'icon' => 'folder-key', 'access' => 'staff',
                'description' => 'Request access to a department folder on the F: drive.',
                'fields' => ['from' => $choice('Department folder', 'folders', false, true), 'on_behalf_of' => $text('On behalf of user (optional)', false, 'staff'),
                    'access_level' => $choice('Access level', ['Read', 'Read & Write', 'Read & Write Manager'], true, true)]],
            'assetreq' => ['title' => 'Equipment loan', 'type' => 'asset request', 'icon' => 'laptop', 'access' => 'staff', 'signature' => true,
                'description' => 'Arrange a laptop, tablet, mobile phone, printer or scanner loan.',
                'fields' => ['asset_type' => $choice('Asset type', ['laptop', 'tablet', 'mobile phone', 'printer', 'scanner'], false, true),
                    'req_from' => $date, 'req_to' => $text('Required until', true, 'date'), 'extra_requirements' => $extra]],
            'software' => ['title' => 'Software access', 'type' => 'software', 'icon' => 'app-window', 'access' => 'staff',
                'description' => 'Request access to clinical and business applications.',
                'fields' => ['required_software' => $choice('Software required', 'software', true, true),
                    'healthirl_username' => $text('HealthIRL username'), 'staff_professional_number' => $text('Staff professional number'),
                    'req_from' => $date, 'extra_requirements' => $extra]],
            'joining' => ['title' => 'New starter', 'type' => 'joining', 'icon' => 'badge-plus', 'access' => 'staff',
                'description' => 'Arrange equipment and access for a new member of staff.',
                'fields' => ['staff_member' => $text('New staff member', true), 'from' => $choice('Joining department', 'departments', false, true),
                    'equipment_move' => $equipment, 'department_folder' => $folder, 'required_software' => $software]],
            'moving' => ['title' => 'Moving department', 'type' => 'moving', 'icon' => 'arrow-right-left', 'access' => 'staff',
                'description' => 'Move your equipment and arrange access in a new department.',
                'fields' => ['from' => $choice('Current department', 'departments', false, true), 'to' => $choice('New department', 'departments', false, true),
                    'equipment_move' => $equipment, 'department_folder' => $folder, 'required_software' => $software]],
            'leaving' => ['title' => 'Staff leaving', 'type' => 'leaving', 'icon' => 'user-round-minus', 'access' => 'staff',
                'description' => 'Let IS know when one or more staff members are leaving.',
                'fields' => ['staff_member' => $text('Staff member', false, 'staff'), 'staff_members' => $text('Multiple staff members (one per line)', false, 'textarea'),
                    'dep_date' => $text('Departure date', true, 'date')]],
            'cyber-incident-report' => ['title' => 'Cyber incident report', 'type' => 'cyber-incident-report', 'icon' => 'shield-alert', 'access' => 'admin', 'pdf' => true,
                'description' => 'Record a security incident and notify IS and the information governance officer.',
                'fields' => ['staff_member' => $text('Staff member who alerted you', false, 'staff'), 'staff_email' => $text('Staff email', false, 'email'),
                    'affected_systems' => $text('Affected systems / hostnames / IP addresses', true, 'textarea'),
                    'nature_of_incident' => $text('Nature of the incident', true, 'textarea'), 'potential_impact' => $text('Potential impact', false, 'textarea'),
                    'description_of_activity' => $text('Activity and supporting evidence', true, 'textarea')]],
        ];
    }

    public static function get(string $slug): array
    {
        abort_unless(isset(self::all()[$slug]), 404);

        return self::all()[$slug];
    }

    public static function options(array $field): array
    {
        $source = $field['options'] ?? [];
        if (is_array($source)) {
            return $source;
        }
        if ($source === 'folders') {
            $path = config('is-tools.department_folders_path');
            if ($path && is_dir($path)) {
                $folders = array_values(array_filter(scandir($path), fn ($name) => $name !== '.' && $name !== '..' && is_dir($path.DIRECTORY_SEPARATOR.$name)));
                natcasesort($folders);

                return array_values($folders);
            }
            $source = 'departments';
        }

        return DB::table($source)->orderBy('name')->pluck('name')->map(fn ($v) => html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8'))->all();
    }
}
