<template>
    <div>
        <div class="mb-4 flex items-center">
            <div class="rounded-full bg-gray-100 p-2 dark:bg-gray-700">
                <CheckIcon class="h-5 w-5 dark:text-gray-200" />
            </div>
            <h3 class="ml-2 text-lg font-medium dark:text-gray-100">Final Review</h3>
        </div>

        <div class="space-y-6">
            <!-- Basic Information -->
            <div class="space-y-2">
                <h4 class="border-b pb-1 font-medium dark:border-gray-700 dark:text-gray-200">Basic Information</h4>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <dl class="space-y-1">
                            <div class="flex">
                                <dt class="w-32 font-medium dark:text-gray-300">Name:</dt>
                                <dd class="dark:text-gray-200">{{ formData.gondolaName }}</dd>
                            </div>
                            <div class="flex">
                                <dt class="w-32 font-medium dark:text-gray-300">Location:</dt>
                                <dd class="dark:text-gray-200">{{ formData.location }}</dd>
                            </div>
                            <div class="flex">
                                <dt class="w-32 font-medium dark:text-gray-300">Side:</dt>
                                <dd class="dark:text-gray-200">{{ formData.side || 'Not specified' }}</dd>
                            </div>
                        </dl>
                    </div>
                    <div>
                        <dl class="space-y-1">
                            <div class="flex">
                                <dt class="w-32 font-medium dark:text-gray-300">Status:</dt>
                                <dd class="dark:text-gray-200">{{ formData.status === 'published' ? 'Published' : 'Draft' }}</dd>
                            </div>
                            <div class="flex">
                                <dt class="w-32 font-medium dark:text-gray-300">Flow:</dt>
                                <dd class="dark:text-gray-200">{{ formData.flow === 'left_to_right' ? 'Left to Right' : 'Right to Left' }}</dd>
                            </div>
                            <div class="flex">
                                <dt class="w-32 font-medium dark:text-gray-300">Scale Factor:</dt>
                                <dd class="dark:text-gray-200">{{ formData.scaleFactor }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Modules -->
            <div class="space-y-2">
                <h4 class="border-b pb-1 font-medium dark:border-gray-700 dark:text-gray-200">Modules</h4>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <dl class="space-y-1">
                            <div class="flex">
                                <dt class="w-32 font-medium dark:text-gray-300"># of Modules:</dt>
                                <dd class="dark:text-gray-200">{{ formData.numModules }}</dd>
                            </div>
                            <div class="flex">
                                <dt class="w-32 font-medium dark:text-gray-300">Width:</dt>
                                <dd class="dark:text-gray-200">{{ formData.width }}cm</dd>
                            </div>
                            <div class="flex">
                                <dt class="w-32 font-medium dark:text-gray-300">Height:</dt>
                                <dd class="dark:text-gray-200">{{ formData.height }}cm</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Base -->
            <div class="space-y-2">
                <h4 class="border-b pb-1 font-medium dark:border-gray-700 dark:text-gray-200">Base</h4>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <dl class="space-y-1">
                            <div class="flex">
                                <dt class="w-32 font-medium dark:text-gray-300">Height:</dt>
                                <dd class="dark:text-gray-200">{{ formData.baseHeight }}cm</dd>
                            </div>
                        </dl>
                    </div>
                    <div>
                        <dl class="space-y-1">
                            <div class="flex">
                                <dt class="w-32 font-medium dark:text-gray-300">Width:</dt>
                                <dd class="dark:text-gray-200">{{ formData.baseWidth }}cm</dd>
                            </div>
                        </dl>
                    </div>
                    <div>
                        <dl class="space-y-1">
                            <div class="flex">
                                <dt class="w-32 font-medium dark:text-gray-300">Depth:</dt>
                                <dd class="dark:text-gray-200">{{ formData.baseDepth }}cm</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Rack -->
            <div class="space-y-2">
                <h4 class="border-b pb-1 font-medium dark:border-gray-700 dark:text-gray-200">Rack</h4>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <dl class="space-y-1">
                            <div class="flex">
                                <dt class="w-48 font-medium dark:text-gray-300">Rack Width:</dt>
                                <dd class="dark:text-gray-200">{{ formData.rackWidth }}cm</dd>
                            </div>
                            <div class="flex">
                                <dt class="w-48 font-medium dark:text-gray-300">Hole Height:</dt>
                                <dd class="dark:text-gray-200">{{ formData.holeHeight }}cm</dd>
                            </div>
                        </dl>
                    </div>
                    <div>
                        <dl class="space-y-1">
                            <div class="flex">
                                <dt class="w-48 font-medium dark:text-gray-300">Hole Width:</dt>
                                <dd class="dark:text-gray-200">{{ formData.holeWidth }}cm</dd>
                            </div>
                            <div class="flex">
                                <dt class="w-48 font-medium dark:text-gray-300">Hole Spacing:</dt>
                                <dd class="dark:text-gray-200">{{ formData.holeSpacing }}cm</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Shelves -->
            <div class="space-y-2">
                <h4 class="border-b pb-1 font-medium dark:border-gray-700 dark:text-gray-200">Shelves</h4>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <dl class="space-y-1">
                            <div class="flex">
                                <dt class="w-32 font-medium dark:text-gray-300">Quantity:</dt>
                                <dd class="dark:text-gray-200">{{ formData.numShelves }}</dd>
                            </div>
                            <div class="flex">
                                <dt class="w-32 font-medium dark:text-gray-300">Thickness:</dt>
                                <dd class="dark:text-gray-200">{{ formData.shelfHeight }}cm</dd>
                            </div>
                        </dl>
                    </div>
                    <div>
                        <dl class="space-y-1">
                            <div class="flex">
                                <dt class="w-32 font-medium dark:text-gray-300">Width:</dt>
                                <dd class="dark:text-gray-200">{{ formData.shelfWidth }}cm</dd>
                            </div>
                            <div class="flex">
                                <dt class="w-32 font-medium dark:text-gray-300">Depth:</dt>
                                <dd class="dark:text-gray-200">{{ formData.shelfDepth }}cm</dd>
                            </div>
                            <div class="flex">
                                <dt class="w-32 font-medium dark:text-gray-300">Product Type:</dt>
                                <dd class="dark:text-gray-200">{{ formData.productType === 'normal' ? 'Normal' : 'Hook' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Visualization (Simplified for review) -->
            <div class="space-y-2">
                <h4 class="border-b pb-1 font-medium dark:border-gray-700 dark:text-gray-200">Preview</h4>
                <div class="mt-4 flex justify-center rounded-lg border bg-gray-50 p-4 dark:bg-gray-800 dark:border-gray-700">
                    <div class="flex space-x-4">
                        <div v-for="moduleIndex in parseInt(formData.numModules || 1)" :key="moduleIndex" class="w-16 border border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-700" :style="{ height: `${formData.height / 5}px`}">
                            <div class="absolute bottom-0 left-0 right-0 bg-gray-300 dark:bg-gray-600" :style="{ height: `${formData.baseHeight / 5}px`}"></div>
                            <div
                                v-for="i in parseInt(formData.numShelves || 1)"
                                :key="i"
                                class="mx-1 h-1 border-t border-gray-400 bg-gray-200 dark:border-gray-600 dark:bg-gray-400"
                                :style="{ marginTop: `${(formData.height - formData.baseHeight) / (formData.numShelves || 1) / 5 - 1}px` }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { CheckIcon } from 'lucide-vue-next';
import { defineProps } from 'vue';

// Define Props
const props = defineProps({
    formData: {
        type: Object as () => Record<string, any>,
        required: true,
    },
});

// No local state or emits needed for review step

</script>
