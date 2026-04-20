<?php

add_action('wp_enqueue_scripts', function () {
    wp_dequeue_style('woocommerce-general');
    wp_dequeue_style('woocommerce-layout');
    wp_dequeue_style('woocommerce-smallscreen');
}, 100);