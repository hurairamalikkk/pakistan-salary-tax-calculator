<?php

/**
 * Plugin Name: Pakistan Salary Tax Calculator
 * Description: Calculate Pakistan salaried income tax using progressive FBR tax slabs with multi-year support.
 * Version:     7.0.0
 * Author:      Huraira Malik
 */

if (!defined('ABSPATH')) exit;

define('PSTC_PATH', plugin_dir_path(__FILE__));
define('PSTC_URL', plugin_dir_url(__FILE__));

require_once PSTC_PATH.'includes/tax-slabs.php';
require_once PSTC_PATH.'includes/calculator-functions.php';

/* Assets */
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('pstc-style', PSTC_URL.'assets/css/style.css');
    wp_enqueue_script('pstc-script', PSTC_URL.'assets/js/script.js', [], null, true);
    wp_localize_script('pstc-script', 'pstc_ajax', [
        'ajax_url'=>admin_url('admin-ajax.php'),
        'nonce'=>wp_create_nonce('pstc_nonce')
    ]);
});

/* Shortcode */
add_shortcode('pakistan_salary_tax_calculator', function () {

    if (!get_option('pstc_enabled',1)) return '';

    $default_year = get_option('pstc_default_year','2024-2025');
    $slabs = get_option('pstc_tax_slabs', []);

    ob_start(); ?>
    <div class="pstc-wrapper">
        <input type="text" id="pstc-salary" placeholder="Enter Salary">

        <select id="pstc-type">
            <option value="monthly">Monthly</option>
            <option value="annual">Annual</option>
        </select>

        <select id="pstc-year">
            <?php foreach(array_keys($slabs) as $y): ?>
                <option value="<?php echo esc_attr($y);?>" <?php selected($default_year,$y);?>>
                    <?php echo esc_html($y);?>
                </option>
            <?php endforeach; ?>
        </select>

        <button id="pstc-calc">Calculate</button>
        <div id="pstc-result"></div>

        <p class="pstc-disclaimer"><?php echo esc_html(get_option('pstc_disclaimer'));?></p>
    </div>
    <?php return ob_get_clean();
});

/* Admin Menu */
add_action('admin_menu', function(){
    add_options_page(
        'Salary Tax Calculator',
        'Salary Tax Calculator',
        'manage_options',
        'pstc-settings',
        'pstc_settings_page'
    );
});

/* Settings Page */
function pstc_settings_page(){

    /* Get slabs + years */
    $all_slabs = get_option('pstc_tax_slabs', []);
    $default_year = get_option('pstc_default_year','2024-2025');

    // Ensure default year exists
    if (!isset($all_slabs[$default_year])) {
        $all_slabs[$default_year] = [];
        update_option('pstc_tax_slabs', $all_slabs);
    }

    $years = array_keys($all_slabs);
    sort($years);

    $slab_year = isset($_GET['slab_year'])
        ? sanitize_text_field($_GET['slab_year'])
        : $default_year;

    /* ADD NEW YEAR */
    if (isset($_POST['add_year'])) {
        $new_year = sanitize_text_field($_POST['new_year']);

        if ($new_year && !isset($all_slabs[$new_year])) {
            $all_slabs[$new_year] = [];
            update_option('pstc_tax_slabs', $all_slabs);
        }

        wp_redirect(
            admin_url('options-general.php?page=pstc-settings&slab_year='.$new_year)
        );
        exit;
    }

    /* SAVE SETTINGS + SLABS */
    if (isset($_POST['save_settings'])) {

        $slab_year = sanitize_text_field($_POST['slab_year']);

        update_option('pstc_enabled', isset($_POST['enabled']));
        update_option('pstc_default_year', sanitize_text_field($_POST['default_year']));
        update_option('pstc_disclaimer', sanitize_textarea_field($_POST['disclaimer']));

        if (isset($_POST['slabs'])) {
            $clean = [];

            foreach ($_POST['slabs'] as $slab) {
                $clean[] = [
                    'min'   => intval($slab['min']),
                    'max'   => ($slab['max']==='' ? INF : intval($slab['max'])),
                    'fixed' => intval($slab['fixed']),
                    'rate'  => floatval($slab['rate']) / 100,
                ];
            }

            $all_slabs[$slab_year] = $clean;
            update_option('pstc_tax_slabs', $all_slabs);
        }

        echo '<div class="updated"><p>Settings Saved</p></div>';
    }

    $slabs = $all_slabs[$slab_year] ?? [];
?>

<div class="wrap">
<h1>Salary Tax Calculator Settings</h1>

<!-- Select Existing Year -->
<form method="get" style="margin-bottom:15px;">
    <input type="hidden" name="page" value="pstc-settings">

    <strong>Select Tax Year:</strong>
    <select name="slab_year">
        <?php foreach ($years as $y): ?>
            <option value="<?php echo esc_attr($y); ?>" <?php selected($slab_year, $y); ?>>
                <?php echo esc_html($y); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button class="button">Load</button>
</form>

<!-- Add New Year -->
<form method="post" style="margin-bottom:20px;">
    <input type="hidden" name="add_year" value="1">

    <strong>Add New Tax Year:</strong>
    <input type="text" name="new_year" placeholder="e.g. 2026-2027" required>
    <button class="button">Add Year</button>
</form>

<form method="post">
<input type="hidden" name="slab_year" value="<?php echo esc_attr($slab_year); ?>">

<h2>General Settings</h2>

<label>
<input type="checkbox" name="enabled" <?php checked(get_option('pstc_enabled'),1);?>>
Enable Calculator
</label><br><br>

<label>Default Tax Year</label>
<input type="text" name="default_year" value="<?php echo esc_attr($default_year);?>"><br><br>

<label>Disclaimer</label><br>
<textarea name="disclaimer" rows="4" cols="60"><?php echo esc_textarea(get_option('pstc_disclaimer'));?></textarea>

<hr>
<h2>Tax Slabs Manager (<?php echo esc_html($slab_year); ?>)</h2>

<table id="pstc-slabs-table" class="widefat">
<thead>
<tr>
<th>Min</th><th>Max</th><th>Fixed</th><th>Rate (%)</th><th>Delete</th>
</tr>
</thead>
<tbody>
<?php foreach ($slabs as $i => $s): ?>
<tr>
<td><input name="slabs[<?php echo $i;?>][min]" value="<?php echo esc_attr($s['min']);?>"></td>
<td><input name="slabs[<?php echo $i;?>][max]" value="<?php echo ($s['max']==INF?'':esc_attr($s['max']));?>"></td>
<td><input name="slabs[<?php echo $i;?>][fixed]" value="<?php echo esc_attr($s['fixed']);?>"></td>
<td><input name="slabs[<?php echo $i;?>][rate]" value="<?php echo esc_attr($s['rate']*100);?>"></td>
<td><button class="button remove-row">X</button></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<br>
<button id="add-slab" class="button">Add New Slab</button>

<br><br>
<button name="save_settings" class="button button-primary">Save Settings</button>
</form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    let slabIndex = document.querySelectorAll('#pstc-slabs-table tbody tr').length;

    document.addEventListener('click', function (e) {

        if (e.target.id === 'add-slab') {
            e.preventDefault();

            let row = `<tr>
                <td><input name="slabs[${slabIndex}][min]"></td>
                <td><input name="slabs[${slabIndex}][max]"></td>
                <td><input name="slabs[${slabIndex}][fixed]"></td>
                <td><input name="slabs[${slabIndex}][rate]"></td>
                <td><button class="button remove-row">X</button></td>
            </tr>`;

            document.querySelector('#pstc-slabs-table tbody')
                .insertAdjacentHTML('beforeend', row);

            slabIndex++;
        }

        if (e.target.classList.contains('remove-row')) {
            e.preventDefault();
            e.target.closest('tr').remove();
        }
    });
});
</script>

<?php }

/* AJAX */
add_action('wp_ajax_pstc_calculate_tax','pstc_calculate_tax_ajax');
add_action('wp_ajax_nopriv_pstc_calculate_tax','pstc_calculate_tax_ajax');

function pstc_calculate_tax_ajax(){
    check_ajax_referer('pstc_nonce','nonce');
    $salary = floatval(str_replace(',','',$_POST['salary']));
    $type   = sanitize_text_field($_POST['type']);
    $year   = sanitize_text_field($_POST['year']);
    wp_send_json_success(pstc_calculate_tax($salary,$type,$year));
}
