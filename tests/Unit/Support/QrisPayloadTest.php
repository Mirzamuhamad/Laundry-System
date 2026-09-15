<?php

namespace Tests\Unit\Support;

use App\Support\QrisPayload;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QrisPayloadTest extends TestCase
{
    private const STATIC_PAYLOAD = '00020101021126610014COM.GO-JEK.WWW01189360091439911088270210G9911088270303UMI51440014ID.CO.QRIS.WWW0215ID10264838858300303UMI5204721053033605802ID5925LaundryKlinTebet15, TEBET6015JAKARTA SELATAN61051281062070703A016304C15E';

    public function test_generates_a_valid_dynamic_payload_with_the_transaction_amount(): void
    {
        $payload = (new QrisPayload)->withAmount(self::STATIC_PAYLOAD, 90000);

        $this->assertSame('00020101021226610014COM.GO-JEK.WWW01189360091439911088270210G9911088270303UMI51440014ID.CO.QRIS.WWW0215ID10264838858300303UMI5204721053033605405900005802ID5925LaundryKlinTebet15, TEBET6015JAKARTA SELATAN61051281062070703A0163040C68', $payload);
    }

    public function test_rejects_a_payload_with_an_invalid_checksum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Checksum payload QRIS tidak valid.');

        (new QrisPayload)->withAmount(substr(self::STATIC_PAYLOAD, 0, -4).'FFFF', 90000);
    }

    #[DataProvider('invalidAmounts')]
    public function test_rejects_an_amount_outside_the_qris_transaction_limit(int $amount): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nominal QRIS harus antara Rp1 dan Rp10.000.000.');

        (new QrisPayload)->withAmount(self::STATIC_PAYLOAD, $amount);
    }

    public static function invalidAmounts(): array
    {
        return [
            'zero' => [0],
            'above QRIS limit' => [10_000_001],
        ];
    }
}
