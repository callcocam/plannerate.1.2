<template>
    <div class="flex relative" :class="{
        'items-start': flow === 'left_to_right',
        'items-end justify-end': flow === 'right_to_left'
    }" :style="{ width: width + 'px' }">
        <p class="flex items-center gap-1 text-lg text-gray-500 border border-gray-800 dark:border-gray-200 rounded-lg px-2 absolute top-0"
            :class="{
                'left-0': flow === 'left_to_right',
                '-right-12 items-end': flow === 'right_to_left'
            }">
            <IconArrowLeft v-if="flow === 'right_to_left'" class="h-6 w-6" />
            <span>Fluxo da gôndola:</span>
            <IconArrowRight v-if="flow === 'left_to_right'" class="h-6 w-6" />
        </p>
    </div>
</template>
<script setup lang="ts">
import { defineProps } from 'vue';
import IconArrowLeft from '@plannerate/components/icons/IconArrowLeft.vue';
import IconArrowRight from '@plannerate/components/icons/IconArrowRight.vue';

defineProps<{
    flow: 'left_to_right' | 'right_to_left',
    width: number
}>();
</script>