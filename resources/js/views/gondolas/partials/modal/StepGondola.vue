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
                <Input id="gondolaName" v-model="formLocal.gondolaName" required @change="updateForm" class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
            </div>

            <div class="space-y-2">
                <Label for="location" class="dark:text-gray-200">Location</Label>
                <Input id="location" v-model="formLocal.location" placeholder="E.g.: Beverage Aisle" @change="updateForm" class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-400" />
                <p class="text-xs text-gray-500 dark:text-gray-400">Aisle where the gondola is located</p>
            </div>

            <div class="space-y-2">
                <Label for="side" class="dark:text-gray-200">Aisle Side</Label>
                <Input id="side" v-model="formLocal.side" placeholder="E.g.: A, B or 1, 2" @change="updateForm" class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-400" />
                <p class="text-xs text-gray-500 dark:text-gray-400">Aisle side identification</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="space-y-2">
                <Label for="scaleFactor" class="dark:text-gray-200">Scale Factor</Label>
                <Input id="scaleFactor" type="number" v-model.number="formLocal.scaleFactor" min="1" @change="updateForm" class="dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
                <p class="text-xs text-gray-500 dark:text-gray-400">Factor to scale the visual gondola model</p>
            </div>

            <div class="space-y-2 md:col-span-2">
                <Label class="dark:text-gray-200">Flow Position</Label>
                <div class="grid grid-cols-2 gap-2">
                    <Button
                        :variant="formLocal.flow === 'left_to_right' ? 'default' : 'outline'"
                        @click="setFlow('left_to_right')"
                        class="justify-center dark:text-gray-100 dark:border-gray-600"
                        :class="{'dark:bg-primary dark:text-white': formLocal.flow === 'left_to_right', 'dark:bg-gray-700 dark:hover:bg-gray-600': formLocal.flow !== 'left_to_right'}"
                    >
                        Left to Right
                    </Button>
                    <Button
                        :variant="formLocal.flow === 'right_to_left' ? 'default' : 'outline'"
                        @click="setFlow('right_to_left')"
                        class="justify-center dark:text-gray-100 dark:border-gray-600"
                        :class="{'dark:bg-primary dark:text-white': formLocal.flow === 'right_to_left', 'dark:bg-gray-700 dark:hover:bg-gray-600': formLocal.flow !== 'right_to_left'}"
                    >
                        Right to Left
                    </Button>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Defines the gondola flow direction</p>
            </div>
        </div>

        <div class="space-y-2">
            <Label for="status" class="dark:text-gray-200">Status</Label>
            <Select v-model="formLocal.status" @update:modelValue="updateForm">
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

const props = defineProps({
    formData: {
        type: Object as () => Record<string, any>,
        required: true,
    },
});

const emit = defineEmits(['update:form']);

const formLocal = reactive({ ...props.formData });

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
    }
    if (!formLocal.flow) {
        formLocal.flow = 'left_to_right';
    }
    updateForm();
});

watch(
    () => props.formData,
    (newVal) => {
        Object.assign(formLocal, newVal);
        if (!formLocal.gondolaName) {
            formLocal.gondolaName = generateGondolaCode();
        }
    },
    { deep: true },
);

const setFlow = (flowValue: 'left_to_right' | 'right_to_left') => {
    formLocal.flow = flowValue;
    updateForm();
};

const updateForm = () => {
    const relevantData = {
        gondolaName: formLocal.gondolaName,
        location: formLocal.location,
        side: formLocal.side,
        scaleFactor: formLocal.scaleFactor,
        flow: formLocal.flow,
        status: formLocal.status,
    };
    emit('update:form', relevantData);
};
</script>
