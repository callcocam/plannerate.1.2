<template>
    <div class="mb-6 border-b pb-4 dark:border-gray-700" v-if="planogram">
        <!-- Modal para adicionar gôndola -->
        <!-- <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
            <Card class="dark:border-gray-700 dark:bg-gray-800">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium dark:text-gray-200">Tenant</CardTitle>
                </CardHeader>
                <CardContent v-if="planogram.store">
                    <div class="text-lg font-semibold dark:text-gray-100">{{ planogram.store.name }}</div>
                    <div class="text-sm text-muted-foreground dark:text-gray-400">{{ planogram.store.email }}</div>
                </CardContent>
            </Card>

            <Card class="dark:border-gray-700 dark:bg-gray-800">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium dark:text-gray-200">Detalhes</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-1">
                        <div class="flex justify-between">
                            <span class="text-sm text-muted-foreground dark:text-gray-400">Slug:</span>
                            <span class="text-sm font-medium dark:text-gray-300">{{ planogram.slug }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-muted-foreground dark:text-gray-400">Atualizado:</span>
                            <span class="text-sm font-medium dark:text-gray-300">{{ formatDate(planogram.updated_at) }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div> -->
        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <h2 class="text-2xl font-bold tracking-tight dark:text-gray-100">{{ planogram.name }}</h2>
                    <Badge :variant="getStatusVariant(planogram.status)">
                        {{ planogram.status }}
                    </Badge>
                    <span v-if="editorStore.hasChanges" class="ml-2 text-xs text-yellow-600 dark:text-yellow-400">(Não salvo)</span>
                </div>
                <p class="text-muted-foreground text-sm dark:text-gray-400">
                     Criado em: {{ formatDate(planogram.created_at) }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <div class="flex items-center gap-2 border-r pr-4">
                    <slot name="actions"></slot>
                </div>
                <a 
                 title="Voltar para a lista"
                href="/admin/plannerate" class="dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 flex items-center rounded-md border px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                    <XIcon class="mr-2 h-4 w-4" />
                    <span> Voltar</span>
                </a>
               
                <Button
                    variant="outline"
                    size="sm"
                    @click="openAddGondolaModal"
                    class="dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                    title="Gôndola"
                    type="button"
                >
                    <PlusCircleIcon class="mr-2 h-4 w-4" />
                    <span>Adicionar</span>
                </Button>
                <Button 
                @click="openEditGondolaModal"
                type="button"
                variant="outline" size="sm" class="dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                title="Editar">
                    <PencilIcon class="mr-2 h-4 w-4" />
                    Editar
                </Button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useEditorStore } from '@plannerate/store/editor';
import { PencilIcon, PlusCircleIcon, XIcon } from 'lucide-vue-next';
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
        name: 'plannerate.gondola.create',
        params: { id: planogramId.value },
    });
};

// Função para abrir o modal de editar gôndola
const openEditGondolaModal = () => {
    console.log('Abrindo modal de edição para gôndola:', route.params.gondolaId);
    router.push({
        name: 'plannerate.gondola.edit',
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
