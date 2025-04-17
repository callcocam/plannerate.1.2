<template>
    <div class="space-y-4">
        <!-- Basic Information -->
        <div class="mb-4 flex items-center">
            <div class="rounded-full bg-gray-100 p-2 dark:bg-gray-700">
                <InfoIcon class="h-5 w-5 dark:text-gray-200" />
            </div>
            <h3 class="ml-2 text-lg font-medium dark:text-gray-100">Basic Information</h3>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="space-y-2">
                <Label for="gondolaName" class="dark:text-gray-200">Gondola Name *</Label>
                <Input 
                    id="gondolaName" 
                    v-model="formLocal.gondolaName" 
                    required 
                    @input="updateField('gondolaName', $event.target.value)" 
                    class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" 
                    :class="{ 'border-red-500': errors.gondolaName }"
                />
                <p v-if="errors.gondolaName" class="text-xs text-red-500 dark:text-red-400">{{ errors.gondolaName[0] }}</p>
            </div>

            <div class="space-y-2">
                <Label for="location" class="dark:text-gray-200">Location *</Label>
                <Input 
                    id="location" 
                    v-model="formLocal.location" 
                    placeholder="E.g.: Beverage Aisle" 
                    @input="updateField('location', $event.target.value)" 
                    class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-400" 
                    :class="{ 'border-red-500': errors.location }"
                />
                <p v-if="errors.location" class="text-xs text-red-500 dark:text-red-400">{{ errors.location[0] }}</p>
                <p v-else class="text-xs text-gray-500 dark:text-gray-400">Aisle where the gondola is located</p>
            </div>

            <div class="space-y-2">
                <Label for="side" class="dark:text-gray-200">Aisle Side *</Label>
                <Input 
                    id="side" 
                    v-model="formLocal.side" 
                    placeholder="E.g.: A, B or 1, 2" 
                    @input="updateField('side', $event.target.value)" 
                    class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-400" 
                    :class="{ 'border-red-500': errors.side }"
                />
                 <p v-if="errors.side" class="text-xs text-red-500 dark:text-red-400">{{ errors.side[0] }}</p>
                <p v-else class="text-xs text-gray-500 dark:text-gray-400">Aisle side identification</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="space-y-2">
                <Label for="scaleFactor" class="dark:text-gray-200">Scale Factor *</Label>
                <Input 
                    id="scaleFactor" 
                    type="number" 
                    v-model.number="formLocal.scaleFactor" 
                    min="1" 
                    @input="updateField('scaleFactor', $event.target.valueAsNumber)" 
                    class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" 
                    :class="{ 'border-red-500': errors.scaleFactor }"
                />
                <p v-if="errors.scaleFactor" class="text-xs text-red-500 dark:text-red-400">{{ errors.scaleFactor[0] }}</p>
                <p v-else class="text-xs text-gray-500 dark:text-gray-400">Factor to scale the visual gondola model</p>
            </div>

            <div class="space-y-2 md:col-span-2">
                <Label class="dark:text-gray-200">Flow Position *</Label>
                 <!-- Adicionar borda vermelha se houver erro no campo 'flow' -->
                 <div class="grid grid-cols-2 gap-2 rounded-md border" :class="{ 'border-red-500': errors.flow }">
                    <Button
                        :variant="formLocal.flow === 'left_to_right' ? 'default' : 'outline'"
                        @click="setFlow('left_to_right')"
                        class="justify-center rounded-r-none border-r dark:text-gray-100 dark:border-gray-600"
                        :class="{'dark:bg-primary dark:text-white': formLocal.flow === 'left_to_right', 'dark:bg-gray-700 dark:hover:bg-gray-600': formLocal.flow !== 'left_to_right'}"
                    >
                        Left to Right
                    </Button>
                    <Button
                        :variant="formLocal.flow === 'right_to_left' ? 'default' : 'outline'"
                        @click="setFlow('right_to_left')"
                        class="justify-center rounded-l-none dark:text-gray-100 dark:border-gray-600"
                        :class="{'dark:bg-primary dark:text-white': formLocal.flow === 'right_to_left', 'dark:bg-gray-700 dark:hover:bg-gray-600': formLocal.flow !== 'right_to_left'}"
                    >
                        Right to Left
                    </Button>
                </div>
                 <p v-if="errors.flow" class="text-xs text-red-500 dark:text-red-400">{{ errors.flow[0] }}</p>
                <p v-else class="text-xs text-gray-500 dark:text-gray-400">Defines the gondola flow direction</p>
            </div>
        </div>

        <!-- Status não é validado nesta etapa, então não precisa de exibição de erro -->
        <div class="space-y-2">
            <Label for="status" class="dark:text-gray-200">Status</Label>
            <Select v-model="formLocal.status" @update:modelValue="(value: string) => updateField('status', value)">
                <SelectTrigger class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                    <SelectValue placeholder="Select status" class="dark:text-gray-300" />
                </SelectTrigger>
                <SelectContent class="dark:bg-gray-800 dark:border-gray-700">
                    <SelectGroup>
                        <SelectLabel class="dark:text-gray-300">Status</SelectLabel>
                        <SelectItem value="published" class="dark:text-gray-200 dark:hover:bg-gray-700 dark:focus:bg-gray-700">Published</SelectItem>
                        <SelectItem value="draft" class="dark:text-gray-200 dark:hover:bg-gray-700 dark:focus:bg-gray-700">Draft</SelectItem>
                    </SelectGroup>
                </SelectContent>
            </Select>
        </div>
    </div>
</template>

<script setup lang="ts">
import { InfoIcon } from 'lucide-vue-next';
import { onMounted, reactive, watch, defineProps, defineEmits } from 'vue'; 
import { z } from 'zod'; // Importar Zod para tipagem, se necessário

// Tipo esperado para o objeto de erros vindo do composable
type ErrorObject = Record<string, string[] | undefined>;

const props = defineProps({
    formData: {
        type: Object as () => Record<string, any>,
        required: true,
    },
    // Adicionar prop para receber os erros
    errors: {
        type: Object as () => ErrorObject,
        required: true,
        default: () => ({}),
    }
});

const emit = defineEmits(['update:form']);

// Usar os dados do formData vindo das props
const formLocal = reactive({ ...props.formData });

// Gerar código inicial (mantido)
const generateGondolaCode = () => {
    const prefix = 'GND';
    const date = new Date();
    const year = date.getFullYear().toString().slice(2);
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const random = Math.floor(Math.random() * 10000)
        .toString()
        .padStart(4, '0');
    return `${prefix}-${year}${month}-${random}`;
};

onMounted(() => {
    if (!formLocal.gondolaName) {
        formLocal.gondolaName = generateGondolaCode();
        // Emitir atualização inicial do nome gerado
        emit('update:form', { gondolaName: formLocal.gondolaName });
    }
    if (!formLocal.flow) {
        formLocal.flow = 'left_to_right';
        // Emitir atualização inicial do flow padrão
        emit('update:form', { flow: formLocal.flow });
    }
});

// Watch para sincronizar o estado local com as props (se necessário)
watch(
    () => props.formData,
    (newVal) => {
        // Sincroniza apenas se os valores realmente mudaram para evitar loops
        if (JSON.stringify(formLocal) !== JSON.stringify(newVal)) {
             Object.assign(formLocal, newVal);
             // Não precisa gerar código aqui, pois o onMounted já cuida disso ou o usuário já digitou
        }
    },
    { deep: true },
);

// Função para definir o fluxo e emitir
const setFlow = (flowValue: 'left_to_right' | 'right_to_left') => {
    formLocal.flow = flowValue;
    emit('update:form', { flow: flowValue });
};

// Função genérica para emitir atualização de qualquer campo
const updateField = (fieldName: keyof typeof formLocal, value: any) => {
    // Atualiza o estado local primeiro (se o v-model não for suficiente)
    // formLocal[fieldName] = value;
    // Emite o evento com a chave e valor corretos
    emit('update:form', { [fieldName]: value });
};

</script>
