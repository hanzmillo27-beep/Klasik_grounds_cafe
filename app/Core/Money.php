<?php
namespace Core;

class Money
{
    public static function cents($amount): int
    {
        if (!is_numeric($amount) || !is_finite((float) $amount) || $amount < 0 || $amount > 99999999.99) {
            throw new \InvalidArgumentException('Invalid money amount.');
        }
        return (int) round((float) $amount * 100);
    }
}
