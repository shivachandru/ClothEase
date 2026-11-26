<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;

class BarcodePrintController extends Controller
{
    public function print(Request $request, ProductVariant $variant)
    {
        abort_unless($variant->shop_id === $request->user()->shop_id, 404);

        $data = $request->validate(['rows' => ['required', 'integer', 'min:1', 'max:500']]);
        abort_unless(PHP_OS_FAMILY === 'Windows', 501, 'Direct barcode printing requires the Windows app and printer driver.');

        $variant->load('product');
        $code = strtoupper(trim($variant->barcode ?: $variant->sku));
        if (!preg_match('/^[A-Z0-9 .\-$\/+%]+$/', $code)) {
            return response()->json(['message' => 'The barcode must use Code 39 characters: A–Z, 0–9, space, or - . $ / + %.'], 422);
        }

        $payload = [
            'printer' => config('printing.barcode_printer'),
            'rows' => (int) $data['rows'],
            'name' => $variant->product->name,
            'variant' => collect([$variant->size, $variant->colour])->filter()->join(' ') ?: 'Standard',
            'price' => (string) $variant->selling_price,
            'code' => $code,
        ];

        $script = base_path('app/Support/PrintBarcodeLabel.ps1');
        if (!is_file($script)) {
            return response()->json(['message' => 'The Windows barcode printing helper is missing.'], 503);
        }

        $process = new Process(['powershell.exe', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', $script]);
        $process->setTimeout(60);
        $process->setInput(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        try {
            $process->run();
        } catch (\Throwable $exception) {
            logger()->error('Barcode label printing could not start.', ['printer' => $payload['printer'], 'error' => $exception->getMessage()]);
            return response()->json(['message' => 'Could not start the Windows printing helper. Confirm PHP can start PowerShell and the Retsol driver is installed.'], 503);
        }

        if (!$process->isSuccessful()) {
            logger()->error('Barcode label printing failed.', ['printer' => $payload['printer'], 'error' => trim($process->getErrorOutput() ?: $process->getOutput())]);
            return response()->json(['message' => 'Could not print. Check that the Retsol R220 is installed and its Windows printer name matches CLOTHEASE_BARCODE_PRINTER.'], 503);
        }

        return response()->json(['message' => "Sent {$payload['rows']} row(s) to {$payload['printer']}. Each row contains two labels."]);
    }
}
