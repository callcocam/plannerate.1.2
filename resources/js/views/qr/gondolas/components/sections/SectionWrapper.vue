<template>
    <div class="flex items-start relative">
        <Cremalheira :section="section" :scale-factor="scaleFactor" @delete-section="$emit('delete', section)"
            :index="index" />

        <SectionComponent :gondola="gondola" :section-index="index" :section="section" :scale-factor="scaleFactor"
            :sections-container="sectionsContainer" @segment-select="$emit('segment-select', $event)" />
    </div>
</template>
<script lang="ts" setup>
import Cremalheira from '@plannerate/views/qr/gondolas/sections/Cremalheira.vue';
import SectionComponent from '@plannerate/views/qr/gondolas/sections/Section.vue';
import { Section } from '@plannerate/types/sections';
import { Gondola } from '@plannerate/types/gondola';
defineProps<{
    section: Section,
    gondola: Gondola,
    index: number,
    scaleFactor: number,
    sectionsContainer: any
}>();

const emit = defineEmits(['delete', 'segment-select']);


</script>