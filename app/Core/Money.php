<?php
namespace Core;

class Money
{
    public static function paymentCents($amount): int
    {
        if ((!is_string($amount) && !is_int($amount) && !is_float($amount))
            || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', (string) $amount)) {
            throw new \InvalidArgumentException('Enter the amount paid using at most two decimal places.');
        }
        return self::cents($amount);
    }

    public static function cents($amount): int
    {
        if (!is_numeric($amount) || !is_finite((float) $amount) || $amount < 0 || $amount > 99999999.99) {
            throw new \InvalidArgumentException('Invalid money amount.');
        }
        return (int) round((float) $amount * 100);
    }
}
