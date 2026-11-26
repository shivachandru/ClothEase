<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FacilityController extends Controller
{
    public function logo(string $path)
    {
        abort_unless(str_starts_with($path, 'facility-logos/'), 404);
        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path);
    }

    public function edit(Request $request)
    {
        $shop = $request->user()->shop;
        abort_unless($shop, 404);

        return view('facility.edit', [
            'shop' => $shop,
            'users' => $shop->users()->with('accessRole')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request)
    {
        $shop = $request->user()->shop;
        abort_unless($shop, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'subheader' => ['nullable', 'string', 'max:200'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:1000'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'use_celandine_font' => ['sometimes', 'boolean'],
        ]);

        unset($data['logo']);
        $data['use_celandine_font'] = $request->boolean('use_celandine_font');
        if ($request->hasFile('logo')) {
            $oldLogo = $shop->logo_path;
            $data['logo_path'] = $request->file('logo')->store('facility-logos', 'public');
            if ($oldLogo) {
                Storage::disk('public')->delete($oldLogo);
            }
        }

        $shop->update($data);

        return redirect()->route('facility.edit')->with('success', 'Facility profile saved.');
    }
}
