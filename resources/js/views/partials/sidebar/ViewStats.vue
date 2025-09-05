<template>
    <div class="space-y-6 p-4">
        <!-- Header com Imagem e Nome do Produto -->
        <div v-if="productInfo"
            class="flex flex-col items-center text-center space-y-3 pb-4 border-b dark:border-gray-700">
            <div class="relative">
                <img :src="productInfo.image_url" alt=""
                    class="h-24 w-24 rounded-lg border-2 object-contain shadow-lg dark:border-gray-600"
                    @error="(e) => handleImageError(e, productInfo)" />

                <!-- Status Badge no canto da imagem -->
                <span :class="statusClass"
                    class="absolute -top-2 -right-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium">
                    {{ statusText }}
                </span>
            </div>

            <div class="space-y-1">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ productInfo.name }}</h3>
                <p v-if="productInfo.description && productInfo.description !== productInfo.name"
                    class="text-sm text-gray-600 dark:text-gray-400 max-w-xs">
                    {{ productInfo.description }}
                </p>
            </div>

            <!-- Botão de Edição -->
            <EditProduct :product="getProductForEdit(productInfo)" @update:product="handleLayerUpdate">
                <button
                    class="inline-flex items-center gap-1 px-3 py-1.5 text-xs bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-md transition-colors dark:bg-blue-900/20 dark:hover:bg-blue-900/40 dark:text-blue-300">
                    <EditIcon class="h-3 w-3" />
                    Editar
                </button>
            </EditProduct>
            <fieldset>
                <legend>Sincronizar</legend>
                <button @click="syncProduct"
                    class="mt-2 inline-flex items-center gap-1 px-3 py-1.5 text-xs bg-green-50 hover:bg-green-100 text-green-700 rounded-md transition-colors dark:bg-green-900/20 dark:hover:bg-green-900/40 dark:text-green-300">
                    🔄 Produto
                </button>
                <button @click="syncSales"
                    class="mt-2 inline-flex items-center gap-1 px-3 py-1.5 text-xs bg-yellow-50 hover:bg-yellow-100 text-yellow-700 rounded-md transition-colors dark:bg-yellow-900/20 dark:hover:bg-yellow-900/40 dark:text-yellow-300">
                    📈 Vendas
                </button>
                <button @click="syncPurchases"
                    class="mt-2 inline-flex items-center gap-1 px-3 py-1.5 text-xs bg-purple-50 hover:bg-purple-100 text-purple-700 rounded-md transition-colors dark:bg-purple-900/20 dark:hover:bg-purple-900/40 dark:text-purple-300">
                    🛒 Compras
                </button>
            </fieldset>
        </div>

        <div v-if="productInfo" class="space-y-2">
            <!-- Informações Básicas -->
            <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Informações Básicas
                </h4>

                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div v-if="productInfo.ean" class="space-y-1">
                        <span class="text-gray-600 dark:text-gray-400">EAN</span>
                        <div class="font-mono bg-white dark:bg-gray-700 px-2 py-1 rounded border">
                            {{ productInfo.ean }}
                        </div>
                    </div>

                    <div v-if="(productInfo as any)?.codigo_erp" class="space-y-1">
                        <span class="text-gray-600 dark:text-gray-400">Código ERP</span>
                        <div class="font-mono bg-white dark:bg-gray-700 px-2 py-1 rounded border">
                            {{ (productInfo as any).codigo_erp }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dimensões -->
            <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 8V4a1 1 0 011-1h4m12 0h-4a1 1 0 011 1v4m0 8v4a1 1 0 01-1 1h-4m-12 0h4a1 1 0 01-1-1v-4" />
                    </svg>
                    Dimensões
                </h4>

                <div class="grid grid-cols-3 gap-3 text-sm">
                    <div class="text-center bg-white dark:bg-gray-700 rounded-md p-2">
                        <div class="text-gray-600 dark:text-gray-400 text-xs">Largura</div>
                        <div class="font-semibold">{{ productInfo.width }}cm</div>
                    </div>
                    <div class="text-center bg-white dark:bg-gray-700 rounded-md p-2">
                        <div class="text-gray-600 dark:text-gray-400 text-xs">Altura</div>
                        <div class="font-semibold">{{ productInfo.height }}cm</div>
                    </div>
                    <div class="text-center bg-white dark:bg-gray-700 rounded-md p-2">
                        <div class="text-gray-600 dark:text-gray-400 text-xs">Profundidade</div>
                        <div class="font-semibold">{{ productInfo.depth }}cm</div>
                    </div>
                </div>

                <div v-if="productInfo.dimensions?.weight" class="mt-2 text-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Peso: </span>
                    <span class="font-semibold">{{ productInfo.dimensions.weight }}kg</span>
                </div>
            </div>

            <!-- Hierarquia Mercadológica -->
            <div v-if="hasHierarchyData" class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-purple-700 dark:text-purple-300 mb-3 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    Hierarquia Mercadológica
                </h4>

                <div class="text-sm bg-white dark:bg-gray-800 rounded-md p-3 border">
                    {{ hasHierarchyData }}
                </div>
            </div>

            <!-- Características do Produto -->
            <div v-if="hasProductCharacteristics" class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Características
                </h4>

                <div class="flex flex-wrap gap-2">
                    <span v-if="(productInfo as any)?.stackable"
                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                        📚 Empilhável
                    </span>
                    <span v-if="(productInfo as any)?.perishable"
                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                        ⏰ Perecível
                    </span>
                    <span v-if="(productInfo as any)?.flammable"
                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                        🔥 Inflamável
                    </span>
                    <span v-if="(productInfo as any)?.hangable"
                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                        🪝 Pendurável
                    </span>
                </div>
            </div>

            <!-- Summary - Informações Financeiras -->
            <div v-if="productInfo.summary" class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-blue-700 dark:text-blue-300 mb-4 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    Resumo Financeiro
                </h4>

                <!-- Vendas -->
                <div class="mb-4">
                    <h5 class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wide">
                        Vendas</h5>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="bg-white dark:bg-gray-800 rounded-md p-2 text-center">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Valor Total</div>
                            <div class="text-sm font-bold text-green-600 dark:text-green-400">
                                R$ {{ formatCurrency(productInfo.summary.sales_total_value || 0) }}
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-md p-2 text-center">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Quantidade</div>
                            <div class="text-sm font-bold text-blue-600 dark:text-blue-400">
                                {{ productInfo.summary.sales_total_quantity || 0 }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Compras e Preços -->
                <div class="grid grid-cols-2 gap-2 mb-4">
                    <div class="bg-white dark:bg-gray-800 rounded-md p-2 text-center">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Qtd. Comprada</div>
                        <div class="text-sm font-bold text-purple-600 dark:text-purple-400">
                            {{ productInfo.summary.purchases_total_quantity || 0 }}
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-md p-2 text-center">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Preço Médio</div>
                        <div class="text-sm font-bold text-gray-700 dark:text-gray-300">
                            R$ {{ formatCurrency(productInfo.summary.preco_medio || 0) }}
                        </div>
                    </div>
                </div>

                <!-- Custos e Margem -->
                <div class="grid grid-cols-3 gap-2">
                    <div class="bg-white dark:bg-gray-800 rounded-md p-2 text-center">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Impostos</div>
                        <div class="text-sm font-bold text-orange-600 dark:text-orange-400">
                            R$ {{ formatCurrency(productInfo.summary.impostos_medio || 0) }}
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-md p-2 text-center">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Custo Médio</div>
                        <div class="text-sm font-bold text-red-600 dark:text-red-400">
                            R$ {{ formatCurrency(productInfo.summary.custo_medio || 0) }}
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-md p-2 text-center">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Margem</div>
                        <div class="text-sm font-bold"
                            :class="getMarginColor(productInfo.summary.margem_percentual || 0)">
                            {{ (productInfo.summary.margem_percentual || 0).toFixed(1) }}%
                        </div>
                    </div>
                </div>
            </div>

            <!-- Informações do Tenant -->
            <div v-if="(productInfo as any)?.tenant"
                class="text-xs text-gray-500 dark:text-gray-400 border-t pt-3 dark:border-gray-700">
                <div class="flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H9m11 0v-5a2 2 0 00-2-2H7a2 2 0 00-2 2v5m6 0v-4a1 1 0 011-1h2a1 1 0 011 1v4m-6 0h6" />
                    </svg>
                    <span class="font-medium">Tenant:</span> {{ (productInfo as any).tenant.name }}
                </div>
            </div>
        </div>

        <!-- Mensagem quando não há produto -->
        <div v-else class="text-center py-6">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2M4 13h2m0 0V9a2 2 0 012-2h2m0 0V9a2 2 0 012-2h2a2 2 0 012 2v2M6 13h12" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Nenhum produto selecionado</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Selecione um produto para ver suas informações</p>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { computed } from 'vue';
import { useViewStatsStore } from '@plannerate/store/editor/viewStats';
import EditProduct from './EditProduct.vue';
import { EditIcon } from 'lucide-vue-next';
import { Product } from '@/types/segment';
import { useProductService } from '@plannerate/services/productService';
import { useEditorStore } from '@plannerate/store/editor';

const viewStatsStore = useViewStatsStore();

const editorStore = useEditorStore();

const { updateSalesPurchasesProduct } = useProductService();

const currentGondola = computed(() => editorStore.currentState);

console.log('Current Gondola:', currentGondola.value);

const productInfo = computed(() => {
    const product = viewStatsStore.getProduct;
    return product || null;
});

const hasHierarchyData = computed(() => {
    if (!productInfo.value) return false;
    const product = productInfo.value as any;
    return product.category_hierarchy_path;
});

const hasProductCharacteristics = computed(() => {
    if (!productInfo.value) return false;
    const product = productInfo.value as any;
    return product.stackable || product.perishable || product.flammable || product.hangable;
});

const statusText = computed(() => {
    if (!productInfo.value) return '';
    const product = productInfo.value as any;

    switch (product.status) {
        case 'published':
            return 'Publicado';
        case 'draft':
            return 'Rascunho';
        case 'archived':
            return 'Arquivado';
        default:
            return product.status || 'Indefinido';
    }
});

const statusClass = computed(() => {
    if (!productInfo.value) return '';
    const product = productInfo.value as any;

    switch (product.status) {
        case 'published':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        case 'draft':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
        case 'archived':
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
    }
});

const syncProduct = async () => {
    console.log('Iniciando sincronização do produto...', currentGondola.value);
    if (!productInfo.value) return;
    await syncSalesPurchasesProduct({
        sync_products: true
    });
}


const syncSales = async () => {
    if (!productInfo.value) return;
    await syncSalesPurchasesProduct({
        sync_sales: true
    });
}


const syncPurchases = async () => {
    if (!productInfo.value) return;
    await syncSalesPurchasesProduct({
        sync_purchases: true
    });
}


const syncSalesPurchasesProduct = async (prarams = {}) => {
    if (!productInfo.value) return; 
    try {
        const response = await updateSalesPurchasesProduct({
            product: productInfo.value.id,
            client_id: currentGondola.value?.client_id, // Ajuste conforme necessário
            ...prarams
        });
        console.log('Resposta da sincronização:', response);
        // Opcional: adicionar feedback visual de sucesso 
    } catch (error) {
        console.error('Erro ao sincronizar produto:', error);
    }
}


const handleImageError = (event: Event, product: any) => {
    const target = event.target as HTMLImageElement;
    const initials = product.name
        .split(' ')
        .map((word: any) => word.charAt(0).toUpperCase())
        .join('')
        .slice(0, 2);
    target.src = `https://placehold.co/400x600?text=${initials}`;
}

const getProductForEdit = (product: Product) => {
    return {
        ...product,
        mercadologico_nivel: product.mercadologico_nivel || {
            mercadologico_nivel_1: null,
            mercadologico_nivel_2: null,
            mercadologico_nivel_3: null,
            mercadologico_nivel_4: null,
            mercadologico_nivel_5: null,
            mercadologico_nivel_6: null,
        }
    } as any;
}

const handleLayerUpdate = (updatedProduct: Product) => {
    viewStatsStore.setSelectedProduct(updatedProduct);
}

// Funções auxiliares para formatação
const formatCurrency = (value: number): string => {
    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(value);
}

const getMarginColor = (margin: number): string => {
    if (margin >= 20) return 'text-green-600 dark:text-green-400';
    if (margin >= 10) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-red-600 dark:text-red-400';
}
</script>