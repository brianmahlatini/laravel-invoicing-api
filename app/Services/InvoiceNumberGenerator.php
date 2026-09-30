<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Gap-free, per-user, per-year sequential numbers (INV-2026-0001), which many
 * tax authorities require. The counter row is locked FOR UPDATE, so two
 * concurrent requests can't both take the same number.
 */
final class InvoiceNumberGenerator
{
    public function next(User $user, int $year): string
    {
        return DB::transaction(function () use ($user, $year): string {
            $row = DB::table('invoice_sequences')->where('user_id', $user->id)->where('year', $year)->lockForUpdate()->first();
            if ($row === null) {
                DB::table('invoice_sequences')->insert(['user_id' => $user->id, 'year' => $year, 'last_number' => 1]);
                $n = 1;
            } else {
                $n = $row->last_number + 1;
                DB::table('invoice_sequences')->where('user_id', $user->id)->where('year', $year)->update(['last_number' => $n]);
            }

            return sprintf('INV-%d-%04d', $year, $n);
        });
    }
}
