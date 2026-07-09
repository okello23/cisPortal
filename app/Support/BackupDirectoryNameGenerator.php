<?php

namespace App\Support;

class BackupDirectoryNameGenerator
{
    public function generate(string $facilityName): string
    {
        $value = mb_strtolower(trim($facilityName));

        $replacements = [
            '/\bhealth\s*centre\s*iv\b/u' => 'hciv',
            '/\bh\s*\/\s*c\s*iv\b/u' => 'hciv',
            '/\bhc\s*iv\b/u' => 'hciv',
            '/\bhealth\s*center\s*iv\b/u' => 'hciv',
            '/\bhealth\s*centre\s*iii\b/u' => 'hciii',
            '/\bh\s*\/\s*c\s*iii\b/u' => 'hciii',
            '/\bhc\s*iii\b/u' => 'hciii',
            '/\bhealth\s*center\s*iii\b/u' => 'hciii',
            '/\bhealth\s*centre\s*ii\b/u' => 'hcii',
            '/\bh\s*\/\s*c\s*ii\b/u' => 'hcii',
            '/\bhc\s*ii\b/u' => 'hcii',
            '/\bhealth\s*center\s*ii\b/u' => 'hcii',
        ];

        foreach ($replacements as $pattern => $replacement) {
            $value = preg_replace($pattern, $replacement, $value) ?? $value;
        }

        $value = str_replace(["'", '`', "\u{2019}"], '', $value);
        $value = preg_replace('/[^a-z0-9]+/u', '_', $value) ?? $value;
        $value = preg_replace('/_+/u', '_', $value) ?? $value;

        return trim($value, '_');
    }
}
