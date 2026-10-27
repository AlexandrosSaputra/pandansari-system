<?php

namespace App\Services;

use App\Models\ForecastRun;
use App\Support\ForecastDataFreshness;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ForecastExplanationService
{
    public function __construct(private ForecastDataFreshness $freshness) {}

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
            'format' => $this->schema(),
            'options' => ['temperature' => 0],
            'messages' => [
                ['role' => 'system', 'content' => $this->instructions()],
                [
                    'role' => 'user',
                    'content' => json_encode($this->payload($run), JSON_THROW_ON_ERROR),
                ],
            ],
        ])->throw()->json();
        $content = $response['message']['content'] ?? null;

        if (! is_string($content)) {
            throw new RuntimeException('Ollama response did not contain message content.');
        }

        return json_decode($content, true, flags: JSON_THROW_ON_ERROR);
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
                    'recommended_restock' => $result->recommended_restock,
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

        return [
            'summary' => sprintf(
                '%s memerlukan estimasi total %d unit untuk periode %s sampai %s; %d produk memiliki rekomendasi restock.',
                $run->operationalUnit->name,
                $run->results->sum('predicted_units'),
                $run->forecast_start->translatedFormat('d F Y'),
                $run->forecast_end->translatedFormat('d F Y'),
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
