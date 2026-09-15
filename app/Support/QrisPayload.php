<?php

namespace App\Support;

use InvalidArgumentException;

class QrisPayload
{
    public function withAmount(string $staticPayload, int $amount): string
    {
        $staticPayload = trim($staticPayload);

        if ($amount < 1 || $amount > 10_000_000) {
            throw new InvalidArgumentException('Nominal QRIS harus antara Rp1 dan Rp10.000.000.');
        }

        $fields = $this->parse($staticPayload);
        $crcField = array_pop($fields);

        if ($crcField === null || $crcField['id'] !== '63' || $crcField['length'] !== 4) {
            throw new InvalidArgumentException('Payload QRIS tidak memiliki CRC yang valid.');
        }

        $payloadWithoutCrcValue = substr($staticPayload, 0, -4);
        if ($this->crc16($payloadWithoutCrcValue) !== strtoupper($crcField['value'])) {
            throw new InvalidArgumentException('Checksum payload QRIS tidak valid.');
        }

        $dynamicFields = [];
        $amountInserted = false;

        foreach ($fields as $field) {
            if ($field['id'] === '01') {
                $field['value'] = '12';
                $field['length'] = 2;
            }

            if ($field['id'] === '54') {
                continue;
            }

            $dynamicFields[] = $field;

            if ($field['id'] === '53') {
                $amountValue = (string) $amount;
                $dynamicFields[] = [
                    'id' => '54',
                    'length' => strlen($amountValue),
                    'value' => $amountValue,
                ];
                $amountInserted = true;
            }
        }

        if (! $amountInserted) {
            throw new InvalidArgumentException('Payload QRIS tidak memiliki kode mata uang.');
        }

        $dynamicPayload = collect($dynamicFields)
            ->map(fn (array $field): string => $field['id'].str_pad((string) $field['length'], 2, '0', STR_PAD_LEFT).$field['value'])
            ->implode('');
        $dynamicPayload .= '6304';

        return $dynamicPayload.$this->crc16($dynamicPayload);
    }

    /**
     * @return array<int, array{id: string, length: int, value: string}>
     */
    private function parse(string $payload): array
    {
        $fields = [];
        $offset = 0;
        $payloadLength = strlen($payload);

        while ($offset < $payloadLength) {
            if ($offset + 4 > $payloadLength) {
                throw new InvalidArgumentException('Struktur payload QRIS tidak lengkap.');
            }

            $id = substr($payload, $offset, 2);
            $lengthValue = substr($payload, $offset + 2, 2);

            if (! ctype_digit($id) || ! ctype_digit($lengthValue)) {
                throw new InvalidArgumentException('Struktur payload QRIS tidak valid.');
            }

            $length = (int) $lengthValue;
            $value = substr($payload, $offset + 4, $length);

            if (strlen($value) !== $length) {
                throw new InvalidArgumentException('Panjang field payload QRIS tidak sesuai.');
            }

            $fields[] = compact('id', 'length', 'value');
            $offset += 4 + $length;
        }

        return $fields;
    }

    private function crc16(string $payload): string
    {
        $crc = 0xFFFF;

        foreach (unpack('C*', $payload) as $byte) {
            $crc ^= $byte << 8;

            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) !== 0
                    ? (($crc << 1) ^ 0x1021) & 0xFFFF
                    : ($crc << 1) & 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
