<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AppearanceController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'sidebar_icon_color' => ['nullable', 'string', 'regex:/^(#[0-9a-fA-F]{6})?$/'],
        ]);

        $request->user()->update([
            'sidebar_icon_color' => $request->sidebar_icon_color ?: null,
        ]);

        return back();
    }
}
