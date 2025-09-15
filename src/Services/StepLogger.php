<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Services;

use Illuminate\Support\Facades\Log;

/**
 * Serviço responsável pelos logs estruturados do processo de distribuição automática
 * Mostra passo a passo como os produtos são adicionados no planograma
 */
class StepLogger
{
    private static ?string $sessionId = null;
    private static int $stepCounter = 0;
    private static string $currentProcess = '';

    public function __construct()
    {
        self::$sessionId = uniqid('planogram_');
        self::$stepCounter = 0;
    }

    /**
     * 🚀 ETAPA 1: Início do processo de distribuição automática
     */
    public static function startProcess(string $gondolaId, string $gondolaName, int $totalProducts): void
    {
        self::$sessionId = uniqid('planogram_');
        self::$stepCounter = 0;
        self::$currentProcess = 'DISTRIBUIÇÃO AUTOMÁTICA';
        
        self::logStep('INÍCIO DO PROCESSO', [
            '🎯 PROCESSO' => self::$currentProcess,
            '📋 GÔNDOLA' => [
                'ID' => $gondolaId,
                'NOME' => $gondolaName
            ],
            '📦 PRODUTOS' => [
                'TOTAL_ENCONTRADOS' => $totalProducts,
                'STATUS' => $totalProducts > 0 ? '✅ Produtos disponíveis' : '❌ Nenhum produto encontrado'
            ],
            '🔄 SESSÃO' => self::$sessionId
        ]);
    }

    /**
     * 📊 ETAPA 2: Classificação ABC dos produtos
     */
    public static function logABCClassification(array $classifiedProducts): void
    {
        $totalA = count($classifiedProducts['A']);
        $totalB = count($classifiedProducts['B']);
        $totalC = count($classifiedProducts['C']);
        $total = $totalA + $totalB + $totalC;

        self::logStep('CLASSIFICAÇÃO ABC CONCLUÍDA', [
            '📊 DISTRIBUIÇÃO' => [
                'CLASSE_A' => "$totalA produtos (" . round(($totalA/$total)*100, 1) . "%)",
                'CLASSE_B' => "$totalB produtos (" . round(($totalB/$total)*100, 1) . "%)",
                'CLASSE_C' => "$totalC produtos (" . round(($totalC/$total)*100, 1) . "%)",
                'TOTAL' => $total
            ],
            '🏆 TOP_5_CLASSE_A' => array_slice(
                array_map(fn($p) => $p['product']['name'] ?? 'Produto ' . $p['product_id'], $classifiedProducts['A']),
                0, 5
            )
        ]);
    }

    /**
     * 🏗️ ETAPA 3: Análise da estrutura da gôndola
     */
    public static function logGondolaStructure(array $structure): void
    {
        self::logStep('ESTRUTURA DA GÔNDOLA ANALISADA', [
            '🏗️ ESTRUTURA' => [
                'TOTAL_MÓDULOS' => $structure['total_sections'],
                'TOTAL_SEGMENTOS' => $structure['total_segments'],
                'SEGMENTOS_POR_NÍVEL' => $structure['segments_by_shelf_level'] ?? []
            ],
            '📏 CAPACIDADE' => [
                'ESTIMATIVA_PRODUTOS' => floor($structure['total_segments'] * 1.2), // Estimativa
                'DENSIDADE_MÉDIA' => round($structure['total_segments'] / max($structure['total_sections'], 1), 1) . ' segmentos/módulo'
            ]
        ]);
    }

    /**
     * 🎯 ETAPA 4: Início do processamento de um módulo
     */
    public static function startModule(int $moduleNumber, string $strategy, array $products): void
    {
        self::logStep("INICIANDO MÓDULO $moduleNumber", [
            '🎯 MÓDULO' => $moduleNumber,
            '📋 ESTRATÉGIA' => $strategy,
            '📦 PRODUTOS_SELECIONADOS' => [
                'QUANTIDADE' => count($products),
                'IDS' => array_column($products, 'product_id'),
                'CLASSES_ABC' => array_count_values(array_column($products, 'abc_class'))
            ]
        ]);
    }

    /**
     * 📏 ETAPA 5: Processamento de uma prateleira específica
     */
    public static function startShelf(int $moduleNumber, int $shelfLevel, string $shelfId, float $shelfWidth, float $availableWidth): void
    {
        self::logStep("PROCESSANDO PRATELEIRA", [
            '🎯 LOCALIZAÇÃO' => [
                'MÓDULO' => $moduleNumber,
                'PRATELEIRA_NÍVEL' => $shelfLevel,
                'SHELF_ID' => $shelfId
            ],
            '📏 DIMENSÕES' => [
                'LARGURA_TOTAL' => $shelfWidth . 'cm',
                'LARGURA_DISPONÍVEL' => $availableWidth . 'cm',
                'OCUPAÇÃO_ATUAL' => round((($shelfWidth - $availableWidth) / $shelfWidth) * 100, 1) . '%'
            ]
        ]);
    }

    /**
     * 🎲 ETAPA 6: Cálculo de facing para um produto
     */
    public static function logFacingCalculation(array $product, int $requestedFacing, int $finalFacing, string $reason): void
    {
        self::logStep("CÁLCULO DE FACING", [
            '📦 PRODUTO' => [
                'ID' => $product['product_id'],
                'NOME' => $product['product']['name'] ?? 'N/A',
                'CLASSE_ABC' => $product['abc_class'] ?? 'N/A',
                'LARGURA' => ($product['product']['width'] ?? 0) . 'cm'
            ],
            '🎲 FACING' => [
                'SOLICITADO' => $requestedFacing,
                'FINAL' => $finalFacing,
                'RAZÃO' => $reason,
                'LARGURA_NECESSÁRIA' => (($product['product']['width'] ?? 0) * $finalFacing) . 'cm'
            ]
        ]);
    }

    /**
     * 🔧 ETAPA 7: Criação ou uso de segmento
     */
    public static function logSegmentAction(string $action, string $segmentId, array $product, int $facing, float $segmentWidth): void
    {
        $actionText = match($action) {
            'created' => 'SEGMENTO CRIADO',
            'updated' => 'SEGMENTO ATUALIZADO',
            'reused' => 'SEGMENTO REUTILIZADO'
        };

        self::logStep($actionText, [
            '🔧 SEGMENTO' => [
                'ID' => $segmentId,
                'AÇÃO' => $actionText,
                'LARGURA' => $segmentWidth . 'cm'
            ],
            '📦 PRODUTO_COLOCADO' => [
                'ID' => $product['product_id'],
                'NOME' => $product['product']['name'] ?? 'N/A',
                'FACING' => $facing,
                'CLASSE_ABC' => $product['abc_class'] ?? 'N/A'
            ]
        ]);
    }

    /**
     * 📋 ETAPA 8: Criação de layer com produto
     */
    public static function logLayerCreation(string $layerId, array $product, int $quantity, string $segmentId): void
    {
        self::logStep("LAYER CRIADA COM PRODUTO", [
            '📋 LAYER' => [
                'ID' => $layerId,
                'SEGMENTO_ID' => $segmentId,
                'QUANTIDADE' => $quantity
            ],
            '📦 PRODUTO_ADICIONADO' => [
                'ID' => $product['product_id'],
                'NOME' => $product['product']['name'] ?? 'N/A',
                'CLASSE_ABC' => $product['abc_class'] ?? 'N/A'
            ],
            '✅ STATUS' => 'PRODUTO OFICIALMENTE COLOCADO NO PLANOGRAMA'
        ]);
    }

    /**
     * ⚠️ ETAPA 9: Produto que falhou ao ser colocado
     */
    public static function logProductFailure(array $product, string $reason, array $attemptedLocations = []): void
    {
        self::logStep("PRODUTO NÃO COLOCADO", [
            '❌ PRODUTO_FALHOU' => [
                'ID' => $product['product_id'],
                'NOME' => $product['product']['name'] ?? 'N/A',
                'CLASSE_ABC' => $product['abc_class'] ?? 'N/A',
                'LARGURA' => ($product['product']['width'] ?? 0) . 'cm'
            ],
            '⚠️ MOTIVO' => $reason,
            '🔄 TENTATIVAS' => [
                'LOCAIS_TENTADOS' => count($attemptedLocations),
                'DETALHES' => $attemptedLocations
            ]
        ]);
    }

    /**
     * 🔄 ETAPA 10: Distribuição em cascata
     */
    public static function logCascadeAttempt(array $product, int $originalModule, int $targetModule, bool $success): void
    {
        $status = $success ? '✅ SUCESSO' : '❌ FALHOU';
        
        self::logStep("TENTATIVA DE CASCATA", [
            '📦 PRODUTO' => [
                'ID' => $product['product_id'],
                'NOME' => $product['product']['name'] ?? 'N/A',
                'CLASSE_ABC' => $product['abc_class'] ?? 'N/A'
            ],
            '🔄 CASCATA' => [
                'MÓDULO_ORIGINAL' => $originalModule,
                'MÓDULO_ALTERNATIVO' => $targetModule,
                'RESULTADO' => $status
            ]
        ]);
    }

    /**
     * 📊 ETAPA 11: Resultado final do módulo
     */
    public static function logModuleResult(int $moduleNumber, array $results): void
    {
        self::logStep("MÓDULO $moduleNumber CONCLUÍDO", [
            '🎯 MÓDULO' => $moduleNumber,
            '📊 RESULTADOS' => [
                'PRODUTOS_COLOCADOS' => $results['products_placed'] ?? 0,
                'SEGMENTOS_UTILIZADOS' => $results['segments_used'] ?? 0,
                'TOTAL_FACINGS' => $results['total_placements'] ?? 0,
                'PRODUTOS_FALHARAM' => count($results['failed_products'] ?? [])
            ],
            '✅ EFICIÊNCIA' => [
                'TAXA_SUCESSO' => isset($results['success_rate']) ? $results['success_rate'] . '%' : 'N/A',
                'APROVEITAMENTO_ESPAÇO' => isset($results['space_utilization']) ? $results['space_utilization'] . '%' : 'N/A'
            ]
        ]);
    }

    /**
     * 🎉 ETAPA 12: Resultado final de todo o processo
     */
    public static function logFinalResult(array $overallResults): void
    {
        self::logStep("PROCESSO CONCLUÍDO", [
            '🎉 RESULTADO_FINAL' => [
                'PRODUTOS_COLOCADOS' => $overallResults['products_placed'] ?? 0,
                'TOTAL_FACINGS' => $overallResults['total_placements'] ?? 0,
                'SEGMENTOS_UTILIZADOS' => $overallResults['segments_used'] ?? 0,
                'MÓDULOS_PROCESSADOS' => count($overallResults['module_usage'] ?? [])
            ],
            '📊 ESTATÍSTICAS' => [
                'TAXA_SUCESSO_GERAL' => ($overallResults['placement_success_rate'] ?? 0) . '%',
                'APROVEITAMENTO_ESPAÇO' => ($overallResults['space_utilization'] ?? 0) . '%',
                'PRODUTOS_NÃO_COLOCADOS' => $overallResults['products_still_failed'] ?? 0
            ],
            '🔄 SESSÃO' => self::$sessionId,
            '⏱️ PROCESSO' => self::$currentProcess . ' FINALIZADO'
        ]);
    }

    /**
     * 📝 ETAPA PERSONALIZADA: Para logs específicos
     */
    public static function logCustomStep(string $title, array $data): void
    {
        self::logStep($title, $data);
    }

    /**
     * Método principal para registrar cada passo
     */
    private static function logStep(string $title, array $data): void
    {
        // Garantir que sessionId está inicializado
        if (self::$sessionId === null) {
            self::$sessionId = uniqid('planogram_');
        }
        
        self::$stepCounter++;
        
        $logData = [
            '🔢 PASSO' => self::$stepCounter,
            '📝 ETAPA' => $title,
            '🔄 SESSÃO' => self::$sessionId,
            '⏰ TIMESTAMP' => now()->format('H:i:s.u')
        ];
        
        // Mesclar dados específicos
        $logData = array_merge($logData, $data);
        
        Log::info("PASSO " . self::$stepCounter . ": $title", $logData);
    }

    /**
     * Obter ID da sessão atual
     */
    public static function getSessionId(): string
    {
        if (self::$sessionId === null) {
            self::$sessionId = uniqid('planogram_');
        }
        return self::$sessionId;
    }

    /**
     * Resetar contador de passos (para nova execução)
     */
    public static function resetSteps(): void
    {
        self::$stepCounter = 0;
        self::$sessionId = uniqid('planogram_');
    }

    /**
     * 🔍 Log para debug de dados específicos
     */
    public static function debug(string $context, array $data): void
    {
        if (self::$sessionId === null) {
            self::$sessionId = uniqid('planogram_');
        }
        
        Log::debug("🔍 DEBUG [$context]", array_merge([
            '🔄 SESSÃO' => self::$sessionId,
            '🔢 PASSO_ATUAL' => self::$stepCounter
        ], $data));
    }
}
