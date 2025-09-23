<template>
    <!-- Modal de Configuração da Geração Automática -->
    <Dialog v-model:open="isOpen">
        <DialogContent class="sm:max-w-4xl">
            <DialogHeader>
                <DialogTitle class="flex items-center">
                    <Zap class="mr-2 h-5 w-5" />
                    Gerar Planograma Automático (ABC + Target Stock)
                </DialogTitle>
                <DialogDescription>
                    Configure análises ABC e Target Stock para distribuição inteligente de TODOS os produtos.
                </DialogDescription>
            </DialogHeader>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 py-4">
                <!-- COLUNA 1: Filtros Existentes -->
                <div class="space-y-4">
                    <h4 class="text-sm font-medium">Filtros de Produtos</h4>
                    
                    <div class="space-y-2">
                        <div class="flex items-center space-x-2">
                            <Checkbox id="filter-dimensions" v-model:checked="filters.dimension" />
                            <Label for="filter-dimensions" class="text-sm">
                                Apenas produtos com dimensões configuradas
                            </Label>
                        </div>
                        
                        <div class="flex items-center space-x-2">
                            <Checkbox id="filter-unused" v-model:checked="filters.unusedOnly" />
                            <Label for="filter-unused" class="text-sm">
                                Apenas produtos não utilizados na gôndola
                            </Label>
                        </div>
                        
                        <div class="flex items-center space-x-2">
                            <Checkbox id="filter-sales" v-model:checked="filters.sales" />
                            <Label for="filter-sales" class="text-sm">
                                Apenas produtos com histórico de vendas
                            </Label>
                        </div>
                        
                        <div class="flex items-center space-x-2">
                            <Checkbox id="filter-hangable" v-model:checked="filters.hangable" />
                            <Label for="filter-hangable" class="text-sm">
                                Incluir produtos penduráveis
                            </Label>
                        </div>
                        
                        <div class="flex items-center space-x-2">
                            <Checkbox id="filter-stackable" v-model:checked="filters.stackable" />
                            <Label for="filter-stackable" class="text-sm">
                                Incluir produtos empilháveis
                            </Label>
                        </div>
                    </div>

                    <!-- Modo de Geração -->
                    <div class="space-y-2">
                        <Label class="text-sm font-medium">Modo de Geração</Label>
                        <div class="flex space-x-2">
                            <Button 
                                type="button" 
                                :variant="!isIntelligentMode ? 'default' : 'outline'" 
                                size="sm" 
                                @click="setBasicMode"
                                class="flex-1"
                            >
                                Básico ({{ filters.limit }})
                            </Button>
                            <Button 
                                type="button" 
                                :variant="isIntelligentMode ? 'default' : 'outline'" 
                                size="sm" 
                                @click="setIntelligentMode"
                                class="flex-1"
                            >
                                🧠 Inteligente
                            </Button>
                        </div>
                    </div>

                    <!-- Limite de Produtos (modo básico) -->
                    <div v-if="!isIntelligentMode" class="space-y-2">
                        <Label for="product-limit" class="text-sm font-medium">
                            Limite de produtos (máximo: 50)
                        </Label>
                        <Input 
                            id="product-limit" 
                            type="number" 
                            v-model.number="filters.limit" 
                            min="1" 
                            max="50" 
                            class="w-full"
                            placeholder="20"
                        />
                    </div>

                    <!-- Limite de Produtos (modo inteligente) -->
                    <div v-if="isIntelligentMode" class="space-y-2">
                        <Label for="intelligent-limit" class="text-sm font-medium">
                            🧠 Limite inteligente (máximo: 200)
                        </Label>
                        <Input 
                            id="intelligent-limit" 
                            type="number" 
                            v-model.number="intelligentLimit" 
                            min="10" 
                            max="200" 
                            class="w-full"
                            placeholder="100"
                        />
                        <p class="text-xs text-gray-500">
                            Para testes: use 50-100 produtos para análise ABC + Target Stock
                        </p>
                    </div>
                </div>

                <!-- COLUNA 2: Parâmetros ABC -->
                <div class="space-y-4">
                    <h4 class="text-sm font-medium">📊 Análise ABC</h4>
                    
                    <div class="space-y-3">
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <Label class="text-xs">Peso Qtd</Label>
                                <Input v-model.number="abcParams.weights.quantity" 
                                       type="number" step="0.1" min="0" max="1" />
                            </div>
                            <div>
                                <Label class="text-xs">Peso Valor</Label>
                                <Input v-model.number="abcParams.weights.value" 
                                       type="number" step="0.1" min="0" max="1" />
                            </div>
                            <div>
                                <Label class="text-xs">Peso Margem</Label>
                                <Input v-model.number="abcParams.weights.margin" 
                                       type="number" step="0.1" min="0" max="1" />
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <Label class="text-xs">Limite Classe A (%)</Label>
                                <Input v-model.number="abcParams.thresholds.a" 
                                       type="number" min="1" max="100" />
                            </div>
                            <div>
                                <Label class="text-xs">Limite Classe B (%)</Label>
                                <Input v-model.number="abcParams.thresholds.b" 
                                       type="number" min="1" max="100" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- COLUNA 3: Target Stock + Facing -->
                <div class="space-y-4">
                    <h4 class="text-sm font-medium">📦 Target Stock & Facing</h4>
                    
                    <div class="space-y-3">
                        <!-- Níveis de Serviço por Classe -->
                        <div class="space-y-2">
                            <Label class="text-xs font-medium">Níveis de Serviço por Classe</Label>
                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <Label class="text-xs">Classe A</Label>
                                    <Input v-model.number="targetStockParams.serviceLevels[0].level" 
                                           type="number" step="0.01" min="0.5" max="0.99" class="text-xs" />
                                </div>
                                <div>
                                    <Label class="text-xs">Classe B</Label>
                                    <Input v-model.number="targetStockParams.serviceLevels[1].level" 
                                           type="number" step="0.01" min="0.5" max="0.99" class="text-xs" />
                                </div>
                                <div>
                                    <Label class="text-xs">Classe C</Label>
                                    <Input v-model.number="targetStockParams.serviceLevels[2].level" 
                                           type="number" step="0.01" min="0.5" max="0.99" class="text-xs" />
                                </div>
                            </div>
                        </div>
                        
                        <!-- Dias de Cobertura por Classe -->
                        <div class="space-y-2">
                            <Label class="text-xs font-medium">Dias de Cobertura por Classe</Label>
                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <Label class="text-xs">Classe A</Label>
                                    <Input v-model.number="targetStockParams.replenishmentParams[0].coverageDays" 
                                           type="number" min="1" max="30" class="text-xs" />
                                </div>
                                <div>
                                    <Label class="text-xs">Classe B</Label>
                                    <Input v-model.number="targetStockParams.replenishmentParams[1].coverageDays" 
                                           type="number" min="1" max="30" class="text-xs" />
                                </div>
                                <div>
                                    <Label class="text-xs">Classe C</Label>
                                    <Input v-model.number="targetStockParams.replenishmentParams[2].coverageDays" 
                                           type="number" min="1" max="30" class="text-xs" />
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-blue-50 p-3 rounded-md text-xs text-blue-700">
                            <strong>ℹ️ Estoque de Segurança:</strong> Calculado automaticamente usando a fórmula Z-Score × Desvio Padrão baseado no Service Level configurado.
                        </div>
                        
                        <!-- Limites de Facing -->
                        <div class="border-t pt-3">
                            <Label class="text-xs font-medium">Facing por Classe</Label>
                            <div class="grid grid-cols-3 gap-1 text-xs">
                                <div>A: 
                                    <Input v-model.number="facingLimits.A.min" type="number" min="1" max="20" class="w-12 inline" />-
                                    <Input v-model.number="facingLimits.A.max" type="number" min="1" max="20" class="w-12 inline" />
                                </div>
                                <div>B: 
                                    <Input v-model.number="facingLimits.B.min" type="number" min="1" max="20" class="w-12 inline" />-
                                    <Input v-model.number="facingLimits.B.max" type="number" min="1" max="20" class="w-12 inline" />
                                </div>
                                <div>C: 
                                    <Input v-model.number="facingLimits.C.min" type="number" min="1" max="20" class="w-12 inline" />-
                                    <Input v-model.number="facingLimits.C.max" type="number" min="1" max="20" class="w-12 inline" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resumo Expandido -->
            <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-md">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                    <div>
                        <strong>Categoria:</strong> {{ planogramCategory || 'Categoria do planograma' }}<br>
                        <strong>Produtos estimados:</strong> 
                        <span v-if="isIntelligentMode" class="text-green-600 font-bold">{{ intelligentLimit }} (inteligente)</span>
                        <span v-else class="text-blue-600 font-bold">{{ filters.limit }} (básico)</span><br>
                        <strong>Filtros ativos:</strong> {{ activeFiltersCount }} de 5
                    </div>
                    <div>
                        <strong>ABC Weights:</strong> Q:{{ abcParams.weights.quantity }}, V:{{ abcParams.weights.value }}, M:{{ abcParams.weights.margin }}<br>
                        <strong>Thresholds:</strong> A:{{ abcParams.thresholds.a }}%, B:{{ abcParams.thresholds.b }}%
                    </div>
                    <div>
                        <strong>Target Stock:</strong> A:{{ targetStockParams.replenishmentParams[0].coverageDays }}d, B:{{ targetStockParams.replenishmentParams[1].coverageDays }}d, C:{{ targetStockParams.replenishmentParams[2].coverageDays }}d<br>
                        <strong>Service Level:</strong> A:{{ Math.round(targetStockParams.serviceLevels[0].level * 100) }}%, B:{{ Math.round(targetStockParams.serviceLevels[1].level * 100) }}%, C:{{ Math.round(targetStockParams.serviceLevels[2].level * 100) }}%<br>
                        <strong>Estoque Segurança:</strong> Calculado automaticamente
                    </div>
                </div>
                
                <!-- Indicador de filtros aplicados -->
                <div class="mt-3 pt-3 border-t border-gray-300 dark:border-gray-600">
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span v-if="filters.dimension" class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full">✓ Dimensões</span>
                        <span v-if="filters.unusedOnly" class="px-2 py-1 bg-green-100 text-green-800 rounded-full">✓ Não utilizados</span>
                        <span v-if="filters.sales" class="px-2 py-1 bg-purple-100 text-purple-800 rounded-full">✓ Com vendas</span>
                        <span v-if="filters.hangable" class="px-2 py-1 bg-orange-100 text-orange-800 rounded-full">✓ Penduráveis</span>
                        <span v-if="filters.stackable" class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full">✓ Empilháveis</span>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="closeModal">
                    Cancelar
                </Button>
                
                <!-- Botão para modo básico -->
                <Button 
                    v-if="!isIntelligentMode"
                    @click="confirmGeneration" 
                    :disabled="isLoading"
                >
                    <template v-if="isLoading">
                        <svg class="animate-spin mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Gerando...
                    </template>
                    <template v-else>
                        <Zap class="mr-2 h-4 w-4" />
                        Gerar Básico ({{ filters.limit }} produtos)
                    </template>
                </Button>
                
                <!-- Botão para modo inteligente -->
                <Button 
                    v-if="isIntelligentMode"
                    @click="executeIntelligentGeneration" 
                    :disabled="isLoading"
                >
                    <template v-if="isLoading">
                        <svg class="animate-spin mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Processando ABC + Target Stock...
                    </template>
                    <template v-else>
                        <Zap class="mr-2 h-4 w-4" />
                        🧠 Gerar Inteligente ({{ intelligentLimit }} produtos)
                    </template>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { Zap } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

// Props
const props = defineProps<{
    open: boolean;
    isLoading?: boolean;
    planogramCategory?: string;
}>();

// Emits
const emit = defineEmits<{
    'update:open': [value: boolean];
    'confirm': [filters: AutoGenerateFilters];
    'confirm-intelligent': [params: IntelligentGenerationParams];
}>();

// Interface para os filtros
export interface AutoGenerateFilters {
    dimension: boolean;
    unusedOnly: boolean;
    sales: boolean;
    hangable: boolean;
    stackable: boolean;
    limit: number;
}

// Nova interface para geração inteligente
export interface IntelligentGenerationParams {
    filters: AutoGenerateFilters;
    abcParams: {
        weights: { quantity: number; value: number; margin: number; };
        thresholds: { a: number; b: number; };
    };
    targetStockParams: {
        serviceLevels: { classification: string; level: number; }[];
        replenishmentParams: { classification: string; coverageDays: number; }[];
    };
    facingLimits: {
        A: { min: number; max: number; };
        B: { min: number; max: number; };
        C: { min: number; max: number; };
    };
}

// Estado dos filtros (valores padrão iguais à sidebar)
const filters = reactive<AutoGenerateFilters>({
    dimension: true,      // Produtos com dimensões (padrão da sidebar)
    unusedOnly: true,     // Produtos não utilizados (padrão da sidebar)
    sales: true,          // Produtos com vendas (padrão da sidebar)
    hangable: false,      // Produtos penduráveis (padrão: false)
    stackable: false,     // Produtos empilháveis (padrão: false)
    limit: 20            // Limite padrão da sidebar (LIST_LIMIT)
});

// Novos parâmetros ABC (valores da foto)
const abcParams = reactive({
    weights: {
        quantity: 0.3,  // Peso Qtd
        value: 0.3,     // Peso Valor
        margin: 0.4     // Peso Margem
    },
    thresholds: {
        a: 80,          // Limite Classe A (%)
        b: 95           // Limite Classe B (%)
    }
});

// Parâmetros Target Stock (valores do TargetStockParamsPopover.vue)
const targetStockParams = reactive({
    serviceLevels: [
        { classification: 'A', level: 0.70 }, // 70% como no TargetStockResultModal
        { classification: 'B', level: 0.80 }, // 80% como no TargetStockResultModal
        { classification: 'C', level: 0.90 }  // 90% como no TargetStockResultModal
    ],
    replenishmentParams: [
        { classification: 'A', coverageDays: 2 }, // 2 dias como no TargetStockResultModal
        { classification: 'B', coverageDays: 5 }, // 5 dias como no TargetStockResultModal
        { classification: 'C', coverageDays: 7 }  // 7 dias como no TargetStockResultModal
    ]
    // Estoque Segurança: Calculado automaticamente (Z-Score × Desvio Padrão)
});

// Limites de Facing por classe
const facingLimits = reactive({
    A: { min: 2, max: 12 },
    B: { min: 1, max: 8 },
    C: { min: 1, max: 4 }
});

// Computed
const isOpen = computed({
    get: () => props.open,
    set: (value) => emit('update:open', value)
});

const activeFiltersCount = computed(() => {
    let count = 0;
    if (filters.dimension) count++;
    if (filters.unusedOnly) count++;
    if (filters.sales) count++;
    if (filters.hangable) count++;
    if (filters.stackable) count++;
    return count;
});

// Computed para estimativa de produtos
const estimatedProducts = computed(() => {
    // Esta é uma estimativa baseada nos filtros
    // Em um cenário real, você faria uma consulta à API
    let baseEstimate = 1000; // Estimativa base
    
    if (filters.dimension) {
        baseEstimate = Math.floor(baseEstimate * 0.35); // ~35% têm dimensões
    }
    
    if (filters.sales) {
        baseEstimate = Math.floor(baseEstimate * 0.8); // ~80% têm vendas
    }
    
    if (!filters.hangable) {
        baseEstimate = Math.floor(baseEstimate * 0.9); // ~90% não são penduráveis
    }
    
    if (!filters.stackable) {
        baseEstimate = Math.floor(baseEstimate * 0.7); // ~70% não são empilháveis
    }
    
    if (filters.unusedOnly) {
        baseEstimate = Math.floor(baseEstimate * 0.6); // ~60% não estão na gôndola
    }
    
    return Math.max(1, baseEstimate);
});

// Estado separado para controlar o modo de geração
const generationMode = ref<'basic' | 'intelligent'>('basic');

// Limite específico para modo inteligente
const intelligentLimit = ref(100);

// Computed para mostrar se é geração inteligente ou básica
const isIntelligentMode = computed(() => {
    return generationMode.value === 'intelligent';
});

// Métodos
const closeModal = () => {
    emit('update:open', false);
};

const confirmGeneration = () => {
    emit('confirm', { ...filters });
};

// Novo método para geração inteligente
const executeIntelligentGeneration = () => {
    // No modo inteligente, usa o limite configurado pelo usuário
    const intelligentFilters = { 
        ...filters, 
        limit: intelligentLimit.value // Usa o limite configurado para o modo inteligente
    };
    
    emit('confirm-intelligent', {
        filters: intelligentFilters,
        abcParams: { ...abcParams },
        targetStockParams: { ...targetStockParams },
        facingLimits: { ...facingLimits }
    });
};

// Métodos para alternar entre modos (preserva o limite configurado)
const setBasicMode = () => {
    generationMode.value = 'basic';
    // Preserva o limite atual configurado pelo usuário
};

const setIntelligentMode = () => {
    generationMode.value = 'intelligent';
    // No modo inteligente, usa todos os produtos (ignora limite)
};

// Reset dos filtros para valores padrão
const resetFilters = () => {
    filters.dimension = true;
    filters.unusedOnly = true;
    filters.sales = true;
    filters.hangable = false;
    filters.stackable = false;
    filters.limit = 20;
    generationMode.value = 'basic';
    intelligentLimit.value = 100;
    
    // Reset dos parâmetros inteligentes (valores da foto)
    abcParams.weights.quantity = 0.3;  // Peso Qtd
    abcParams.weights.value = 0.3;     // Peso Valor  
    abcParams.weights.margin = 0.4;    // Peso Margem
    abcParams.thresholds.a = 80;       // Limite Classe A (%)
    abcParams.thresholds.b = 95;       // Limite Classe B (%)
    
    targetStockParams.serviceLevels = [
        { classification: 'A', level: 0.70 }, // 70% como no TargetStockResultModal
        { classification: 'B', level: 0.80 }, // 80% como no TargetStockResultModal
        { classification: 'C', level: 0.90 }  // 90% como no TargetStockResultModal
    ];
    targetStockParams.replenishmentParams = [
        { classification: 'A', coverageDays: 2 }, // 2 dias como no TargetStockResultModal
        { classification: 'B', coverageDays: 5 }, // 5 dias como no TargetStockResultModal
        { classification: 'C', coverageDays: 7 }  // 7 dias como no TargetStockResultModal
    ];
    // Estoque Segurança: Calculado automaticamente
    
    facingLimits.A = { min: 2, max: 12 };
    facingLimits.B = { min: 1, max: 8 };
    facingLimits.C = { min: 1, max: 4 };
};

// Expor métodos para o componente pai
defineExpose({
    resetFilters
});
</script>
