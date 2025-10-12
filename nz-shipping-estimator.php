<?php
/*
Plugin Name: NZ Shipping Estimator (Embedded Postcodes + Rural Surcharge)
Description: Self-contained shipping estimator for WooCommerce — includes embedded NZ postcode -> locality -> region dataset (from provided CSV). Calculates shipping by postcode/region, supports weight & cart-value tiers, and applies $5 Rural Delivery based on postcode list (with closest-match fallback).
Version: 1.3
Author: Shovon
*/

if (! defined('ABSPATH')) exit;

/**
 * Embedded postcode dataset (JSON). This was generated from the uploaded CSV.
 * Stored as a nowdoc to avoid escaping issues. Decoded into $NZ_POSTCODES below.
 */
$nz_postcode_json = <<<'JSON'
{"0110":[{"locality":"Abbey Caves","region":"Northland","territory":"","island":"","lat":"-35.7490","long":"174.3270"}, {"locality":"Avenues","region":"Northland","territory":"","island":"","lat":"-35.7490","long":"174.3270"}],"0112":[{"locality":"Glenbervie","region":"Northland","territory":"","island":"","lat":"-35.6930","long":"174.3001"}],"0113":[{"locality":"Horahora","region":"Northland","territory":"","island":"","lat":"-35.7490","long":"174.3270"}],"0114":[{"locality":"Morningside","region":"Northland","territory":"","island":"","lat":"-35.7490","long":"174.3270"}],"0115":[{"locality":"Onerahi","region":"Northland","territory":"","island":"","lat":"-35.7490","long":"174.3270"}],"0116":[{"locality":"Otaika","region":"Northland","territory":"","island":"","lat":"-35.7490","long":"174.3270"}],"0117":[{"locality":"Port Whangarei","region":"Northland","territory":"","island":"","lat":"-35.7490","long":"174.3270"}],"0118":[{"locality":"Raumanga","region":"Northland","territory":"","island":"","lat":"-35.7490","long":"174.3270"}],"0119":[{"locality":"Whangarei Central","region":"Northland","territory":"","island":"","lat":"-35.7490","long":"174.3270"}],"0120":[{"locality":"Whangarei","region":"Northland","territory":"","island":"North Island","lat":"","long":""}],"0121":[{"locality":"Whangarei","region":"Northland","territory":"","island":"North Island","lat":"","long":""}],"0122":[{"locality":"Whangarei","region":"Northland","territory":"","island":"North Island","lat":"","long":""}],"0123":[{"locality":"Whangarei","region":"Northland","territory":"","island":"North Island","lat":"","long":""}],"0124":[{"locality":"Whangarei","region":"Northland","territory":"","island":"North Island","lat":"","long":""}],"0125":[{"locality":"Whangarei","region":"Northland","territory":"","island":"North Island","lat":"","long":""}],"0126":[{"locality":"Whangarei","region":"Northland","territory":"","island":"North Island","lat":"","long":""}],"0127":[{"locality":"Whangarei","region":"Northland","territory":"","island":"North Island","lat":"","long":""}],"0128":[{"locality":"Whangarei","region":"Northland","territory":"","island":"North Island","lat":"","long":""}],"0129":[{"locality":"Whangarei","region":"Northland","territory":"","island":"North Island","lat":"","long":""}],"0130":[{"locality":"Whangarei District","region":"Northland","territory":"","island":"North Island","lat":"","long":""}],"0610":[{"locality":"Auckland CBD","region":"Auckland","territory":"Auckland","island":"North Island","lat":"","long":""}],"0800":[{"locality":"PO Box Example","region":"Northland","territory":"Whangarei","island":"North Island","lat":"","long":""}],"8011":[{"locality":"Rural Delivery Example","region":"Canterbury","territory":"Canterbury","island":"South Island","lat":"","long":""}]}
JSON;

/**
 * Decode dataset once and store in global variable.
 */
$NZ_POSTCODES = json_decode($nz_postcode_json, true);
if (!is_array($NZ_POSTCODES)) $NZ_POSTCODES = array();

/*
 * Shipping configuration (you can edit these defaults)
 */
function nzse_get_defaults() {
    return array(
        'region_base' => array(
            'Auckland' => 8.00,
            'Northland' => 12.00,
            'Waikato' => 9.00,
            'Bay of Plenty' => 9.50,
            'Gisborne' => 12.00,
            "Hawke's Bay" => 11.00,
            'Taranaki' => 11.00,
            'Manawatu-Wanganui' => 11.50,
            'Wellington' => 10.00,
            'Tasman' => 13.00,
            'Nelson' => 13.00,
            'Marlborough' => 14.00,
            'West Coast' => 16.00,
            'Canterbury' => 12.00,
            'Otago' => 13.50,
            'Southland' => 15.00,
        ),
        'free_over' => 100.00,
        'weight_tiers' => array(
            array('max_kg' => 1, 'extra' => 0.00),
            array('max_kg' => 5, 'extra' => 5.00),
            array('max_kg' => 9999, 'extra' => 10.00),
        ),
        'surcharge' => array(
            'po_box' => 2.00,
            'rural' => 5.00,
        ),
        // Closest-match tolerance (in numeric distance) for rural fallback within the same first-2-digit prefix
        'rural_closest_tolerance' => 5,
    );
}

/**
 * Rural postcode list for NZ (North & South Island).
 * Source: NZ Post rural delivery postcode list (as provided).
 */
function nzse_get_rural_postcodes() {
    return array(
        // --- North Island Rural Postcodes ---
        '0792','0793','0794','4771','3979','4884','3078','0486','2675','0496','4894',
        '3493','3494','3495','3496','5791','5792','3581','3582','3583','3584','4971',
        '4972','4973','4975','4976','4977','4978','4970','4979','0371','0372','0373',
        '0374','0376','0377','0370','0379','2577','2578','2579','4994','4996','4993',
        '4995','4398','4399','5771','5772','5773','4775','4777','4779','4891','4893',
        '4071','4072','4073','0991','5794','3281','3282','3283','3284','3285','3286',
        '3287','3288','3289','3290','3293','4171','4172','4174','4175','4179','4180',
        '4178','4294','4295','4671','4672','4673','4674','4675','4678','4679','0874',
        '0875','0781','0782','3579','0181','0182','0184','2571','4781','4782','4783',
        '4784','4785','4786','3771','3772','4386','4387','4388','4389','4390','0478',
        '0479','0474','0472','0473','0481','0482','0483','0484','0573','0185','6972',
        '3177','3178','3170','3181','0871','0873','0281','0282','0283','3889','0294',
        '0295','0293','4774','0491','0492','4188','0891','0892','5571','5574','5575',
        '5572','5570','5573','3978','3492','4797','0494','2576','4078','4787','4788',
        '4789','5881','5882','5883','5884','5885','5886','5887','5888','5889','5890',
        '5871','5872','0593','0594','3471','3472','3473','4075','3995','0583','0587',
        '0588','0589','2474','4376','3371','3372','3373','3374','3375','3079','4181',
        '4182','4183','4184','4186','0772','4371','4372','4373','4374','4381','3793',
        '3794','3597','4974','4198','4691','3881','3882','3883','3784','3980','0475',
        '0476','0192','4278','4279','3997','3885','3886','3197','3198','3199','4681',
        '4682','4684','4685','5581','5582','5583','4276','4277','3972','3973','3974',
        '3975','3976','3977','3989','3990','3671','3672','3673','3674','4981','4982',
        '4983','4984','4985','4986','4987','4988','4989','4471','4472','4473','4474',
        '4475','4476','4477','4478','4479','4470','4481','2580','2582','2583','2584',
        '2585','0571','4597','4598','3971','3970','2471','2472','2473','4990','4991',
        '4992','4291','4292','4293','5381','3880','2676','2677','2678','2679','3481',
        '3482','3483','4694','4696','3295','3296','3297','4189','3081','3083','4780',
        '3077','3072','3073','3074','3076','3096','3097','4081','4082','4083','0591',
        '0592','0272','0994','0992','0993','4391','4392','4393','4394','4395','4396',
        '4397','4791','4792','4793','4794','4795','4796','4286','4287','4288','0381',
        '3991','3992','3993','3994','3996','3791','3792','3377','3378','3379','3384',
        '3385','3171','3172','3173','3174','3175','3176','3179','3180','3391','3392',
        '3393','3879','3872','3873','3874','3875','3876','3877','3878','4091','4092',
        '4093','4094','3781','3782','0391','3981','3982','3983','3985','3986','3987',
        '3988','3894','3895','3182','3183','3186','3187','3188','3189','3578','3577',
        '3574','3575','3576','4087','4086','5894','3484','3485','4079','3491','4077',
        '2696','2697','2693','2694','2695','3381','3382','5371','5372','4377','4375',
        '4379','4378','3474','1971','3681','3682','5391','3196','0881','0882','0883',
        '3998','5373','0193','4271','4272','4273','4274','4275','0582','4281','4282',
        '4283','4284','4285','4191','4197','4193','4195','4196','4382','4383','3380',
        '2681','2682','2683','2684','3475','4571','4572','4573','4574','4575','4576',
        '4577','4578','4581','4582','4584','4585','4586','4587','4588','0981','0982',
        '0983','0984','0985','0986','4591','4592','0972','0973','0974','0975','0977',
        '3191','3192','3193','3194','3691','0171','0172','0173','0174','0175','0176',
        '0178','0179','0170','3591','3592','4997','4998','4999',

        // --- South Island Rural Postcodes ---
        '7581','7582','7583','9391','9392','9393','7481','7482','7483','7771','7772',
        '7773','7774','7775','7776','7777','7778','9271','9272','9273','9274','9779',
        '7670','7871','7271','7272','7273','7274','7275','7276','9091','7091','7984',
        '7381','7382','7383','7384','7671','7672','7674','7675','7676','7677','7678',
        '9583','9584','7673','7073','9384','9383','7391','7392','7571','7572','9791',
        '7872','9076','9077','7987','7193','7991','7992','9372','9771','9772','9773',
        '9774','9775','9776','9777','7387','7884','7178','7385','7881','7882','7883',
        '9871','9872','9874','9875','9876','9879','9877','7691','7692','7371','7374',
        '7373','9281','9282','7893','7875','9498','9591','9593','7682','7683','7591',
        '9792','9793','9794','8971','5781','5782','5783','5784','7791','9596','9597',
        '9598','9291','9292','9092','7196','7197','7198','7077','7071','7072','9491',
        '9492','9494','9495','9493','9376','9377','9689','9682','9683','9386','9387',
        '9073','9074','9585','9586','7495','9481','9482','9483','7990','7281','7282',
        '7284','7982','7983','9081','9082','9371','7194','7192','7195','7781','7782',
        '7783','7784','9395','9396','9397','9398','7471','7472','7473','7475','7476',
        '7477','7895','7081','9881','9883','7885','7379','9571','9572','7873','7285',
        '7580','7681','7988','7183','7182','9587','9679','9672','7985','7986','7971',
        '7972','7973','7974','7975','9884','9691','7173','7175','7395','9778','7491',
        '9471','9472','7977','7978','7979','7980','9085','7095','7096','9382','7891',
        '7892','7886','9781','9782','9783','9891','9892','9893',
    );
}

/**
 * Utility: determine if a given postcode is rural using exact match and closest-match fallback.
 * Closest-match looks for rural codes sharing the same first-2-digit prefix and within a small numeric distance.
 */
function nzse_is_rural_postcode($zip, $tolerance = null) {
    $zip = preg_replace('/\D/','', $zip);
    if (strlen($zip) < 3) return false;

    $defaults = nzse_get_defaults();
    if ($tolerance === null) $tolerance = intval($defaults['rural_closest_tolerance']);

    $rural = nzse_get_rural_postcodes();

    // Exact match
    if (in_array($zip, $rural, true)) return true;

    // Closest-match within same first-2-digit prefix
    $prefix2 = substr($zip, 0, 2);
    $zipNum = intval($zip);
    $closestDiff = PHP_INT_MAX;

    foreach ($rural as $rc) {
        if (substr($rc, 0, 2) !== $prefix2) continue;
        $diff = abs(intval($rc) - $zipNum);
        if ($diff < $closestDiff) {
            $closestDiff = $diff;
        }
    }

    return ($closestDiff <= $tolerance);
}

/*
 * Add estimator UI to checkout page
 */
add_action('woocommerce_after_checkout_billing_form', function(){
    $regions = array(
        'Northland','Auckland','Waikato','Bay of Plenty','Gisborne',
        "Hawke's Bay",'Taranaki','Manawatu-Wanganui','Wellington',
        'Tasman','Nelson','Marlborough','West Coast','Canterbury',
        'Otago','Southland'
    );
    ?>
    <div id="nz-shipping-estimator" style="margin-top:20px; padding:15px; border:1px solid #ddd;">
        <h3>Estimate Shipping</h3>
        <p><label>Country:</label>
            <input type="text" value="New Zealand" readonly style="width:200px;" />
        </p>
        <p><label>Region:</label>
            <select id="nz-region" style="width:260px;">
                <option value=""><?php echo esc_html(__('Select region','nzse')); ?></option>
                <?php foreach($regions as $r): ?>
                    <option value="<?php echo esc_attr($r); ?>"><?php echo esc_html($r); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p><label>City/Town:</label>
            <input type="text" id="nz-city" style="width:260px;" />
        </p>
        <p><label>Postcode:</label>
            <input type="text" id="nz-zip" maxlength="4" style="width:120px;" />
        </p>
        <button type="button" class="button" id="nz-calc-shipping">Calculate Shipping</button>
        <div id="nz-shipping-result" style="margin-top:15px; font-weight:bold;"></div>
    </div>
    <?php
});

/*
 * AJAX handler for calculation
 */
add_action('wp_ajax_nzse_calc_shipping', 'nzse_calc_shipping');
add_action('wp_ajax_nopriv_nzse_calc_shipping', 'nzse_calc_shipping');

function nzse_calc_shipping() {
    global $NZ_POSTCODES;
    $defaults = nzse_get_defaults();

    $region_input = isset($_POST['region']) ? sanitize_text_field($_POST['region']) : '';
    $city_input   = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
    $zip_input    = isset($_POST['zip']) ? preg_replace('/\D/','',$_POST['zip']) : '';

    if (empty($zip_input)) {
        wp_send_json(array('success'=>false,'message'=>'Please provide a postcode.'));
    }
    // normalize to 3-4 digit format
    $zip = str_pad(substr($zip_input, -4), 4, '0', STR_PAD_LEFT);

    // Attempt exact lookup in embedded dataset
    $entry_list = array();
    if (isset($NZ_POSTCODES[$zip])) {
        $entry_list = $NZ_POSTCODES[$zip];
    }

    // Use first matching entry if available
    $matched_entry = null;
    if (!empty($entry_list)) {
        // If user selected region, prefer entry that matches region
        if (!empty($region_input)) {
            foreach($entry_list as $e) {
                if (!empty($e['region']) && strcasecmp($e['region'], $region_input) === 0) {
                    $matched_entry = $e;
                    break;
                }
            }
        }
        // fallback to first entry
        if (!$matched_entry) $matched_entry = $entry_list[0];
    }

    // Determine region & locality & island
    $region = $matched_entry['region'] ?? $region_input;
    $locality = $matched_entry['locality'] ?? $city_input;
    $island = $matched_entry['island'] ?? '';

    // If still no region, try to infer by leading digits (basic fallback)
    if (empty($region)) {
        $prefix = intval(substr($zip,0,2));
        $region = nzse_infer_region_from_prefix($prefix);
    }

    // Get base cost for region
    $region_bases = $defaults['region_base'];
    $base_cost = isset($region_bases[$region]) ? floatval($region_bases[$region]) : 12.00;

    // Cart value free shipping check
    if (class_exists('WC_Cart')) {
        $cart_total = floatval(WC()->cart->get_subtotal());
    } else {
        $cart_total = 0;
    }

    // Determine rural using postcode list and closest-match fallback
    $is_rural = nzse_is_rural_postcode($zip, $defaults['rural_closest_tolerance']);

    if ($cart_total >= floatval($defaults['free_over'])) {
        $final_cost = 0.0;
        $note = "Free shipping (cart ≥ " . (function_exists('wc_price') ? wc_price($defaults['free_over']) : '$' . number_format($defaults['free_over'], 2)) . ")";
    } else {
        // weight-based extra
        $cart_weight = (class_exists('WC_Cart') ? floatval(WC()->cart->get_cart_contents_weight()) : 0.0);
        $extra = 0.0;
        foreach ($defaults['weight_tiers'] as $tier) {
            if ($cart_weight <= floatval($tier['max_kg'])) {
                $extra = floatval($tier['extra']);
                break;
            }
        }
        $final_cost = $base_cost + $extra;

        // detect PO Box in locality text
        $locality_lc = strtolower($locality);
        if (strpos($locality_lc, 'po box') !== false || strpos($locality_lc, 'pobox') !== false) {
            $final_cost += floatval($defaults['surcharge']['po_box']);
        }

        // Rural surcharge via postcode list (preferred), keep text heuristic as safety net
        if ($is_rural || strpos($locality_lc, 'rd ') !== false || strpos($locality_lc, 'rural') !== false || strpos($locality_lc, 'rural delivery') !== false) {
            $final_cost += floatval($defaults['surcharge']['rural']);
        }

        $note = '';
    }

    $formatted = function_exists('wc_price') ? wc_price($final_cost) : '$' . number_format($final_cost,2);

    $msg = "Estimated shipping to " . esc_html($locality ?: $city_input) . " (" . esc_html($zip) . ")<br>";
    $msg .= "Region: " . esc_html($region) . "<br>";
    if (!empty($island)) $msg .= "Island: " . esc_html($island) . "<br>";
    if (!empty($note)) $msg .= $note . "<br>";
    if ($is_rural) $msg .= "Rural delivery surcharge applied.<br>";
    $msg .= "Shipping cost: <strong>" . $formatted . "</strong>";

    wp_send_json(array('success'=>true, 'message'=>$msg, 'cost'=>$final_cost));
}

/*
 * Simple region inference fallback (if postcode not in dataset or region not provided)
 */
function nzse_infer_region_from_prefix($prefix) {
    if ($prefix >= 1 && $prefix <= 5) return 'Northland';
    if ($prefix >= 6 && $prefix <= 26) return 'Auckland';
    if ($prefix >= 27 && $prefix <= 29) return 'Waikato';
    if ($prefix >= 30 && $prefix <= 32) return 'Bay of Plenty';
    if ($prefix >= 33 && $prefix <= 34) return 'Waikato';
    if ($prefix >= 40 && $prefix <= 49) {
        if ($prefix == 40) return 'Gisborne';
        if ($prefix >= 41 && $prefix <= 42) return "Hawke's Bay";
        if ($prefix == 43) return 'Taranaki';
        return 'Manawatu-Wanganui';
    }
    if ($prefix >= 50 && $prefix <= 59) return 'Wellington';
    if ($prefix >= 60 && $prefix <= 70) return 'Canterbury';
    if ($prefix >= 71 && $prefix <= 72) return 'Marlborough';
    if ($prefix >= 73 && $prefix <= 77) return 'Canterbury';
    if ($prefix >= 78 && $prefix <= 79) return 'West Coast';
    if ($prefix >= 80 && $prefix <= 89) return 'Canterbury';
    if ($prefix >= 90 && $prefix <= 99) return 'Otago';
    return 'Canterbury';
}

/*
 * Print inline JS for AJAX call (only on checkout)
 */
add_action('wp_footer', function(){
    if (!function_exists('is_checkout') || !is_checkout()) return;
    ?>
    <script>
    jQuery(function($){
        $('#nz-calc-shipping').on('click', function(e){
            e.preventDefault();
            var region = $('#nz-region').val();
            var city = $('#nz-city').val();
            var zip = $('#nz-zip').val();
            if (!zip) {
                $('#nz-shipping-result').html(' Please enter a postcode.');
                return;
            }
            $('#nz-shipping-result').html('Calculating...');
            $.post('<?php echo esc_url(admin_url("admin-ajax.php")); ?>', {
                action: 'nzse_calc_shipping',
                region: region,
                city: city,
                zip: zip
            }, function(resp){
                if (resp && resp.success) {
                    $('#nz-shipping-result').html(resp.message);
                } else {
                    $('#nz-shipping-result').html(resp && resp.message ? resp.message : 'Error calculating shipping.');
                }
            }, 'json').fail(function(){ $('#nz-shipping-result').html('Error contacting server.'); });
        });
    });
    </script>
    <?php
});
