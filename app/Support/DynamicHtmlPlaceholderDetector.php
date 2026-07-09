<?php

namespace App\Support;

class DynamicHtmlPlaceholderDetector
{
    public static function suggestsPlaywright(string $html): bool
    {
        if (stripos($html, 'jobboard-datatable') !== false
            && stripos($html, 'Bitte warten') !== false) {
            return true;
        }

        if (preg_match('/data-widget=["\']jobboardDatatable["\']/i', $html) === 1
            && preg_match('/<table\b[^>]*>.*?<tbody\b[^>]*>.*?<tr\b/is', $html) !== 1) {
            return true;
        }

        if (preg_match('/data-gjb-hidden-until-loaded/i', $html) === 1
            && preg_match('/class=["\'][^"\']*js-gjb-hits/i', $html) === 1
            && preg_match('/<table\b[^>]*>.*?<tbody\b[^>]*>.*?<tr\b/is', $html) !== 1) {
            return true;
        }

        return false;
    }
}
