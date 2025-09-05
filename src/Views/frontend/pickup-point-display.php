<?php
/**
 * Pickup Point Display Template
 *
 * This template is rendered as a hidden template in the page
 * and cloned/populated by JavaScript for consistent pickup point display.
 *
 * @package Wuunder\Shipping
 * @var array $data Template data (not used directly, placeholders instead)
 */

defined( 'ABSPATH' ) || exit;
?>
<template id="wuunder-pickup-template" style="display:none">
    <div class="wuunder-pickup-display">
        <div class="pickup-name">{{name}}</div>
        <div class="pickup-address">
            <span class="pickup-street">{{street}}</span><br>
            <span class="pickup-location">{{postcode}} {{city}}</span>
        </div>
        <?php if ( ! empty( $show_carrier ) ): ?>
        <div class="pickup-carrier">{{carrier}}</div>
        <?php endif; ?>
    </div>
</template>