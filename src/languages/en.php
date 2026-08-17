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
    'price' => 'Price',
    'buy' => 'Buy',
    'warning' => 'Warning',
    'error' => 'Error',

    // Game / product list
    'out_of_stock' => 'Out of Stock',
    'no_products_found' => 'No products found for this game.',
    'select_game_prompt' => 'Please select a game to view products.',
    'games_fetch_error' => 'Could not fetch game list: ',
    'products_fetch_error' => 'Could not fetch products: ',

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
];
