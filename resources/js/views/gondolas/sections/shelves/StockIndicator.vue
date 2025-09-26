<template>
    <div v-if="stockInfo" @click="($event) => $emit('click', $event)"
        class="coverage-indicator absolute inset-0 w-full top-0 left-0 right-0 bottom-0 opacity-90 flex items-center justify-center"
        :class="{
            'border-2 border-red-500 bg-red-100': stockStatus === 'increase',
            'border-2 border-blue-500 bg-blue-100': stockStatus === 'decrease',
            'border-2 border-green-500 bg-green-100': stockStatus === 'ok',
        }">
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger as-child>
                    <div
                        class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-white shadow-lg p-1 cursor-pointer hover:scale-110 transition-transform"
                        :class="{
                            'border-2 border-red-500': stockStatus === 'increase',
                            'border-2 border-blue-500': stockStatus === 'decrease',
                            'border-2 border-green-500': stockStatus === 'ok',
                        }">
                        <span v-if="stockStatus === 'increase'" class="text-red-600">
                            <TrendingUp class="w-4 h-4" />
                        </span>
                        <span v-if="stockStatus === 'decrease'" class="text-blue-600">
                            <TrendingDown class="w-4 h-4" />
                        </span>
                        <span v-if="stockStatus === 'ok'" class="text-green-600">
                            <CheckCircle class="w-4 h-4" />
                        </span>
                    </div>
                </TooltipTrigger>
                <TooltipContent side="top" class="max-w-xs">
                    <div class="space-y-1 text-sm">
                        <p class="font-semibold">{{ props.segment.layer.product.name }}</p>
                        <hr class="border-gray-300">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <p class="text-gray-600">Capacidade Planograma:</p>
                                <p class="font-medium">{{ countItems.toFixed(0) }} unidades</p>
                            </div>
                            <div>
                                <p class="text-gray-600">Estoque Alvo:</p>
                                <p class="font-medium">{{ stockInfo.targetStock }} unidades</p>
                            </div>
                            <div>
                                <p class="text-gray-600">Estoque Atual:</p>
                                <p class="font-medium">{{ stockInfo.currentStock }} unidades</p>
                            </div>
                            <div>
                                <p class="text-gray-600">Estoque Mínimo:</p>
                                <p class="font-medium">{{ stockInfo.minimumStock }} unidades</p>
                            </div>
                        </div>
                        <hr class="border-gray-300">
                        <div class="text-center">
                            <div class="mb-2 text-xs text-gray-600">
                                <p><strong>Faixa de Tolerância:</strong></p>
                                <p>{{ Math.round(stockInfo.targetStock - toleranceMargin) }} - {{ Math.round(stockInfo.targetStock + toleranceMargin) }} unidades</p>
                            </div>
                            <p class="text-xs text-gray-500" v-if="stockStatus === 'increase'">
                                💡 Aumente o espaço do produto na gôndola
                            </p>
                            <p class="text-xs text-gray-500" v-if="stockStatus === 'decrease'">
                                💡 Diminua o espaço do produto na gôndola
                            </p>
                            <p class="text-xs text-gray-500" v-if="stockStatus === 'ok'">
                                ✅ Espaço adequado para o estoque alvo
                            </p>
                        </div>
                    </div>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { TrendingUp, TrendingDown, CheckCircle } from 'lucide-vue-next';
import type { Segment } from '@plannerate/types/segment';
import type { Shelf } from '@plannerate/types/shelves';
import { useTargetStockAnalysis } from '@plannerate/composables/useTargetStockAnalysis';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

const props = defineProps<{
    segment: Segment;
    shelf: Shelf;
}>();

defineEmits(['click']);

const { targetStockResultStore } = useTargetStockAnalysis();

// Margem de tolerância configurável (20% por padrão para ser mais realista)
const tolerancePercentage = computed(() => 0.20);

const stockInfo = computed(() => {
    if (!targetStockResultStore.result || !props.segment?.layer?.product?.ean) {
        return null;
    }
    return targetStockResultStore.result.find(item => item.ean === props.segment.layer.product.ean);
});

const segmentQuantity = computed(() => {
    return props.segment?.quantity ?? 0;
});

const countItems = computed(() => {
    if (!props.segment?.layer?.product?.depth || !props.shelf.shelf_depth) {
        return 0;
    }
    const itemsInDepth = Math.floor(props.shelf.shelf_depth / props.segment.layer.product.depth);
    const totalItems = segmentQuantity.value * props.segment.layer.quantity * itemsInDepth;
    return totalItems;
});

const toleranceMargin = computed(() => {
    const info = stockInfo.value;
    if (!info || info.targetStock === undefined) {
        return 0;
    }
    const percentualMargin = info.targetStock * tolerancePercentage.value;
    return Math.max(percentualMargin, 5); // Mínimo de 5 unidades
});

const stockStatus = computed(() => {
    const info = stockInfo.value;
    if (!info || info.targetStock === undefined || !countItems.value) {
        return 'unknown';
    }
    
    const margin = toleranceMargin.value;
    const lowerBound = info.targetStock - margin;
    const upperBound = info.targetStock + margin;
    
    if (countItems.value < lowerBound) {
        return 'increase';
    }
    if (countItems.value > upperBound) {
        return 'decrease';
    }
    return 'ok';
});
</script>
