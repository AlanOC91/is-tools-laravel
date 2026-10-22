<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitForm;
use App\Services\FormCatalog;
use App\Services\SubmissionService;
use Illuminate\Http\Request;

class FormController extends Controller
{
    public function index()
    {
        return view('forms.index', ['forms' => FormCatalog::all()]);
    }

    public function show(Request $request, string $form)
    {
        $definition = FormCatalog::get($form);
        if ($definition['access'] !== 'guest' && ! $request->user()) {
            return redirect()->guest(route('login'));
        }
        abort_if($definition['access'] === 'admin' && ! $request->user()?->can('admin'), 403);

        return view('forms.show', ['slug' => $form, 'form' => $definition]);
    }

    public function store(SubmitForm $request, string $form, SubmissionService $service)
    {
        $saved = $service->submit($form, $request->validated(), $request->user());

        return redirect()->route('forms.show', $form)->with('success', 'Your request has been saved. Reference #'.$saved->id.'. I.S. Support will process it.');
    }
}
