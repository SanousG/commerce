<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserLocaleRequest;
use Illuminate\Http\RedirectResponse;

class UserLocaleController extends Controller
{
    public function update(UpdateUserLocaleRequest $request): RedirectResponse
    {
        $locale = $request->validated('locale');

        $request->user()->forceFill([
            'admin_locale' => $locale,
        ])->save();

        app()->setLocale($locale);

        return back()->with('status', __('Language preference saved.'));
    }
}
