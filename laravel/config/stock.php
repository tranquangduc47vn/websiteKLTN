<?php

/*
| Port application/config/stock.php
*/

return [
    'allow_negative_stock' => (bool) env('STOCK_ALLOW_NEGATIVE', false),
    'default_min_stock' => (int) env('STOCK_DEFAULT_MIN', 5),
];
