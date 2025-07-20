// Category.vue - Componente modificado para usar v-model corretamente
<script setup lang="ts"> 
import { Check, ChevronsUpDown, Search, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
        required: true,
    },
    placeholder: {
        type: String,
        default: 'Selecione uma categoria',
    },
    searchPlaceholder: {
        type: String,
        default: 'Buscar categoria...',
    },
    emptyMessage: {
        type: String,
        default: 'Nenhuma categoria encontrada.',
    },
    clearable: {
        type: Boolean,
        default: true,
    },
    clearText: {
        type: String,
        default: 'Limpar seleção',
    },
    modelValue: {
        type: [Object, null],
        default: null,
    },
});

const emit = defineEmits(['update:modelValue', 'change', 'clear']);

// Valor selecionado do combobox
const value = ref<any>(props.modelValue);

// Observar mudanças externas no valor
watch(
    () => props.modelValue,
    (newValue) => {
        if (newValue !== value.value) {
            value.value = newValue;
        }
    },
    { immediate: true },
);

// Observe mudanças internas e emita eventos
watch(
    () => value.value,
    (newValue) => {
        emit('update:modelValue', newValue);
    },
    { immediate: false },
);

// Filtro de busca
const searchQuery = ref('');

// Define o tipo para as categorias
interface Category {
    id: string | number;
    name: string;
    [key: string]: any;
}

// Lista filtrada de categorias
const filteredCategories = computed<Category[]>(() => {
    if (!searchQuery.value.trim()) {
        return props.categories as Category[];
    }

    const query = searchQuery.value.toLowerCase();
    return (props.categories as Category[]).filter((category) => category.name?.toLowerCase().includes(query));
});

// Manipular mudança de valor
const handleChange = (newValue: any) => {
    value.value = newValue;
    emit('change', newValue);
};

// Resetar busca quando o dropdown é fechado
const handleOpenChange = (isOpen: any) => {
    if (!isOpen) {
        searchQuery.value = '';
    }
};

// Limpar a seleção atual
const clearSelection = (event: any) => {
    if (event) {
        event.stopPropagation(); // Evita que o evento propague para o combobox
    }
    value.value = null;
    emit('change', null);
    emit('clear');
};
</script>

<template>
    <Combobox v-model="value" by="id" @update:open="handleOpenChange" @update:model-value="handleChange">
        <ComboboxAnchor as-child>
            <ComboboxTrigger as-child>
                <Button variant="outline" class="w-full justify-between dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200" size="sm">
                    <span class="truncate">
                        {{ value?.name || placeholder }}
                    </span>
                    <div class="flex items-center gap-1">
                        <X
                            v-if="clearable && value"
                            class="h-4 w-4 shrink-0 cursor-pointer hover:opacity-70 dark:text-gray-300"
                            :title="clearText"
                            @click="clearSelection"
                        />
                        <ChevronsUpDown class="h-4 w-4 shrink-0 opacity-50 dark:text-gray-300" />
                    </div>
                </Button>
            </ComboboxTrigger>
        </ComboboxAnchor>

        <ComboboxList class="max-h-[300px] overflow-y-auto dark:bg-gray-800 dark:border-gray-700">
            <div class="sticky top-0 z-10 bg-background dark:bg-gray-800">
                <div class="relative w-full items-center">
                    <ComboboxInput
                        v-model="searchQuery"
                        class="h-10 w-full rounded-none border-0 border-b pl-9 focus-visible:ring-0 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700 dark:placeholder-gray-400"
                        :placeholder="searchPlaceholder"
                    />
                    <span class="absolute inset-y-0 start-0 flex items-center justify-center px-3">
                        <Search class="size-4 text-muted-foreground dark:text-gray-400" />
                    </span>
                </div>
            </div>

            <ComboboxEmpty class="dark:text-gray-400">{{ emptyMessage }}</ComboboxEmpty>

            <ComboboxGroup>
                <!-- Adiciona a opção de limpar como primeiro item -->
                <ComboboxItem v-if="clearable && value" :value="null" class="flex items-center justify-between border-b dark:border-gray-700 dark:hover:bg-gray-700 dark:focus:bg-gray-700">
                    <span class="text-muted-foreground dark:text-gray-400">{{ clearText }}</span>
                </ComboboxItem>

                <ComboboxItem v-for="category in filteredCategories" :key="category.id" :value="category" class="flex items-center justify-between dark:text-gray-200 dark:hover:bg-gray-700 dark:focus:bg-gray-700">
                    <span class="truncate">{{ category.name }}</span>
                    <ComboboxItemIndicator>
                        <Check class="ml-auto h-4 w-4" />
                    </ComboboxItemIndicator>
                </ComboboxItem>
            </ComboboxGroup>
        </ComboboxList>
    </Combobox>
</template>
