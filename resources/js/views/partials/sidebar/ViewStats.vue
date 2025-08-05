<template>
    <div class="space-y-4 p-2">
        <!-- Estatísticas de Visualização -->
        <div class="flex items-center w-full justify-center" v-if="productInfo">
            <img :src="productInfo.image_url" alt=""
                class="h-16 w-16 rounded-md border object-contain dark:border-gray-600"
                @error="(e) => handleImageError(e, productInfo)" />
        </div>

        <!-- Informações do Produto -->
        <div v-if="productInfo" class="border-t pt-4">
            <h4 class="text-md font-medium mb-3">Informações do Produto</h4>

            <!-- Nome e Descrição -->
            <div class="space-y-2 mb-4">

                <div>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Nome:</span>
                    <p class="text-sm text-gray-900 dark:text-gray-100">{{ productInfo.name }}</p>
                </div>
                <div v-if="productInfo.description && productInfo.description !== productInfo.name">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Descrição:</span>
                    <p class="text-sm text-gray-900 dark:text-gray-100">{{ productInfo.description }}</p>
                </div>
            </div>

            <!-- Códigos e Identificadores -->
            <div class="space-y-2 mb-4">
                <div v-if="productInfo.ean">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">EAN:</span>
                    <span class="text-sm text-gray-900 dark:text-gray-100 ml-2">{{ productInfo.ean }}</span>
                </div>
                <div v-if="(productInfo as any)?.codigo_erp">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Código ERP:</span>
                    <span class="text-sm text-gray-900 dark:text-gray-100 ml-2">{{ (productInfo as any).codigo_erp
                    }}</span>
                </div>
            </div>

            <!-- Dimensões -->
            <div v-if="productInfo.dimensions" class="mb-4">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Dimensões:</span>
                <p class="text-sm text-gray-900 dark:text-gray-100">
                    {{ productInfo.width }}cm × {{ productInfo.height }}cm × {{ productInfo.depth }}cm
                    <span v-if="productInfo.dimensions.weight">({{ productInfo.dimensions.weight }}kg)</span>
                </p>
            </div>

            <!-- Hierarquia Mercadológica -->
            <div v-if="hasHierarchyData" class="mb-4">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Hierarquia:</span>
                <div class="text-sm text-gray-900 dark:text-gray-100 mt-1 space-y-1">
                    {{ hasHierarchyData }}
                </div>
            </div>

            <!-- Características do Produto -->
            <div v-if="hasProductCharacteristics" class="mb-4">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Características:</span>
                <div class="flex flex-wrap gap-1 mt-1">
                    <span v-if="(productInfo as any)?.stackable"
                        class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                        Empilhável
                    </span>
                    <span v-if="(productInfo as any)?.perishable"
                        class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                        Perecível
                    </span>
                    <span v-if="(productInfo as any)?.flammable"
                        class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                        Inflamável
                    </span>
                    <span v-if="(productInfo as any)?.hangable"
                        class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                        Pendurável
                    </span>
                </div>
            </div>

            <!-- Status -->
            <div class="mb-4">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Status:</span>
                <span :class="statusClass" class="inline-flex items-center px-2 py-1 rounded-full text-xs ml-2">
                    {{ statusText }}
                </span>
            </div>

            <!-- Tenant -->
            <div v-if="(productInfo as any)?.tenant" class="text-xs text-gray-500 border-t pt-2">
                <span class="font-medium">Tenant:</span> {{ (productInfo as any).tenant.name }}
            </div>
            <EditProduct :product="getProductForEdit(productInfo)" @update:product="handleLayerUpdate">
                <EditIcon class="h-4 w-4 cursor-pointer no-remove-properties" />
            </EditProduct>
        </div>

        <!-- Mensagem quando não há produto -->
        <div v-else class="border-t pt-4">
            <p class="text-sm text-muted-foreground">Nenhum produto selecionado</p>
        </div>
    </div>
</template>
<script lang="ts" setup>
import { computed } from 'vue';
import { useViewStatsStore } from '@plannerate/store/editor/viewStats';
import EditProduct from './EditProduct.vue';
import { EditIcon } from 'lucide-vue-next';
import { Product } from '@/types/segment';

const viewStatsStore = useViewStatsStore();


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

const handleImageError = (event: Event, product: any) => {
    const target = event.target as HTMLImageElement;

    // Pegar as iniciais do nome do produto
    const initials = product.name
        .split(' ')
        .map((word: any) => word.charAt(0).toUpperCase())
        .join('')
        .slice(0, 2); // Limita a 2 letras (opcional)

    // Exemplo de uso com placehold.co
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
</script>