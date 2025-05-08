<template>
    <div
        class="sticky top-0 flex h-screen w-72 flex-shrink-0 flex-col overflow-hidden rounded-lg border bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
            <h3 class="text-center text-lg font-medium text-gray-800 dark:text-gray-100">Produtos Disponíveis</h3>

            <!-- Campo de busca com design aprimorado -->
            <div class="relative mt-3">
                <input v-model="filters.search" type="text" placeholder="Buscar produtos..."
                    class="w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary focus:ring-1 focus:ring-primary dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:placeholder-gray-400" />
                <Search class="absolute right-3 top-2 h-4 w-4 text-gray-400 dark:text-gray-300" />
            </div>

            <!-- Botão de filtros com design aprimorado -->
            <button
                class="mt-2 flex w-full items-center justify-between rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                @click="showFilters = !showFilters">
                <div class="flex items-center">
                    <SlidersHorizontal class="mr-2 h-4 w-4 text-gray-500 dark:text-gray-300" />
                    <span class="text-gray-700 dark:text-gray-100">Filtros</span>
                </div>
                <ChevronDown class="h-4 w-4 text-gray-500 dark:text-gray-300" :class="{ 'rotate-180': showFilters }" />
            </button>

            <!-- Painel de filtros colapsável -->
            <div v-if="showFilters"
                class="mt-2 rounded-md border border-gray-200 bg-white p-3 text-sm dark:border-gray-600 dark:bg-gray-700">
                <div class="mb-2">
                    <label class="mb-1 block text-gray-700 dark:text-gray-200">Categoria</label>
                    <select
                        class="w-full rounded-md border-gray-300 bg-white py-1 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        v-model="filters.category">
                        <option value="">Todas as categorias</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                    </select>
                </div>

                <div class="mb-2">
                    <label class="mb-1 block text-gray-700 dark:text-gray-200">Status de uso</label>
                    <select
                        class="w-full rounded-md border-gray-300 bg-white py-1 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        v-model="filters.usageStatus">
                        <option value="all">Todos</option>
                        <option value="unused">Não usados</option>
                        <option value="used">Já usados</option>
                    </select>
                </div>

                <div class="mb-2">
                    <p class="mb-1 block text-gray-700 dark:text-gray-200">Atributos</p>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center">
                            <input type="checkbox"
                                class="mr-1 rounded text-primary dark:border-gray-600 dark:bg-gray-700"
                                v-model="filters.hangable" />
                            <span class="dark:text-gray-200">Pendurável</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox"
                                class="mr-1 rounded text-primary dark:border-gray-600 dark:bg-gray-700"
                                v-model="filters.stackable" />
                            <span class="dark:text-gray-200">Empilhável</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="button" @click="clearFilters"
                        class="rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-700 hover:bg-gray-200 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                        Limpar filtros
                    </button>
                </div>
            </div>
        </div>

        <!-- Lista de produtos com design limpo -->
        <div class="flex-1 overflow-y-auto p-2 dark:bg-gray-800">
            <div v-if="!editorStore.isLoading && filteredProducts.length > 0"
                class="mb-2 px-2 py-1 text-sm text-gray-500 dark:text-gray-400">
                <span>{{ filteredProducts.length }} produtos encontrados</span>
            </div>

            <ul v-if="!editorStore.isLoading && filteredProducts.length > 0" class="space-y-1">
                <li v-for="product in filteredProducts" :key="product.id"
                    class="group cursor-pointer rounded-md bg-white p-2 shadow-sm transition hover:bg-blue-50 dark:bg-gray-700 dark:hover:bg-gray-600"
                    @click="handleProductSelect(product)" draggable="true"
                    @dragstart="handleDragStart($event, product)">
                    <div class="flex items-center space-x-3">
                        <div
                            class="flex-shrink-0 overflow-hidden rounded border bg-white p-1 dark:border-gray-600 dark:bg-gray-800">
                            <img :src="product.image_url" :alt="product.name" class="h-12 w-12 object-contain"
                                @error="(e) => handleImageError(e, product)" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-800 dark:text-gray-100">{{ product.name }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ product.width }}×{{ product.height
                            }}×{{ product.depth }} cm</p>
                        </div>
                    </div>
                    <div class="mt-1 flex justify-end">
                        <button class="invisible text-xs text-blue-600 group-hover:visible dark:text-blue-400"
                            @click.stop="viewStats(product)">
                            Ver estatísticas
                        </button>
                    </div>
                </li>
            </ul>

            <!-- Loading state -->
            <div v-if="editorStore.isLoading" class="flex items-center justify-center py-10">
                <Loader class="h-6 w-6 animate-spin text-primary" />
                <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">Carregando...</span>
            </div>

            <!-- Empty state -->
            <div v-if="!editorStore.isLoading && filteredProducts.length === 0"
                class="flex flex-col items-center justify-center py-10 text-center">
                <Package class="h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Nenhum produto disponível encontrado</p>
                <p v-if="Object.values(filters).some((f) => f)" class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                    Tente ajustar os filtros.</p>
            </div>

            <!-- Load More button -->
            <div v-if="!loading && hasMorePages" class="flex justify-center py-4">
                <button @click="loadMore"
                    class="rounded-md bg-gray-100 px-4 py-2 text-sm text-gray-700 hover:bg-gray-200 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                    Carregar mais produtos
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ChevronDown, Loader, Package, Search, SlidersHorizontal } from 'lucide-vue-next';
import { storeToRefs } from 'pinia';
import { onMounted, reactive, ref, watch } from 'vue';
import { apiService } from '@plannerate/services';
import { useEditorStore } from '@plannerate/store/editor';
import { Product } from '@plannerate/types/segment';

interface Category {
    id: number | string;
    name: string;
}

interface FilterState {
    search: string;
    category: number | string | null | '';
    hangable: boolean;
    stackable: boolean;
    usageStatus: string;
}
defineProps({
    categories: {
        type: Array as () => Category[],
        default: () => [],
    },
});

const editorStore = useEditorStore();

const { productIdsInCurrentGondola } = storeToRefs(editorStore);

const emit = defineEmits(['select-product', 'drag-start', 'view-stats']);

const showFilters = ref(false);
const filters = reactive<FilterState>({
    search: '',
    category: '',
    hangable: false,
    stackable: false,
    usageStatus: 'all',
});

const loading = ref(false);
const filteredProducts = ref<Product[]>([]);
const currentPage = ref(1);
const hasMorePages = ref(true);
const LIST_LIMIT = 20;

interface PaginatedProductsResponse {
    data: Product[];
    meta: {
        current_page: number;
        last_page: number;
    };
}

const fetchProducts = async (page = 1, append = false) => {
    if (loading.value) return;
    loading.value = true;
    console.log(`Fetching products: page=${page}, append=${append}`);

    try {
        const idsArray = Array.from(productIdsInCurrentGondola.value);

        const params: Record<string, any> = {
            search: filters.search || undefined,
            category: filters.category || undefined,
            hangable: filters.hangable || undefined,
            stackable: filters.stackable || undefined,
            page: page,
            limit: LIST_LIMIT,
        };

        // Aplicar filtro baseado no status de uso
        if (filters.usageStatus === 'unused') {
            params.notInGondola = idsArray;
        } else if (filters.usageStatus === 'used') {
            params.inGondola = true;
        }
        // Se for 'all', não aplicamos nenhum filtro específico de uso

        Object.keys(params).forEach((key) => {
            if (params[key] === undefined || params[key] === '' || (Array.isArray(params[key]) && params[key].length === 0)) {
                delete params[key];
            }
        });

        const response = await apiService.get<PaginatedProductsResponse>('products', { params });
        // console.log('API Response:', response);

        const newProducts = response.data || [];
        if (append) {
            filteredProducts.value.push(...newProducts);
        } else {
            filteredProducts.value = newProducts;
        }

        if (response.meta) {
            currentPage.value = response.meta.current_page;
            hasMorePages.value = response.meta.current_page < response.meta.last_page;
        } else {
            hasMorePages.value = newProducts.length === LIST_LIMIT;
        }
    } catch (error) {
        console.error('Erro ao carregar produtos:', error);
        if (!append) {
            filteredProducts.value = [];
        }
        hasMorePages.value = false;
    } finally {
        loading.value = false;
    }
};

watch(
    filters,
    async () => {
        // console.log('Filters changed, fetching page 1...');
        await fetchProducts(1, false);
    },
    { deep: true },
);

watch(productIdsInCurrentGondola, () => {
    console.log('Product IDs in current gondola changed (via editorStore), fetching page 1...');
    setTimeout(async () => {
        await fetchProducts(1, false);
    }, 300); // Pequeno delay
});

function loadMore() {
    if (!loading.value && hasMorePages.value) {
        fetchProducts(currentPage.value + 1, true);
    }
}

function handleProductSelect(product: Product) {
    emit('select-product', product);
}

function handleDragStart(event: DragEvent, product: Product) {
    if (event.dataTransfer) {
        event.dataTransfer.setData('text/product', JSON.stringify(product));
        event.dataTransfer.effectAllowed = 'copy';
    }
    emit('drag-start', event, product);
}

function viewStats(product: Product) {
    emit('view-stats', product);
}

function handleImageError(event: Event, product: Product) {
    const target = event.target as HTMLImageElement;

    // Pegar as iniciais do nome do produto
    const initials = product.name
        .split(' ')
        .map(word => word.charAt(0).toUpperCase())
        .join('')
        .slice(0, 2); // Limita a 2 letras (opcional)

    // Exemplo de uso com placehold.co
    target.src = `https://placehold.co/400x600?text=${initials}`;
}
function clearFilters() {
    filters.search = '';
    filters.category = '';
    filters.hangable = false;
    filters.stackable = false;
    filters.usageStatus = 'all';
    showFilters.value = false;
}

onMounted(() => {
    // console.log('Component mounted, fetching initial products...');
    fetchProducts(1, false);
});
</script>

<style scoped>
.overflow-y-auto {
    max-height: calc(100vh - 200px);
    scrollbar-width: thin;
    scrollbar-color: #e2e8f0 #f8fafc;
}

.overflow-x-auto {
    overflow-x: hidden;
}

.overflow-y-auto::-webkit-scrollbar {
    width: 8px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f8fafc;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background-color: #e2e8f0;
    border-radius: 4px;
}

@media (prefers-color-scheme: dark) {
    .overflow-y-auto {
        scrollbar-color: #4b5563 #1f2937;
    }

    .overflow-y-auto::-webkit-scrollbar-track {
        background: #1f2937;
    }

    .overflow-y-auto::-webkit-scrollbar-thumb {
        background-color: #4b5563;
    }
}
</style>
