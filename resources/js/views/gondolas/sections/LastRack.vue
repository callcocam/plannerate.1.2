<template>
    <div class="flex items-center">
        <Cremalheira :section="section" :scale-factor="scaleFactor" :is-last-section="true" />
    </div>
</template>
<script lang="ts" setup>
import { type Section as SectionType } from '@plannerate/types/sections';
import Cremalheira from '@plannerate/views/gondolas/sections/Cremalheira.vue';
defineProps({
    section: {
        type: Object as () => SectionType,
        required: true,
    },
    scaleFactor: {
        type: Number,
        required: true,
    },
}); 
</script>