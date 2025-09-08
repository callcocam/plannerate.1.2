<template>
    <!-- Modal de Configuração da Geração Automática -->
    <Dialog v-model:open="isOpen">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center">
                    <Zap class="mr-2 h-5 w-5" />
                    Gerar Planograma Automaticamente
                </DialogTitle>
                <DialogDescription>
                    Configure os filtros para selecionar quais produtos incluir na geração automática.
                </DialogDescription>
            </DialogHeader>
            
            <div class="space-y-4 py-4">
                <!-- Filtros de Produtos -->
                <div class="space-y-3">
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
                </div>

                <!-- Limite de Produtos -->
                <div class="space-y-2">
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

                <!-- Resumo -->
                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-md">
                    <p class="text-xs text-gray-600 dark:text-gray-400">
                        <strong>Categoria:</strong> {{ planogramCategory || 'Categoria do planograma' }}<br>
                        <strong>Filtros ativos:</strong> {{ activeFiltersCount }} de 5<br>
                        <strong>Estimativa:</strong> Até {{ filters.limit }} produtos serão analisados
                    </p>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="closeModal">
                    Cancelar
                </Button>
                <Button @click="confirmGeneration" :disabled="isLoading">
                    <template v-if="isLoading">
                        <svg class="animate-spin mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Gerando...
                    </template>
                    <template v-else>
                        <Zap class="mr-2 h-4 w-4" />
                        Gerar Planograma
                    </template>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { computed, reactive } from 'vue';
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

// Estado dos filtros (valores padrão iguais à sidebar)
const filters = reactive<AutoGenerateFilters>({
    dimension: true,      // Produtos com dimensões (padrão da sidebar)
    unusedOnly: true,     // Produtos não utilizados (padrão da sidebar)
    sales: true,          // Produtos com vendas (padrão da sidebar)
    hangable: false,      // Produtos penduráveis (padrão: false)
    stackable: false,     // Produtos empilháveis (padrão: false)
    limit: 20            // Limite padrão da sidebar (LIST_LIMIT)
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

// Métodos
const closeModal = () => {
    emit('update:open', false);
};

const confirmGeneration = () => {
    emit('confirm', { ...filters });
};

// Reset dos filtros para valores padrão
const resetFilters = () => {
    filters.dimension = true;
    filters.unusedOnly = true;
    filters.sales = true;
    filters.hangable = false;
    filters.stackable = false;
    filters.limit = 20;
};

// Expor métodos para o componente pai
defineExpose({
    resetFilters
});
</script>
