<template>
    <Dialog :open="isOpen">
        <DialogPersonaCloseContent class="flex max-h-[90vh] w-full max-w-2xl flex-col p-0 dark:border-gray-700 dark:bg-gray-800">
            <DialogClose
                @click="fecharModal"
                class="absolute right-4 top-4 rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:pointer-events-none data-[state=open]:bg-accent data-[state=open]:text-muted-foreground"
            >
                <X class="h-4 w-4" />
                <span class="sr-only">Close</span>
            </DialogClose>
            <!-- Cabeçalho Fixo -->
            <div class="border-b p-4 dark:border-gray-700">
                <DialogTitle class="text-xl font-semibold dark:text-gray-100">Adicionar Nova Seção</DialogTitle>
                <DialogDescription class="dark:text-gray-300">Preencha os detalhes da nova seção para a gôndola. </DialogDescription>
            </div>

            <!-- Mensagens de Erro -->
            <div v-if="Object.keys(errors).length > 0" class="border-b border-red-200 bg-red-50 p-4 dark:border-red-900/30 dark:bg-red-900/20">
                <p class="mb-2 font-medium text-red-600 dark:text-red-400">Por favor, corrija os seguintes erros:</p>
                <ul class="list-inside list-disc space-y-1 text-sm text-red-500 dark:text-red-400">
                    <li v-for="(error, key) in errors" :key="key">
                        <!-- Ajustar para exibir erros corretamente, pode precisar iterar sobre arrays de erro -->
                        {{ Array.isArray(error) ? error.join(', ') : error }}
                    </li>
                </ul>
            </div>

            <!-- Área de Conteúdo com Rolagem -->
            <div class="flex-1 overflow-y-auto p-4 dark:bg-gray-800">
                <form @submit.prevent="enviarFormulario" class="space-y-4">
                    <!-- Campos do Formulário da Seção -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <div class="col-span-2">
                            <Label for="section_name" class="dark:text-gray-300">Nome da Seção</Label>
                            <Input
                                id="section_name"
                                v-model="formData.name"
                                type="text"
                                placeholder="Ex: Seção A-1"
                                class="mt-1 w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            />
                            <span v-if="errors?.name" class="mt-1 text-xs text-red-500">{{ errors.name[0] }}</span>
                        </div>

                        <div>
                            <Label for="width" class="dark:text-gray-300">Largura da Seção (cm)</Label>
                            <Input
                                id="width"
                                v-model.number="formData.width"
                                type="number"
                                min="0"
                                step="any"
                                class="mt-1 w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            />
                            <span v-if="errors?.width" class="mt-1 text-xs text-red-500">{{ errors.width[0] }}</span>
                        </div>
                        <div>
                            <Label for="height" class="dark:text-gray-300">Altura da Seção (cm)</Label>
                            <Input
                                id="height"
                                v-model.number="formData.height"
                                type="number"
                                min="0"
                                step="any"
                                class="mt-1 w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            />
                            <span v-if="errors?.height" class="mt-1 text-xs text-red-500">{{ errors.height[0] }}</span>
                        </div>
                    </div>

                    <Separator class="my-4 dark:bg-gray-600" />
                    <p class="font-medium dark:text-gray-200">Base</p>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <Label for="base_height" class="dark:text-gray-300">Altura da Base (cm)</Label>
                            <Input
                                id="base_height"
                                v-model.number="formData.base_height"
                                type="number"
                                min="0"
                                step="any"
                                class="mt-1 w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            />
                            <span v-if="errors?.base_height" class="mt-1 text-xs text-red-500">{{ errors.base_height[0] }}</span>
                        </div>
                        <div>
                            <Label for="base_width" class="dark:text-gray-300">Largura da Base (cm)</Label>
                            <Input
                                id="base_width"
                                v-model.number="formData.base_width"
                                type="number"
                                min="0"
                                step="any"
                                class="mt-1 w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            />
                            <span v-if="errors?.base_width" class="mt-1 text-xs text-red-500">{{ errors.base_width[0] }}</span>
                        </div>
                        <div>
                            <Label for="base_depth" class="dark:text-gray-300">Profundidade da Base (cm)</Label>
                            <Input
                                id="base_depth"
                                v-model.number="formData.base_depth"
                                type="number"
                                min="0"
                                step="any"
                                class="mt-1 w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            />
                            <span v-if="errors?.base_depth" class="mt-1 text-xs text-red-500">{{ errors.base_depth[0] }}</span>
                        </div>
                    </div>

                    <Separator class="my-4 dark:bg-gray-600" />
                    <p class="font-medium dark:text-gray-200">Cremalheira</p>

                    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                        <div>
                            <Label for="cremalheira_width" class="dark:text-gray-300">Largura (cm)</Label>
                            <Input
                                id="cremalheira_width"
                                v-model.number="formData.cremalheira_width"
                                type="number"
                                min="0"
                                step="any"
                                class="mt-1 w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            />
                            <span v-if="errors?.cremalheira_width" class="mt-1 text-xs text-red-500">{{ errors.cremalheira_width[0] }}</span>
                        </div>
                        <div>
                            <Label for="hole_height" class="dark:text-gray-300">Altura Furo (cm)</Label>
                            <Input
                                id="hole_height"
                                v-model.number="formData.hole_height"
                                type="number"
                                min="0"
                                step="any"
                                class="mt-1 w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            />
                            <span v-if="errors?.hole_height" class="mt-1 text-xs text-red-500">{{ errors.hole_height[0] }}</span>
                        </div>
                        <div>
                            <Label for="hole_width" class="dark:text-gray-300">Largura Furo (cm)</Label>
                            <Input
                                id="hole_width"
                                v-model.number="formData.hole_width"
                                type="number"
                                min="0"
                                step="any"
                                class="mt-1 w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            />
                            <span v-if="errors?.hole_width" class="mt-1 text-xs text-red-500">{{ errors.hole_width[0] }}</span>
                        </div>
                        <div>
                            <Label for="hole_spacing" class="dark:text-gray-300">Espaçamento Furos (cm)</Label>
                            <Input
                                id="hole_spacing"
                                v-model.number="formData.hole_spacing"
                                type="number"
                                min="0"
                                step="any"
                                class="mt-1 w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            />
                            <span v-if="errors?.hole_spacing" class="mt-1 text-xs text-red-500">{{ errors.hole_spacing[0] }}</span>
                        </div>
                    </div>

                    <Separator class="my-4 dark:bg-gray-600" />
                    <p class="font-medium dark:text-gray-200">Prateleiras Padrão</p>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <div>
                            <Label for="shelf_width" class="dark:text-gray-300">Largura (cm)</Label>
                            <Input
                                id="shelf_width"
                                v-model.number="formData.shelf_width"
                                type="number"
                                min="0"
                                step="any"
                                class="mt-1 w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            />
                            <span v-if="errors?.shelf_width" class="mt-1 text-xs text-red-500">{{ errors.shelf_width[0] }}</span>
                        </div>
                        <div>
                            <Label for="shelf_height" class="dark:text-gray-300">Altura (cm)</Label>
                            <Input
                                id="shelf_height"
                                v-model.number="formData.shelf_height"
                                type="number"
                                min="0"
                                step="any"
                                class="mt-1 w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            />
                            <span v-if="errors?.shelf_height" class="mt-1 text-xs text-red-500">{{ errors.shelf_height[0] }}</span>
                        </div>
                        <div>
                            <Label for="shelf_depth" class="dark:text-gray-300">Profundidade (cm)</Label>
                            <Input
                                id="shelf_depth"
                                v-model.number="formData.shelf_depth"
                                type="number"
                                min="0"
                                step="any"
                                class="mt-1 w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            />
                            <span v-if="errors?.shelf_depth" class="mt-1 text-xs text-red-500">{{ errors.shelf_depth[0] }}</span>
                        </div>
                        <div>
                            <Label for="num_shelves" class="dark:text-gray-300">Número de Prateleiras</Label>
                            <Input
                                id="num_shelves"
                                v-model.number="formData.num_shelves"
                                type="number"
                                min="0"
                                class="mt-1 w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            />
                            <span v-if="errors?.num_shelves" class="mt-1 text-xs text-red-500">{{ errors.num_shelves[0] }}</span>
                        </div>
                    </div>
                    <div>
                        <Label for="product_type" class="dark:text-gray-300">Tipo de Produto Padrão</Label>
                        <Select v-model="formData.product_type">
                            <SelectTrigger class="mt-1 w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                <SelectValue placeholder="Selecione o tipo" />
                            </SelectTrigger>
                            <SelectContent class="dark:border-gray-600 dark:bg-gray-700">
                                <SelectItem value="normal" class="dark:text-gray-200 dark:hover:bg-gray-600">Normal </SelectItem>
                                <SelectItem value="hook" class="dark:text-gray-200 dark:hover:bg-gray-600">Gancheira </SelectItem>
                                <!-- Adicionar outros tipos se necessário -->
                            </SelectContent>
                        </Select>
                        <span v-if="errors?.product_type" class="mt-1 text-xs text-red-500">{{ errors.product_type[0] }}</span>
                    </div>
                </form>
            </div>

            <!-- Rodapé Fixo -->
            <div class="flex justify-between border-t bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <Button
                    variant="outline"
                    @click="fecharModal"
                    class="dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                >
                    Cancelar
                </Button>
                <Button @click="enviarFormulario" :disabled="enviando" class="dark:bg-primary dark:text-primary-foreground dark:hover:bg-primary/90">
                    <SaveIcon v-if="!enviando" class="mr-2 h-4 w-4" />
                    <Loader2Icon v-else class="mr-2 h-4 w-4 animate-spin" />
                    Adicionar Seção
                </Button>
            </div>
        </DialogPersonaCloseContent>
    </Dialog>
</template>

<script setup lang="ts">
import { Loader2Icon, SaveIcon, X } from 'lucide-vue-next';
import { onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { apiService } from '../../services';
import { useToast } from './../../components/ui/toast';
import { useEditorStore } from './../../store/editor';
// Componente específico de Dialog para fechar ao clicar fora ou ESC
import DialogPersonaCloseContent from '../../components/ui/dialog/DialogPersonaCloseContent.vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: true,
    },
    // Adicionar gondolaId como prop pode ser uma alternativa à rota
    // gondolaId: {
    //     type: [String, Number],
    //     required: true
    // }
});

const route = useRoute();
const router = useRouter();
const planogramId = ref(route.params.id);
const gondolaId = ref(route.params.gondolaId); // Obter o gondolaId da rota

const emit = defineEmits(['close', 'section-added', 'update:open']);
const { toast } = useToast();
const editorStore = useEditorStore();

const isOpen = ref(props.open);
const enviando = ref(false);
const errors = ref<Record<string, any>>({}); // Tipagem mais específica para erros

// Função para gerar um código aleatório formatado para o nome da gôndola
const gerarCodigoGondola = () => {
    const prefixo = 'GND';
    const data = new Date();
    const ano = data.getFullYear().toString().slice(2); // Últimos 2 dígitos do ano
    const mes = (data.getMonth() + 1).toString().padStart(2, '0');
    const random = Math.floor(Math.random() * 10000)
        .toString()
        .padStart(4, '0');

    return `${prefixo}-${ano}${mes}-${random}`;
};

// Formulário com campos para a seção
const formData = reactive({
    name: '', // Nome da seção
    code: '', // Novo campo para nome da seção
    gondola_id: gondolaId.value, // ID da gôndola
    num_modulos: 1,
    width: 130,
    height: 180,
    base_height: 17,
    base_width: 130, // Pode fazer sentido linkar com width? Ou ser independente?
    base_depth: 40,
    cremalheira_width: 4,
    hole_height: 3,
    hole_width: 2,
    hole_spacing: 2,
    shelf_width: 4, // Largura padrão da prateleira
    shelf_height: 4, // Espessura padrão da prateleira
    shelf_depth: 40, // Profundidade padrão da prateleira
    num_shelves: 4, // Número inicial de prateleiras a criar
    product_type: 'normal', // Tipo de produto padrão para as prateleiras
    // Remover campos da gondola que estavam antes: planogram_id, gondola_name, location, side, flow, scale_factor, status, section_code
});

watch(
    () => props.open,
    (newVal) => {
        isOpen.value = newVal;
        if (newVal) {
            // Resetar form e erros ao abrir
            Object.assign(formData, {
                name: gerarCodigoGondola(), // Nome da seção
                code: gerarCodigoGondola(), // Nome padrão
                gondola_id: gondolaId.value,
                num_modulos: 1,
                width: 130,
                height: 180,
                base_height: 17,
                base_width: 130,
                base_depth: 40,
                cremalheira_width: 4,
                hole_height: 3,
                hole_width: 2,
                hole_spacing: 2,
                shelf_width: 125, // Ajustado para ser um pouco menor que a largura da seção
                shelf_height: 4,
                shelf_depth: 40,
                num_shelves: 4,
                product_type: 'normal',
            });
            errors.value = {};
            // Atualizar gondolaId caso a rota mude enquanto o modal está fechado
            gondolaId.value = route.params.gondolaId;
            planogramId.value = route.params.id;
        }
    },
);

onMounted(() => {
    isOpen.value = props.open;
    // Definir nome padrão inicial se aberto diretamente
    if (props.open) {
        gondolaId.value = route.params.gondolaId;
        planogramId.value = route.params.id;
        formData.code = gerarCodigoGondola();
        formData.name = gerarCodigoGondola().concat(' - Seção');
        formData.shelf_width = formData.width - formData.cremalheira_width * 2; // Exemplo de cálculo
    }
});

// Função para fechar o modal
const fecharModal = () => {
    // Garante que volte para a visualização da gôndola correta
    router.push({
        name: 'gondola.view',
        params: { id: planogramId.value, gondolaId: gondolaId.value },
    });
};

// Função para enviar o formulário de adição de seção
const enviarFormulario = async () => {
    enviando.value = true;
    errors.value = {};

    if (!gondolaId.value) {
        toast({
            title: 'Erro',
            description: 'ID da Gôndola não encontrado na rota.',
            variant: 'destructive',
        });
        enviando.value = false;
        return;
    }

    // Preparar os dados para envio
    const dadosEnvio = { ...formData };
    console.log(dadosEnvio);

    try {
        // Endpoint: POST /api/gondolas/{gondolaId}/sections (Ajustar conforme sua API)
        const response = await apiService.post('sections', dadosEnvio);

        toast({
            title: 'Sucesso',
            description: 'Nova seção adicionada com sucesso!',
            variant: 'default',
        });

        // TODO: Atualizar o editorStore de forma adequada para a nova seção
        // Exemplo: editorStore.addSectionToGondola(gondolaId.value, response.data);

        emit('section-added', response.data); // Emitir evento com os dados da nova seção
        fecharModal(); // Fechar modal após sucesso
    } catch (error: any) {
        console.error('Erro ao adicionar seção:', error);

        if (error.response && error.response.status === 422) {
            errors.value = error.response.data.errors || {};
            toast({
                title: 'Erro de validação',
                description: 'Por favor, corrija os campos destacados.',
                variant: 'destructive',
            });
        } else {
            toast({
                title: 'Erro',
                description: error.response?.data?.message || 'Ocorreu um erro ao adicionar a seção.',
                variant: 'destructive',
            });
        }
    } finally {
        enviando.value = false;
    }
};
</script>

<style scoped>
/* Estilos para a barra de rolagem no modo escuro (mantidos) */
@media (prefers-color-scheme: dark) {
    .overflow-y-auto {
        scrollbar-width: thin;
        scrollbar-color: #4b5563 #1f2937;
    }

    .overflow-y-auto::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    .overflow-y-auto::-webkit-scrollbar-track {
        background: #1f2937;
    }

    .overflow-y-auto::-webkit-scrollbar-thumb {
        background-color: #4b5563;
        border-radius: 4px;
    }
}
</style>
