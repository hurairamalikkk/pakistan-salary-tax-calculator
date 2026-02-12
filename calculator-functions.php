<?php
if (!defined('ABSPATH')) exit;

function pstc_calculate_tax($salary, $type, $year) {

    $annual_salary = ($type === 'monthly') ? $salary * 12 : $salary;
    $slabs = pstc_get_tax_slabs($year);

    $tax = 0;

    if (empty($slabs)) {
        return [
            'annual_salary' => number_format($annual_salary),
            'annual_tax' => '0',
            'monthly_tax' => '0',
            'effective_rate' => '0'
        ];
    }

    foreach ($slabs as $slab) {

        $min = intval($slab['min']);
        $max = ($slab['max'] === INF || $slab['max'] === '' ) ? INF : intval($slab['max']);

        if ($annual_salary >= $min && $annual_salary <= $max) {
            $tax = $slab['fixed'] + (($annual_salary - $min) * $slab['rate']);
            break;
        }
    }

    $monthly_tax = $tax / 12;
    $effective_rate = ($annual_salary > 0) ? ($tax / $annual_salary) * 100 : 0;

    return [
        'annual_salary'   => number_format($annual_salary),
        'annual_tax'      => number_format($tax),
        'monthly_tax'     => number_format($monthly_tax),
        'effective_rate'  => number_format($effective_rate, 2)
    ];
}
