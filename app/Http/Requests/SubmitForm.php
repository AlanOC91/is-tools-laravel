<?php

namespace App\Http\Requests;

use App\Services\FormCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitForm extends FormRequest
{
    public function authorize(): bool
    {
        $access = FormCatalog::get($this->route('form'))['access'];

        return $access === 'guest' || ($this->user() && ($access !== 'admin' || $this->user()->can('admin')));
    }

    protected function prepareForValidation(): void
    {
        // The old AD page posted "from" while its handler expected "from_dep".
        if ($this->route('form') === 'ad' && ! $this->has('from_dep') && $this->has('from')) {
            $this->merge(['from_dep' => $this->input('from')]);
        }
        if ($this->route('form') === 'drive' && is_array($this->input('from'))) {
            $this->merge(['from' => implode(', ', $this->input('from'))]);
        }
    }

    public function rules(): array
    {
        $form = FormCatalog::get($this->route('form'));
        $rules = ['website' => 'prohibited'];
        foreach ($form['fields'] as $key => $field) {
            $rule = [$field['required'] ? 'required' : 'nullable'];
            if ($field['type'] === 'checkboxes') {
                $rules[$key] = [...$rule, 'array', 'max:100'];
                $rules[$key.'.*'] = ['string', 'distinct', Rule::in(FormCatalog::options($field))];
            } else {
                $rule[] = 'string';
                $rule[] = 'max:'.($field['type'] === 'textarea' ? '10000' : '255');
                if ($field['type'] === 'date') {
                    $rule[] = 'date_format:Y-m-d';
                }
                if ($field['type'] === 'email') {
                    $rule[] = 'email:rfc';
                }
                if ($field['type'] === 'select') {
                    $rule[] = Rule::in(FormCatalog::options($field));
                }
                $rules[$key] = $rule;
            }
        }
        if ($form['signature'] ?? false) {
            $rules['signature'] = ['required', 'string', 'max:700000', function ($attribute, $value, $fail) {
                if (! str_starts_with($value, 'data:image/png;base64,')) {
                    $fail('Please draw a signature.');

                    return;
                }
                $image = base64_decode(substr($value, 22), true);
                $info = $image === false ? false : @getimagesizefromstring($image);
                if (! $info || $info[2] !== IMAGETYPE_PNG || $info[0] > 2400 || $info[1] > 1200 || strlen($image) > 500000) {
                    $fail('Please provide a valid PNG signature.');
                }
            }];
            $rules['consent'] = 'accepted';
        }
        if ($this->route('form') === 'assetreq') {
            $rules['req_to'][] = 'after_or_equal:req_from';
        }
        if ($this->route('form') === 'leaving') {
            $rules['staff_member'][] = 'required_without:staff_members';
            $rules['staff_members'][] = 'required_without:staff_member';
        }

        return $rules;
    }
}
