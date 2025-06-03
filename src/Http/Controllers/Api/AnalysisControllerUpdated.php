<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */
namespace Callcocam\Plannerate\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Callcocam\Plannerate\Models\Planogram;
use Callcocam\Plannerate\Services\Analysis\ABCAnalysisService;
use Callcocam\Plannerate\Services\Analysis\TargetStockAnalysisService;
use Callcocam\Plannerate\Services\Analysis\BCGAnalysisServiceImproved;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AnalysisControllerUpdated extends Controller
{
    protected $abcService;
    protected $targetStockService;
    protected $bcgService;

    // Configurações válidas para BCG
    private const VALID_BCG_LEVELS = [
        'segmento_varejista',
        'departamento',
        'subdepartamento',
        'categoria',
        'subcategoria',
        'produto'
    ];

    private const VALID_BCG_COMBINATIONS = [
        'segmento_varejista' => ['departamento', 'subdepartamento', 'categoria', 'subcategoria', 'produto'],
        'departamento' => ['subdepartamento', 'categoria', 'produto'],
        'subdepartamento' => ['categoria', 'produto'],
        'categoria' => ['subcategoria', 'produto'],
        'subcategoria' => ['produto']
    ];

    public function __construct(
        ABCAnalysisService $abcService,
        TargetStockAnalysisService $targetStockService,
        BCGAnalysisServiceImproved $bcgService
    ) {
        $this->abcService = $abcService;
        $this->targetStockService = $targetStockService;
        $this->bcgService = $bcgService;
    }

    /**
     * Realiza análise BCG com configuração hierárquica
     */
    public function bcgAnalysis(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'products' => 'required|array|min:1',
            'planogram' => 'required|string|exists:planograms,id',
            'storeId' => 'nullable|integer|exists:stores,id',
            'xAxis' => 'nullable|string|in:VALOR DE VENDA,VENDA EM QUANTIDADE,MARGEM DE CONTRIBUIÇÃO',
            'yAxis' => 'nullable|string|in:VALOR DE VENDA,VENDA EM QUANTIDADE,MARGEM DE CONTRIBUIÇÃO',
            'classifyBy' => ['nullable', 'string', Rule::in(self::VALID_BCG_LEVELS)],
            'displayBy' => ['nullable', 'string', Rule::in(self::VALID_BCG_LEVELS)],
            'configuration' => 'nullable|array',
            'configuration.rule' => 'nullable|string',
            'configuration.isValid' => 'nullable|boolean'
        ]);

        // Valores padrão
        $classifyBy = $validatedData['classifyBy'] ?? 'categoria';
        $displayBy = $validatedData['displayBy'] ?? 'produto';
        $xAxis = $validatedData['xAxis'] ?? 'VALOR DE VENDA';
        $yAxis = $validatedData['yAxis'] ?? 'MARGEM DE CONTRIBUIÇÃO';

        // Validar combinação
        if (!$this->isValidBCGCombination($classifyBy, $displayBy)) {
            return response()->json([
                'error' => 'Combinação inválida',
                'message' => "Não é possível classificar por '{$classifyBy}' e exibir por '{$displayBy}'",
                'valid_combinations' => self::VALID_BCG_COMBINATIONS
            ], 422);
        }

        // Log da configuração
        Log::info('BCG Controller - Configuração recebida:', [
            'classifyBy' => $classifyBy,
            'displayBy' => $displayBy,
            'xAxis' => $xAxis,
            'yAxis' => $yAxis,
            'products_count' => count($validatedData['products']),
            'configuration' => $validatedData['configuration'] ?? null
        ]);

        try {
            $planogram = Planogram::findOrFail($validatedData['planogram']);

            $result = $this->bcgService->analyze(
                $validatedData['products'],
                $planogram->start_date,
                $planogram->end_date,
                $xAxis,
                $yAxis,
                $validatedData['storeId'] ?? null,
                $classifyBy,
                $displayBy
            );

            // Adicionar metadados da análise
            $response = [
                'data' => $result,
                'metadata' => [
                    'configuration' => [
                        'classify_by' => $classifyBy,
                        'display_by' => $displayBy,
                        'x_axis' => $xAxis,
                        'y_axis' => $yAxis,
                        'period' => [
                            'start_date' => $planogram->start_date,
                            'end_date' => $planogram->end_date
                        ]
                    ],
                    'summary' => [
                        'total_items' => count($result),
                        'aggregation_level' => $displayBy,
                        'classification_level' => $classifyBy
                    ]
                ]
            ];

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Erro na análise BCG:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Erro interno na análise BCG',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna as configurações válidas para BCG
     */
    public function bcgConfigurations(): JsonResponse
    {
        $configurations = [];

        foreach (self::VALID_BCG_COMBINATIONS as $classifyBy => $displayOptions) {
            foreach ($displayOptions as $displayBy) {
                $configurations[] = [
                    'classify_by' => $classifyBy,
                    'display_by' => $displayBy,
                    'label' => $this->generateConfigurationLabel($classifyBy, $displayBy),
                    'hierarchy_level' => array_search($classifyBy, self::VALID_BCG_LEVELS) <=> array_search($displayBy, self::VALID_BCG_LEVELS)
                ];
            }
        }

        return response()->json([
            'configurations' => $configurations,
            'levels' => self::VALID_BCG_LEVELS,
            'axis_options' => [
                'VALOR DE VENDA',
                'VENDA EM QUANTIDADE',
                'MARGEM DE CONTRIBUIÇÃO'
            ]
        ]);
    }

    /**
     * Valida uma combinação específica de BCG
     */
    public function validateBCGConfiguration(Request $request): JsonResponse
    {
        $request->validate([
            'classifyBy' => ['required', 'string', Rule::in(self::VALID_BCG_LEVELS)],
            'displayBy' => ['required', 'string', Rule::in(self::VALID_BCG_LEVELS)]
        ]);

        $classifyBy = $request->classifyBy;
        $displayBy = $request->displayBy;
        $isValid = $this->isValidBCGCombination($classifyBy, $displayBy);

        return response()->json([
            'is_valid' => $isValid,
            'classify_by' => $classifyBy,
            'display_by' => $displayBy,
            'label' => $isValid ? $this->generateConfigurationLabel($classifyBy, $displayBy) : null,
            'available_display_options' => self::VALID_BCG_COMBINATIONS[$classifyBy] ?? [],
            'message' => $isValid ? 'Combinação válida' : 'Combinação inválida'
        ]);
    }

    /**
     * Análise ABC (mantida para compatibilidade)
     */
    public function abcAnalysis(Request $request): JsonResponse
    {
        $request->validate([
            'products' => 'required|array',
            'planogram' => 'required|string',
            'storeId' => 'nullable|integer|exists:stores,id',
        ]);

        $planogram = Planogram::find($request->planogram);
        $startDate = $planogram->start_date;
        $endDate = $planogram->end_date;

        $result = $this->abcService->analyze(
            $request->products,
            $startDate,
            $endDate,
            $request->storeId
        );

        return response()->json($result);
    }

    /**
     * Análise de estoque alvo (mantida para compatibilidade)
     */
    public function targetStockAnalysis(Request $request): JsonResponse
    {
        $request->validate([
            'products' => 'required|array',
            'planogram' => 'required|string',
            'storeId' => 'nullable|integer|exists:stores,id',
        ]);

        $planogram = Planogram::find($request->planogram);
        $startDate = $planogram->start_date;
        $endDate = $planogram->end_date;

        $result = $this->targetStockService->analyze(
            $request->products,
            $startDate,
            $endDate,
            $request->storeId,
        );

        return response()->json($result);
    }

    /**
     * Validações privadas
     */
    private function isValidBCGCombination(string $classifyBy, string $displayBy): bool
    {
        return isset(self::VALID_BCG_COMBINATIONS[$classifyBy]) &&
            in_array($displayBy, self::VALID_BCG_COMBINATIONS[$classifyBy]);
    }

    private function generateConfigurationLabel(string $classifyBy, string $displayBy): string
    {
        $labels = [
            'segmento_varejista' => 'Segmento Varejista',
            'departamento' => 'Departamento',
            'subdepartamento' => 'Subdepartamento',
            'categoria' => 'Categoria',
            'subcategoria' => 'Subcategoria',
            'produto' => 'Produto'
        ];

        $classifyLabel = $labels[$classifyBy] ?? $classifyBy;
        $displayLabel = $labels[$displayBy] ?? $displayBy;

        return "Classificar por {$classifyLabel} → Exibir por {$displayLabel}";
    }
}
