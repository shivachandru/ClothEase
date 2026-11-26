<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReceiptTemplateController extends Controller
{
    public const DEFAULT_ALIGNMENT = [
        'header' => 'center',
        'details' => 'left',
        'items' => 'left',
        'summary' => 'left',
        'payments' => 'left',
        'gst' => 'center',
        'footer' => 'center',
    ];

    public function edit(Request $request)
    {
        $shop = $request->user()->shop;
        $alignment = array_merge(self::DEFAULT_ALIGNMENT, $shop->receipt_alignment ?? []);
        $fontSize = (int) ($shop->receipt_font_size ?: 10);
        $headerFontSize = (int) ($shop->receipt_header_font_size ?: 18);
        $itemsFontSize = (int) ($shop->receipt_items_font_size ?: 9);
        $subheaderGstFontSize = (int) ($shop->receipt_subheader_gst_font_size ?: 10);
        $showGstin = (bool) ($shop->receipt_show_gstin ?? true);

        return view('masters.receipt-template', compact('shop', 'alignment', 'fontSize', 'headerFontSize', 'itemsFontSize', 'subheaderGstFontSize', 'showGstin'));
    }

    public function update(Request $request)
    {
        $shop = $request->user()->shop;
        if ($request->boolean('reset_alignment')) {
            $shop->update(['receipt_alignment' => self::DEFAULT_ALIGNMENT]);

            return redirect()->route('masters.receipt-template.edit')->with('success', 'Receipt alignment restored to defaults.');
        }

        $fields = array_keys(self::DEFAULT_ALIGNMENT);
        $rules = ['receipt_alignment' => ['required', 'array']];
        foreach ($fields as $field) {
            $rules["receipt_alignment.$field"] = ['required', Rule::in(['left', 'center', 'right'])];
        }

        $rules['receipt_font_size'] = ['required', 'integer', 'min:8', 'max:16'];
        $rules['receipt_header_font_size'] = ['required', 'integer', 'min:12', 'max:30'];
        $rules['receipt_items_font_size'] = ['required', 'integer', 'min:7', 'max:16'];
        $rules['receipt_subheader_gst_font_size'] = ['required', 'integer', 'min:7', 'max:16'];
        $rules['receipt_show_gstin'] = ['sometimes', 'boolean'];
        $data = $request->validate($rules);
        $shop->update([
            'receipt_alignment' => $data['receipt_alignment'],
            'receipt_font_size' => $data['receipt_font_size'],
            'receipt_header_font_size' => $data['receipt_header_font_size'],
            'receipt_items_font_size' => $data['receipt_items_font_size'],
            'receipt_subheader_gst_font_size' => $data['receipt_subheader_gst_font_size'],
            'receipt_show_gstin' => $request->boolean('receipt_show_gstin'),
        ]);

        return redirect()->route('masters.receipt-template.edit')->with('success', 'Receipt template saved.');
    }
}
