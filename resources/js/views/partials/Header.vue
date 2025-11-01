<template>
    <div class="mb-6 border-b border-gray-200 pb-4 dark:border-gray-700 p-4" v-if="planogram">
        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <h2 class="text-2xl font-bold tracking-tight dark:text-gray-100">{{ planogram.name }}</h2>
                    <Badge :variant="getStatusVariant(planogram.status)">
                        {{ planogram.status }}
                    </Badge>
                    <span v-if="editorStore.hasChanges" class="ml-2 text-xs text-yellow-600 dark:text-yellow-400">(Não
                        salvo)</span>
                </div>
                <p class="text-muted-foreground text-sm dark:text-gray-400">
                    Criado em: {{ formatDate(planogram.created_at) }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <div class="flex items-center gap-2 border-r pr-4">
                    <slot name="actions"></slot>
                </div>
                <a title="Voltar para a lista" href="/admin/plannerate"
                    class="dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 flex items-center rounded-md border px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                    <XIcon class="mr-2 h-4 w-4" />
                    <span> Voltar</span>
                </a>
                <Button variant="outline" size="sm" @click="openImportModal"
                    class="dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                    title="Gôndola" type="button">
                    <UploadCloudIcon class="mr-2 h-4 w-4" />
                    <span>Importar Dados</span>
                </Button>
                <Button variant="outline" size="sm" @click="openAddGondolaModal"
                    class="dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                    title="Gôndola" type="button">
                    <PlusCircleIcon class="mr-2 h-4 w-4" />
                    <span>Adicionar Gôndola</span>
                </Button>
                <Button v-if="route.params.gondolaId" @click="openEditGondolaModal" type="button" variant="outline"
                    size="sm" class="dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                    title="Editar">
                    <PencilIcon class="mr-2 h-4 w-4" />
                    Editar Gôndola
                </Button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useEditorStore } from '@plannerate/store/editor';
import { PencilIcon, PlusCircleIcon, UploadCloudIcon, XIcon } from 'lucide-vue-next';
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const router = useRouter();
const route = useRoute();
const editorStore = useEditorStore();

// Props para receber os dados do planograma do Inertia
const props = defineProps({
    planogram: {
        type: Object,
        required: true,
    },
});

const planogramId = computed(() => props.planogram?.id || route.params.id);

// Emitir eventos para o componente pai
const emit = defineEmits(['close', 'gondola-added']);

// Função para abrir o modal de adicionar gôndola
const openAddGondolaModal = () => {
    router.push({
        name: 'plannerate.create',
        params: {
            id: planogramId.value,
        },
    });
};

// Função para abrir o modal de editar gôndola
const openEditGondolaModal = () => {
    router.push({
        name: 'plannerate.gondola.edit',
        params: { id: planogramId.value, gondolaId: route.params.gondolaId },
    });
};
// Função para abrir o modal de importação
const openImportModal = () => {
    router.push({
        name: 'plannerate.gondola.import',
        params: { id: planogramId.value, gondolaId: route.params.gondolaId },
    });
};
// Função para formatar datas
const formatDate = (dateString: string) => {
    if (!dateString) return 'N/A';

    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Determina a variante de cor do badge com base no status
const getStatusVariant = (status: string) => {
    switch (status?.toLowerCase()) {
        case 'published':
            return 'success';
        case 'draft':
            return 'secondary';
        default:
            return 'default';
    }
};
</script>
