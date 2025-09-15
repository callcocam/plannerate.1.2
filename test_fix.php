<?php

require '../../../vendor/autoload.php';
$app = require_once '../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== TESTANDO CORREÇÃO DA DISTRIBUIÇÃO ===" . PHP_EOL;

// Instanciar os serviços com dependências
$facingCalculator = new \Callcocam\Plannerate\Services\FacingCalculatorService();
$placementService = new \Callcocam\Plannerate\Services\ProductPlacementService($facingCalculator);

// Pegar a section do módulo 4 da gôndola específica
$section = \Callcocam\Plannerate\Models\Section::where('ordering', 3)
    ->whereHas('gondola', function($q) {
        $q->where('id', '01k4yc5gagym3q7n5sq08hdsp8');
    })->first();

$shelves = $section->shelves()->orderBy('ordering')->get();

echo "Section ID: " . $section->id . PHP_EOL;
echo "Total de prateleiras: " . $shelves->count() . PHP_EOL . PHP_EOL;

// Produto de teste que falhou anteriormente
$productExample = [
    'product_id' => 'T1C3CD516D88A07C0C1789FA7B',
    'abc_class' => 'C',
    'final_score' => 0.5,
    'product' => [
        'width' => 17.5
    ]
];

echo "=== TESTANDO PRODUTO: " . $productExample['product_id'] . " ===" . PHP_EOL;
echo "Largura: " . $productExample['product']['width'] . "cm" . PHP_EOL;
echo "Classe ABC: " . $productExample['abc_class'] . PHP_EOL . PHP_EOL;

// Estado das prateleiras ANTES do teste
echo "=== ESTADO ANTES DO TESTE ===" . PHP_EOL;
foreach ($shelves as $index => $shelf) {
    $segments = $shelf->segments()->with('layer.product')->get();
    $usedWidth = 0;
    foreach ($segments as $segment) {
        if ($segment->layer && $segment->layer->product) {
            $product = $segment->layer->product;
            $quantity = intval($segment->layer->quantity ?? 1);
            $productWidth = floatval($product->width);
            $segmentWidth = $productWidth * $quantity;
            $usedWidth += $segmentWidth;
        }
    }
    $availableWidth = 125.0 - $usedWidth;
    
    echo "Prateleira " . ($shelf->ordering + 1) . ": " . $segments->count() . " segmentos, " . $usedWidth . "cm usados, " . $availableWidth . "cm livres" . PHP_EOL;
}

echo PHP_EOL . "=== EXECUTANDO TESTE ===" . PHP_EOL;

// Testar com facing conservador de 2
$facingTotal = 2;
$result = $placementService->tryPlaceProductInSection($section, $productExample, $facingTotal, $shelves);

echo "Resultado: " . ($result['success'] ? "SUCCESS" : "FAILED") . PHP_EOL;
echo "Segments usados: " . $result['segments_used'] . PHP_EOL;
echo "Total placements: " . $result['total_placements'] . PHP_EOL;

if (!$result['success']) {
    echo "Motivo: " . ($result['reason'] ?? 'Não especificado') . PHP_EOL;
}

echo PHP_EOL . "=== ESTADO APÓS O TESTE ===" . PHP_EOL;
foreach ($shelves as $index => $shelf) {
    $segments = $shelf->segments()->with('layer.product')->get();
    $usedWidth = 0;
    $newSegments = 0;
    
    foreach ($segments as $segment) {
        if ($segment->layer && $segment->layer->product) {
            $product = $segment->layer->product;
            $quantity = intval($segment->layer->quantity ?? 1);
            $productWidth = floatval($product->width);
            $segmentWidth = $productWidth * $quantity;
            $usedWidth += $segmentWidth;
            
            if ($product->id === $productExample['product_id']) {
                $newSegments += $quantity;
            }
        }
    }
    $availableWidth = 125.0 - $usedWidth;
    
    echo "Prateleira " . ($shelf->ordering + 1) . ": " . $segments->count() . " segmentos, " . $usedWidth . "cm usados, " . $availableWidth . "cm livres";
    if ($newSegments > 0) {
        echo " [+{$newSegments} novos produtos]";
    }
    echo PHP_EOL;
}
