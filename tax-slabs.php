<?php
if (!defined('ABSPATH')) exit;

/**
 * Default slabs — used ONLY for calculation fallback
 * NEVER saved into DB automatically
 */
function pstc_default_tax_slabs() {
    return [
        '2024-2025' => [
            ['min'=>0,'max'=>600000,'fixed'=>0,'rate'=>0],
            ['min'=>600001,'max'=>1200000,'fixed'=>0,'rate'=>0.05],
            ['min'=>1200001,'max'=>2400000,'fixed'=>30000,'rate'=>0.125],
            ['min'=>2400001,'max'=>3600000,'fixed'=>180000,'rate'=>0.20],
            ['min'=>3600001,'max'=>6000000,'fixed'=>420000,'rate'=>0.25],
            ['min'=>6000001,'max'=>INF,'fixed'=>1020000,'rate'=>0.35],
        ],
    ];
}

/**
 * Get slabs for calculator
 */
function pstc_get_tax_slabs($year) {

    $saved = get_option('pstc_tax_slabs');

    // ✅ FIRST priority — slabs saved from admin
    if (is_array($saved) && isset($saved[$year]) && !empty($saved[$year])) {
        $slabs = $saved[$year];
    } 
    else {
        // ✅ fallback only if NOTHING saved
        $defaults = pstc_default_tax_slabs();
        $slabs = $defaults[$year] ?? [];
    }

    usort($slabs, function($a, $b){
        return $a['min'] <=> $b['min'];
    });

    return $slabs;
}

