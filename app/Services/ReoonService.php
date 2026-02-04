<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ReoonService
{
    public function verify(string $email): array
    {
        try {
            $response = Http::timeout(10)->get(
                config('services.reoon.url'),
                [
                    'email' => $email,
                    'key'   => config('services.reoon.key'),
                ]
            );

            if (!$response->successful()) {
                return $this->unknown();
            }

            $data = $response->json();

            // 🔑 REAL STATUS FROM REOON
            $status = strtolower($data['status'] ?? 'unknown');

            // ✅ VERIFIED RULES
            $verifiedStatuses = [
                'valid',
            ];

            // ❌ BLOCKED RULES
            $blockedStatuses = [
                'invalid',
                'disposable',
            ];

            return [
                'status'   => $status,
                'verified' => in_array($status, $verifiedStatuses),
                'blocked'  => in_array($status, $blockedStatuses),
                'raw'      => $data,
            ];

        } catch (\Throwable $e) {
            return $this->unknown();
        }
    }

    private function unknown(): array
    {
        return [
            'status' => 'unknown',
            'verified' => false,
            'blocked' => false,
            'raw' => null,
        ];
    }
}
