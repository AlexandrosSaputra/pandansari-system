<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $units = DB::table('operational_units')
            ->select(['id', 'code', 'name'])
            ->orderBy('id')
            ->get();
        $occupiedCodes = [];

        foreach ($units as $unit) {
            if (preg_match('/^[A-Z]{2}\d{4}$/', (string) $unit->code) === 1) {
                $occupiedCodes[$unit->code] = true;
            }
        }

        foreach ($units as $unit) {
            if (preg_match('/^[A-Z]{2}\d{4}$/', (string) $unit->code) === 1) {
                continue;
            }

            $prefix = $this->prefix((string) $unit->name);
            $code = $this->availableCode($prefix, $occupiedCodes);

            DB::table('operational_units')
                ->where('id', $unit->id)
                ->update(['code' => $code]);

            $occupiedCodes[$code] = true;
        }
    }

    /**
     * Legacy codes cannot be restored after normalization.
     */
    public function down(): void {}

    private function prefix(string $name): string
    {
        $letters = preg_replace('/[^A-Z]/', '', Str::upper(Str::ascii($name))) ?? '';

        return substr($letters.'XX', 0, 2);
    }

    /**
     * @param  array<string, bool>  $occupiedCodes
     */
    private function availableCode(string $prefix, array $occupiedCodes): string
    {
        for ($offset = 1; $offset <= 10000; $offset++) {
            $code = sprintf('%s%04d', $prefix, $offset % 10000);

            if (! isset($occupiedCodes[$code])) {
                return $code;
            }
        }

        throw new RuntimeException("No operational unit codes remain for prefix {$prefix}.");
    }
};
