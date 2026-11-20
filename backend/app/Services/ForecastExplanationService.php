<?php

namespace App\Services;

use App\Models\ForecastRun;
use App\Support\ForecastDataFreshness;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ForecastExplanationService
{
    private const OLLAMA_RISKS = [
        'weekly_data' => 'Data mingguan tidak menunjukkan variasi permintaan harian.',
        'stockouts' => 'Penjualan saat stok habis mungkin tidak mencerminkan seluruh permintaan.',
        'forecast_uncertainty' => 'Prediksi memiliki ketidakpastian dan perlu ditinjau sebelum pengadaan.',
    ];

    private const OLLAMA_ACTIONS = [
        'check_stock' => 'Periksa stok fisik dan transfer yang sedang berjalan sebelum pengadaan.',
        'review_products' => 'Tinjau rekomendasi tiap produk dan sesuaikan dengan kondisi operasional.',
        'review_history' => 'Periksa kelengkapan dan ketepatan data penjualan historis.',
    ];

    public function __construct(private ForecastDataFreshness $freshness) {}

    /** @return array{status: string, model: ?string} */
    public function runtimeStatus(): array
    {
        $provider = (string) config('forecast.explanation.provider', 'none');

        if ($provider !== 'ollama') {
            return ['status' => $provider === 'none' ? 'disabled' : 'not_checked', 'model' => null];
        }

        $model = (string) config('forecast.explanation.ollama.model');

        try {
            $baseUrl = rtrim((string) config('forecast.explanation.ollama.base_url'), '/');
            $response = Http::acceptJson()
                ->connectTimeout(1)
                ->timeout(2)
                ->get($baseUrl.'/api/tags');

            if (! $response->successful() || ! is_array($response->json('models'))) {
                return ['status' => 'unavailable', 'model' => $model];
            }

            $expected = str_contains(basename($model), ':') ? $model : $model.':latest';
            $installed = collect($response->json('models'))->contains(
                fn (mixed $entry): bool => is_array($entry)
                    && ($entry['name'] ?? $entry['model'] ?? null) === $expected,
            );

            return ['status' => $installed ? 'ready' : 'model_missing', 'model' => $model];
        } catch (ConnectionException) {
            return ['status' => 'unavailable', 'model' => $model];
        }
    }

    public function explain(ForecastRun $run): ForecastRun
    {
        $provider = (string) config('forecast.explanation.provider', 'none');
        $fallback = $this->fallback($run);

        if ($provider === 'none') {
            $run->update([
                'explanation_provider' => 'none',
                'explanation_status' => 'disabled',
                'explanation' => $fallback,
            ]);

            return $run->refresh();
        }

        try {
            $explanation = match ($provider) {
                'openai' => $this->openAi($run),
                'ollama' => $this->ollama($run),
                default => throw new RuntimeException("Unsupported forecast explanation provider: {$provider}"),
            };

            $run->update([
                'explanation_provider' => $provider,
                'explanation_status' => 'generated',
                'explanation' => $this->validate($explanation),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Forecast explanation provider failed.', [
                'forecast_run_id' => $run->id,
                'provider' => $provider,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            $run->update([
                'explanation_provider' => $provider,
                'explanation_status' => 'fallback',
                'explanation' => $fallback,
            ]);
        }

        return $run->refresh();
    }

    /** @return array{summary: string, risks: array<int, string>, actions: array<int, string>} */
    private function openAi(ForecastRun $run): array
    {
        $apiKey = (string) config('forecast.explanation.openai.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $baseUrl = rtrim((string) config('forecast.explanation.openai.base_url'), '/');
        $response = $this->request()->withToken($apiKey)->post($baseUrl.'/responses', [
            'model' => config('forecast.explanation.openai.model'),
            'store' => false,
            'instructions' => $this->instructions(),
            'input' => json_encode($this->payload($run), JSON_THROW_ON_ERROR),
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'forecast_explanation',
                    'strict' => true,
                    'schema' => $this->schema(),
                ],
            ],
        ])->throw()->json();

        foreach ($response['output'] ?? [] as $output) {
            foreach ($output['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text' && isset($content['text'])) {
                    return json_decode($content['text'], true, flags: JSON_THROW_ON_ERROR);
                }
            }
        }

        throw new RuntimeException('OpenAI response did not contain structured output text.');
    }

    /** @return array{summary: string, risks: array<int, string>, actions: array<int, string>} */
    private function ollama(ForecastRun $run): array
    {
        $baseUrl = rtrim((string) config('forecast.explanation.ollama.base_url'), '/');
        $response = $this->request()->post($baseUrl.'/api/chat', [
            'model' => config('forecast.explanation.ollama.model'),
            'stream' => false,
            'format' => $this->ollamaSchema(),
            'options' => ['temperature' => 0],
            'messages' => [
                ['role' => 'system', 'content' => 'Pilih kode risiko dan tindakan yang relevan untuk forecast. Gunakan hanya kode yang tersedia dalam skema JSON; jangan membuat teks atau kode baru.'],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'forecast' => $this->payload($run),
                        'risk_options' => self::OLLAMA_RISKS,
                        'action_options' => self::OLLAMA_ACTIONS,
                    ], JSON_THROW_ON_ERROR),
                ],
            ],
        ])->throw()->json();
        $content = $response['message']['content'] ?? null;

        if (! is_string($content)) {
            throw new RuntimeException('Ollama response did not contain message content.');
        }

        $selection = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($selection)) {
            throw new RuntimeException('Ollama selection is not an object.');
        }

        $riskCodes = $this->validateCodes($selection['risk_codes'] ?? null, self::OLLAMA_RISKS);
        $actionCodes = $this->validateCodes($selection['action_codes'] ?? null, self::OLLAMA_ACTIONS);
        $explanation = $this->fallback($run);
        $explanation['risks'] = array_values(array_filter(
            $explanation['risks'],
            fn (string $risk): bool => $risk !== 'Data historis mingguan tidak dapat menjelaskan permintaan harian atau penjualan yang hilang saat stok habis.',
        ));
        $explanation['actions'] = array_slice($explanation['actions'], 2);

        foreach ($riskCodes as $code) {
            $explanation['risks'][] = self::OLLAMA_RISKS[$code];
        }
        foreach ($actionCodes as $code) {
            $explanation['actions'][] = self::OLLAMA_ACTIONS[$code];
        }

        return $explanation;
    }

    /** @param array<string, string> $allowed
     * @return array<int, string>
     */
    private function validateCodes(mixed $codes, array $allowed): array
    {
        if (! is_array($codes) || ! array_is_list($codes) || count($codes) === 0 || count($codes) > count($allowed)) {
            throw new RuntimeException('Ollama selection has an invalid list of codes.');
        }

        foreach ($codes as $code) {
            if (! is_string($code) || ! array_key_exists($code, $allowed)) {
                throw new RuntimeException('Ollama selection contains an unknown code.');
            }
        }

        return array_values(array_unique($codes));
    }

    /** @return array<string, mixed> */
    private function ollamaSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'risk_codes' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'string', 'enum' => array_keys(self::OLLAMA_RISKS)]],
                'action_codes' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'string', 'enum' => array_keys(self::OLLAMA_ACTIONS)]],
            ],
            'required' => ['risk_codes', 'action_codes'],
            'additionalProperties' => false,
        ];
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout((int) config('forecast.explanation.timeout', 30));
    }

    private function instructions(): string
    {
        return implode(' ', [
            'Anda menjelaskan hasil forecast restock Pandansari dalam bahasa Indonesia.',
            'Angka dari sistem bersifat final: jangan menghitung ulang, mengubah, atau mengarang nilai.',
            'Jelaskan ringkas, sebutkan risiko kualitas data atau WAPE yang tinggi, dan berikan tindakan review manusia.',
            'Jangan menyatakan bahwa stok atau transfer telah berubah karena rekomendasi tidak mengeksekusi transaksi.',
            'Kembalikan hanya JSON yang sesuai dengan schema.',
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(ForecastRun $run): array
    {
        $run->loadMissing(['operationalUnit', 'results.item']);

        return [
            'unit' => $run->operationalUnit->name,
            'period' => [
                'start' => $run->forecast_start->toDateString(),
                'end' => $run->forecast_end->toDateString(),
                'history_through' => $run->history_through->toDateString(),
            ],
            'metrics' => $run->metrics,
            'data_freshness' => $this->freshness->forRun($run),
            'totals' => [
                'predicted_units' => $run->results->sum('predicted_units'),
                'recommended_restock' => $run->results->sum('recommended_restock'),
                'products_requiring_restock' => $run->results->where('recommended_restock', '>', 0)->count(),
            ],
            'highest_restock_items' => $run->results
                ->sortByDesc('recommended_restock')
                ->take(10)
                ->map(fn ($result): array => [
                    'item' => $result->item->name,
                    'predicted_units' => $result->predicted_units,
                    'available_quantity' => $result->available_quantity,
                    'minimum_stock' => $result->minimum_stock,
                    'safety_stock_mode' => $result->safety_stock_mode->value,
                    'target_service_level' => (float) $result->target_service_level,
                    'calculated_safety_stock' => $result->calculated_safety_stock,
                    'safety_stock' => $result->safety_stock,
                    'supplier_lead_time_days' => $result->supplier_lead_time_days,
                    'order_pack_size' => $result->order_pack_size,
                    'unrounded_restock' => $result->unrounded_restock,
                    'recommended_restock' => $result->recommended_restock,
                    'order_by_date' => $result->order_by_date?->toDateString(),
                    'model' => $result->model,
                    'wape' => (float) $result->wape,
                ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'summary' => ['type' => 'string'],
                'risks' => ['type' => 'array', 'items' => ['type' => 'string']],
                'actions' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['summary', 'risks', 'actions'],
            'additionalProperties' => false,
        ];
    }

    /** @return array{summary: string, risks: array<int, string>, actions: array<int, string>} */
    private function fallback(ForecastRun $run): array
    {
        $run->loadMissing(['operationalUnit', 'results.item']);
        $restockItems = $run->results->where('recommended_restock', '>', 0);
        $highest = $restockItems->sortByDesc('recommended_restock')->first();
        $earliestOrder = $restockItems->whereNotNull('order_by_date')->sortBy('order_by_date')->first();
        $freshness = $this->freshness->forRun($run);
        $risks = [
            sprintf('WAPE agregat backtest adalah %.2f%%; rekomendasi tetap perlu ditinjau sebelum pengadaan.', (float) ($run->metrics['wape'] ?? 0)),
            'Data historis mingguan tidak dapat menjelaskan permintaan harian atau penjualan yang hilang saat stok habis.',
        ];

        if ($freshness['requires_warning']) {
            array_unshift($risks, sprintf(
                'Data terakhir berumur %d hari saat prediksi dibuat dan terdapat %d minggu tanpa observasi sebelum periode target.',
                $freshness['age_days'],
                $freshness['gap_weeks'],
            ));
        }
        $actions = [
            'Periksa stok fisik dan transfer yang sedang berjalan sebelum menyetujui jumlah pengadaan.',
            'Tinjau produk dengan WAPE tertinggi dan sesuaikan safety stock bila diperlukan.',
        ];

        if ($highest) {
            $actions[] = sprintf(
                'Prioritaskan review %s dengan rekomendasi restock %d unit.',
                $highest->item->name,
                $highest->recommended_restock,
            );
        }

        if ($earliestOrder) {
            $actions[] = sprintf(
                'Pesan %s paling lambat %s dalam kelipatan %d unit per kemasan.',
                $earliestOrder->item->name,
                $earliestOrder->order_by_date->translatedFormat('d F Y'),
                $earliestOrder->order_pack_size,
            );
        }

        return [
            'summary' => sprintf(
                '%s diperkirakan membutuhkan %d unit untuk periode %s sampai %s; rekomendasi restock total %d unit untuk %d produk.',
                $run->operationalUnit->name,
                $run->results->sum('predicted_units'),
                $run->forecast_start->translatedFormat('d F Y'),
                $run->forecast_end->translatedFormat('d F Y'),
                $run->results->sum('recommended_restock'),
                $restockItems->count(),
            ),
            'risks' => $risks,
            'actions' => $actions,
        ];
    }

    /** @param array<string, mixed> $value
     * @return array{summary: string, risks: array<int, string>, actions: array<int, string>}
     */
    private function validate(array $value): array
    {
        if (! is_string($value['summary'] ?? null)
            || ! is_array($value['risks'] ?? null)
            || ! is_array($value['actions'] ?? null)
            || collect($value['risks'])->contains(fn (mixed $item): bool => ! is_string($item))
            || collect($value['actions'])->contains(fn (mixed $item): bool => ! is_string($item))) {
            throw new RuntimeException('Forecast explanation does not match the required structure.');
        }

        return [
            'summary' => $value['summary'],
            'risks' => array_values($value['risks']),
            'actions' => array_values($value['actions']),
        ];
    }
}
