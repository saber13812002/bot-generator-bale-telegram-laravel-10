<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

trait AssertsObservabilitySecret
{
    private function assertObservabilitySecret(Request $request, string $configKey): void
    {
        $expected = (string) config($configKey, '');
        if ($expected === '') {
            abort(404);
        }

        $provided = (string) (
            $request->route('secret')
            ?? $request->query('token')
            ?? $request->bearerToken()
            ?? ''
        );

        if ($provided === '' || !hash_equals($expected, $provided)) {
            abort(404);
        }
    }
}
