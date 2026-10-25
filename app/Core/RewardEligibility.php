<?php
namespace Core;

class RewardEligibility
{
    public static function isCoffeeCategory($name): bool
    {
        if (!is_string($name)) return false;
        $category = preg_replace('/[\s_-]+/', ' ', strtolower(trim($name)));
        return in_array($category, [
            'coffee', 'coffee based', 'specialty coffee', 'hot coffee',
            'iced coffee', 'cold coffee', 'espresso',
        ], true);
    }
}
