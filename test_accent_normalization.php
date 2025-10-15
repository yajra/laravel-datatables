<?php

require_once __DIR__ . '/vendor/autoload.php';

use Yajra\DataTables\Utilities\Helper;

// Test the normalizeAccents function
echo "Testing Helper::normalizeAccents() function:\n";
echo "==================================================\n";

$testCases = [
    'Tatiane Simões' => 'tatiane simoes',
    'João' => 'joao',
    'São Paulo' => 'sao paulo',
    'José' => 'jose', 
    'Ação' => 'acao',
    'Coração' => 'coracao',
    'Não' => 'nao',
    'Canção' => 'cancao',
];

foreach ($testCases as $input => $expected) {
    $result = strtolower(Helper::normalizeAccents($input));
    $status = $result === $expected ? '✅ PASS' : '❌ FAIL';
    
    echo "Input: '$input'\n";
    echo "Expected: '$expected'\n";
    echo "Result: '$result'\n";
    echo "Status: $status\n";
    echo "---\n";
}

echo "\nAll tests completed!\n";