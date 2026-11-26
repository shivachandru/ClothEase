<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BillSettingController extends Controller
{
    public function edit(Request $request)
    {
        return view('masters.bill-settings', ['shop' => $request->user()->shop]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'bill_prefix' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/'],
            // Accept numeric input such as "01" from number fields, then normalize it.
            'bill_next_number' => ['required', 'regex:/^[0-9]{1,12}$/'],
            'barcode_use_prefix' => ['nullable', 'boolean'],
            'barcode_prefix' => ['nullable', 'required_if:barcode_use_prefix,1', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]*$/'],
            'barcode_next_number' => ['required', 'regex:/^[0-9]{1,12}$/'],
        ]);
        $data['bill_next_number'] = (int) $data['bill_next_number'];
        if ($data['bill_next_number'] < 1) {
            return back()->withErrors(['bill_next_number' => 'The next bill sequence must be at least 1.'])->withInput();
        }
        $data['barcode_next_number'] = (int) $data['barcode_next_number'];
        if ($data['barcode_next_number'] < 1) {
            return back()->withErrors(['barcode_next_number' => 'The barcode sequence must be at least 1.'])->withInput();
        }

        $shop = $request->user()->shop;
        $data['barcode_use_prefix'] = (bool) ($data['barcode_use_prefix'] ?? false);
        $data['barcode_prefix'] = $data['barcode_use_prefix'] ? trim((string) ($data['barcode_prefix'] ?? '')) : '';
        if ($data['barcode_use_prefix'] && $data['barcode_prefix'] === '') {
            return back()->withErrors(['barcode_prefix' => 'Enter a barcode prefix or turn off the prefix option.'])->withInput();
        }
        if ($request->boolean('reset_barcode_sequence')) {
            $data['barcode_next_number'] = 1;
        }
        $shop->update($data);

        return redirect()->route('masters.bill-settings.edit')->with('success', 'Billing and barcode settings saved.');
    }
}
