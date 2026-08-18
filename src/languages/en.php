<?php

global $lang;

$lang = [
    'lang' => 'en',
    'title' => 'Test Project',
    'all_games' => 'All Games',
    'prd_name' => 'Product Name',
    'stock' => 'Stock',
    'min_order' => 'Min. Order',
    'max_order' => 'Max. Order',
    'quantity' => 'Quantity',
    'price' => 'Unit Price',
    'line_total' => 'Total',
    'currency' => '₺',
    'buy' => 'Buy',
    'warning' => 'Warning',
    'error' => 'Error',
    'ok' => 'OK',
    
    // Game / product list
    'select_game' => 'Select Game',
    'pre_order' => 'Pre-Order',
    'unlimited' => 'Unlimited',
    'barem_amount' => 'Amount (₺)',
    'order_pending' => 'Your order was placed as a pre-order and is being processed.',
    'loading' => 'Loading...',
    'out_of_stock' => 'Out of Stock',
    'no_products_found' => 'No products found for this game.',
    'select_game_prompt' => 'Please select a game to view products.',
    'games_fetch_error' => 'Could not fetch game list: ',
    'products_fetch_error' => 'Could not fetch products: ',

    // 404
    'not_found_title' => 'Page Not Found',
    'not_found_text' => 'The page you are looking for does not exist or may have been moved.',
    'back_home' => 'Back to Home',

    // Order result (SweetAlert2 modal)
    'order_success' => 'Order Successful',
    'order_failed' => 'Order Failed',
    'order_no' => 'Order No',
    'total' => 'Total',
    'network_error' => 'Could not reach the server.',
    'unknown_error' => 'An error occurred.',
    'invalid_quantity' => 'Please enter a valid quantity.',
    'quantity_too_low' => 'Order quantity must be at least :min.',
    'quantity_too_high' => 'Order quantity must be at most :max.',

    // Server-side order validation/error messages (/order)
    'missing_fields' => 'Missing or invalid information.',
    'duplicate_order' => 'This order was already submitted or the session expired.',
    'product_not_found' => 'Product not found.',
    'min_order_error' => 'Order quantity must be at least :min.',
    'max_order_error' => 'Order quantity must be at most :max.',
    'out_of_stock_error' => 'Product is out of stock.',
    'barem_required_error' => 'Please enter an amount.',
    'barem_range_error' => 'Amount must be between :min and :max.',
    'barem_step_error' => 'Amount must increase in steps of :step.',
];
