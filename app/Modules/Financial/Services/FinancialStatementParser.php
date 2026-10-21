<?php

namespace App\Modules\Financial\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinancialStatementParser
{
    private const MAX_ROWS = 5000;

    /** @return array{format: string, rows: array<int, array{posted_at: string, description: string, amount_cents: int, external_id: ?string}>} */
    public function parse(UploadedFile $file): array
    {
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false || trim($contents) === '') {
            throw ValidationException::withMessages(['statement' => 'O arquivo de extrato está vazio ou não pôde ser lido.']);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $parsed = $extension === 'ofx'
            ? ['format' => 'ofx', 'rows' => $this->parseOfx($contents)]
            : ['format' => 'csv', 'rows' => $this->parseCsv($contents)];

        if ($parsed['rows'] === []) {
            throw ValidationException::withMessages(['statement' => 'Nenhum lançamento válido foi encontrado no extrato.']);
        }

        if (count($parsed['rows']) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['statement' => 'O extrato ultrapassa o limite de 5.000 lançamentos por importação.']);
        }

        return $parsed;
    }

    /** @return array<int, array{posted_at: string, description: string, amount_cents: int, external_id: ?string}> */
    private function parseCsv(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $lines = preg_split('/\R/u', $contents) ?: [];
        $firstLine = (string) collect($lines)->first(fn (string $line): bool => trim($line) !== '');
        $delimiter = $this->detectDelimiter($firstLine);
        $handle = fopen('php://temp', 'w+b');
        fwrite($handle, $contents);
        rewind($handle);

        $header = null;
        $rows = [];
        while (($columns = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            if ($this->blankRow($columns)) {
                continue;
            }

            if ($header === null) {
                $header = array_map(fn ($value): string => $this->normalizeHeader((string) $value), $columns);
                $this->assertCsvHeader($header);

                continue;
            }

            $record = [];
            foreach ($header as $index => $key) {
                $record[$key] = $columns[$index] ?? null;
            }

            $rows[] = $this->normalizeRow(
                (string) ($record['date'] ?? ''),
                (string) ($record['description'] ?? ''),
                (string) ($record['amount'] ?? ''),
                filled($record['external_id'] ?? null) ? (string) $record['external_id'] : null,
            );
        }
        fclose($handle);

        return $rows;
    }

    /** @return array<int, array{posted_at: string, description: string, amount_cents: int, external_id: ?string}> */
    private function parseOfx(string $contents): array
    {
        preg_match_all('/<STMTTRN>(.*?)(?=<\/STMTTRN>|<STMTTRN>|<\/BANKTRANLIST>)/is', $contents, $matches);
        $rows = [];

        foreach ($matches[1] ?? [] as $block) {
            $date = $this->ofxValue($block, 'DTPOSTED');
            $amount = $this->ofxValue($block, 'TRNAMT');
            $description = $this->ofxValue($block, 'MEMO')
                ?: $this->ofxValue($block, 'NAME')
                ?: 'Lançamento bancário';

            $rows[] = $this->normalizeRow(
                substr((string) $date, 0, 8),
                $description,
                (string) $amount,
                $this->ofxValue($block, 'FITID'),
            );
        }

        return $rows;
    }

    /** @return array{posted_at: string, description: string, amount_cents: int, external_id: ?string} */
    private function normalizeRow(string $date, string $description, string $amount, ?string $externalId): array
    {
        $parsedDate = $this->parseDate(trim($date));
        $amountCents = $this->parseAmountCents($amount);
        $description = trim(preg_replace('/\s+/u', ' ', $description) ?? $description);

        if ($description === '' || $amountCents === 0) {
            throw ValidationException::withMessages(['statement' => 'Cada linha deve ter data, descrição e valor diferente de zero.']);
        }

        return [
            'posted_at' => $parsedDate->format('Y-m-d'),
            'description' => mb_substr($description, 0, 255),
            'amount_cents' => $amountCents,
            'external_id' => filled($externalId) ? mb_substr(trim((string) $externalId), 0, 120) : null,
        ];
    }

    private function parseDate(string $value): CarbonImmutable
    {
        foreach (['Y-m-d', 'd/m/Y', 'Ymd'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value);
                if ($date !== false && $date->format($format) === $value) {
                    return $date;
                }
            } catch (\Throwable) {
            }
        }

        throw ValidationException::withMessages(['statement' => "Data inválida no extrato: {$value}."]);
    }

    private function parseAmountCents(string $value): int
    {
        $value = trim(str_replace(["\u{00A0}", 'R$', ' '], '', $value));
        $negative = str_starts_with($value, '(') && str_ends_with($value, ')');
        $value = trim($value, '()');
        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $decimal = $lastComma > $lastDot ? ',' : '.';
            $thousand = $decimal === ',' ? '.' : ',';
            $value = str_replace($thousand, '', $value);
            $value = str_replace($decimal, '.', $value);
        } elseif ($lastComma !== false) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        if (! is_numeric($value)) {
            throw ValidationException::withMessages(['statement' => "Valor inválido no extrato: {$value}."]);
        }

        $cents = (int) round(((float) $value) * 100);

        return $negative ? -abs($cents) : $cents;
    }

    /** @param array<int, string> $header */
    private function assertCsvHeader(array $header): void
    {
        foreach (['date', 'description', 'amount'] as $required) {
            if (! in_array($required, $header, true)) {
                throw ValidationException::withMessages([
                    'statement' => 'O CSV deve conter as colunas data, descrição e valor. A coluna id é opcional.',
                ]);
            }
        }
    }

    private function normalizeHeader(string $value): string
    {
        $value = strtolower(trim(Str::ascii($value)));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;

        return match (trim($value, '_')) {
            'data', 'date', 'data_lancamento', 'data_do_lancamento' => 'date',
            'descricao', 'description', 'historico', 'memo' => 'description',
            'valor', 'amount', 'quantia' => 'amount',
            'id', 'fitid', 'documento', 'transaction_id', 'identificador' => 'external_id',
            default => trim($value, '_'),
        };
    }

    private function detectDelimiter(string $line): string
    {
        $counts = [';' => substr_count($line, ';'), ',' => substr_count($line, ','), "\t" => substr_count($line, "\t")];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    /** @param array<int, string|null> $columns */
    private function blankRow(array $columns): bool
    {
        return collect($columns)->every(fn ($value): bool => trim((string) $value) === '');
    }

    private function ofxValue(string $block, string $tag): ?string
    {
        return preg_match('/<'.preg_quote($tag, '/').'>\s*([^<\r\n]+)/i', $block, $match)
            ? trim(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5))
            : null;
    }
}
