# Prompt para Refatoração Completa do AutoPlanogramController.php

## Contexto
Você é um desenvolvedor sênior PHP/Laravel especialista em refatoração e clean code. O arquivo `AutoPlanogramController.php` está com 1.400+ linhas e múltiplos problemas de arquitetura que precisam ser corrigidos.

## Objetivos da Refatoração
1. **Reduzir tamanho**: De 1.400+ para ~800 linhas (redução de 35-40%)
2. **Eliminar código morto**: Métodos não utilizados identificados
3. **Aplicar SOLID**: Separar responsabilidades em classes dedicadas
4. **Melhorar manutenibilidade**: Métodos menores, lógica clara
5. **Manter funcionalidade**: Zero impacto nas APIs públicas

## Problemas Críticos Identificados

### 1. CÓDIGO MORTO (Remover Completamente)
```php
// DELETAR - Método abandonado (100+ linhas)
protected function distributeProductsInShelf($shelf, array $allProducts, int $startProductIndex = 0)

// DELETAR - Over-engineering (150+ linhas total)
protected function getBalancedProductsForModule1(array $classifiedProducts)
protected function getBalancedProductsForModule2(array $classifiedProducts)  
protected function getBalancedProductsForModule3(array $classifiedProducts)
protected function getBalancedProductsForModule4(array $classifiedProducts)
protected function getBalancedProductsForExtraModules(int $moduleNumber, array $classifiedProducts)

// DELETAR - Usado apenas uma vez
protected function calculateAverageProductWidth(array $products)
protected function getModuleStrategy(int $moduleNumber)
```

### 2. MÉTODOS GIGANTES (Dividir)
```php
// 200+ linhas - DIVIDIR em 3-4 métodos menores
protected function placeProductsSequentially()

// 150+ linhas - SIMPLIFICAR lógica
public function calculateScores(Request $request)

// 100+ linhas - EXTRAIR validações
protected function fillSectionVertically()
```

### 3. DUPLICAÇÃO (Consolidar)
```php
// CONSOLIDAR em FacingCalculatorService
protected function calculateOptimalFacing()
protected function calculateConservativeFacing()  
protected function calculateUsedWidthInShelf()
protected function createVerticalSegmentWithValidation()
```

## Tarefas Específicas

### FASE 1 - Limpeza (Urgente)
1. **Remover métodos identificados como código morto**
2. **Reduzir logs de debug excessivos** (manter apenas logs importantes)
3. **Eliminar comentários obsoletos** e documentação desatualizada
4. **Consolidar métodos de cálculo de largura** em 1 método

### FASE 2 - Extração de Responsabilidades
1. **Criar `FacingCalculatorService`** para todos os cálculos de facing
2. **Criar `ShelfSpaceValidator`** para validações de largura/espaço
3. **Criar `ProductDistributionStrategy`** para lógica de distribuição
4. **Criar `GondolaStructureAnalyzer`** para análise da estrutura

### FASE 3 - Simplificação da Lógica Principal
1. **Simplificar `placeProductsSequentially()`** usando services extraídos
2. **Reduzir complexidade de `calculateScores()`** movendo lógica para services
3. **Implementar Strategy Pattern** para diferentes tipos de distribuição
4. **Aplicar dependency injection** para os services criados

## Estrutura Final Desejada

```php
<?php
namespace Callcocam\Plannerate\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Callcocam\Plannerate\Services\Engine\ScoreEngineService;
use Callcocam\Plannerate\Services\FacingCalculatorService;
use Callcocam\Plannerate\Services\ShelfSpaceValidator;
use Callcocam\Plannerate\Services\ProductDistributionStrategy;

class AutoPlanogramController extends Controller
{
    public function __construct(
        protected ScoreEngineService $scoreEngine,
        protected FacingCalculatorService $facingCalculator,
        protected ShelfSpaceValidator $spaceValidator,
        protected ProductDistributionStrategy $distributionStrategy
    ) {}

    // MANTER - API principal (simplificar)
    public function calculateScores(Request $request): JsonResponse
    
    // MANTER - API de aplicação  
    public function applyScores(Request $request): JsonResponse
    
    // MANTER - API de configuração
    public function getConfig(): JsonResponse
    
    // MÉTODOS PRIVADOS SIMPLIFICADOS (5-10 máx, 20-30 linhas cada)
    private function validateCalculateScoresRequest()
    private function getProductsByPlanogramCategory() 
    private function processAutoDistribution()
    private function generateSummary()
}
```

## Critérios de Qualidade

### Métricas Obrigatórias:
- **Métodos públicos**: Máximo 50 linhas cada
- **Métodos privados**: Máximo 30 linhas cada  
- **Complexidade ciclomática**: Máximo 10 por método
- **Responsabilidade única**: 1 responsabilidade por classe/método
- **DRY**: Zero duplicação de lógica

### Clean Code:
- **Nomes descritivos**: Variáveis e métodos autoexplicativos
- **Comentários mínimos**: Código autodocumentado
- **Logs estratégicos**: Apenas logs importantes para produção
- **Exception handling**: Tratamento específico por tipo de erro

### Testes:
- **Cada service**: Deve ser testável unitariamente
- **APIs públicas**: Manter contratos existentes
- **Edge cases**: Tratar cenários de erro graciosamente

## Entregáveis

1. **AutoPlanogramController.php refatorado** (~400-500 linhas)
2. **Services criados** (4-5 classes, 100-200 linhas cada)
3. **Interfaces/Contracts** para dependency injection
4. **Documentação** das mudanças realizadas
5. **Migration guide** se necessário

## Restrições

### NÃO PODE:
- Quebrar APIs existentes (calculateScores, applyScores, getConfig)
- Alterar estrutura de response JSON
- Remover funcionalidades ativas
- Impactar performance negativamente

### DEVE:
- Manter todos os logs importantes para produção
- Preservar tratamento de erros existente
- Manter validações de entrada
- Seguir padrões Laravel existentes no projeto

---

**Comece pela FASE 1 - Limpeza, removendo código morto identificado. Depois prossiga com as fases seguintes. Documente cada mudança significativa.**