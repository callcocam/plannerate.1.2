<template>
    <div class="flex items-center relative">
        <Cremalheira :section="section" :scale-factor="scaleFactor" @delete-section="$emit('delete', section)"
            @edit-section="$emit('edit', section)">
            <template #actions>
                <!-- Botão de arrastar -->
                <Button size="sm"
                    class="drag-handle h-6 w-6 cursor-move p-0 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                    variant="secondary" title="Arrastar seção">
                    <MoveIcon class="h-3 w-3" />
                </Button>

                <!-- Botão inverter prateleiras -->
                <Button v-if="canInvertShelves" size="sm" variant="outline"
                    class="h-6 w-6 p-0 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                    title="Inverter ordem das prateleiras" @click="$emit('invert-shelves', section)">
                    <ArrowUpDownIcon class="h-3 w-3" />
                </Button>
            </template>
        </Cremalheira>

        <SectionComponent :gondola="gondola" :section-index="index" :section="section" :scale-factor="scaleFactor"
            :sections-container="sectionsContainer" @segment-select="$emit('segment-select', $event)" />
    </div>
</template>
<script lang="ts" setup>
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import Cremalheira from '@plannerate/views/gondolas/sections/Cremalheira.vue';
import SectionComponent from '@plannerate/views/gondolas/sections/Section.vue';
import { Section } from '@plannerate/types/sections';
import { Gondola } from '@plannerate/types/gondola';
import { ArrowUpDownIcon, MoveIcon } from 'lucide-vue-next';

const props = defineProps<{
    section: Section,
    gondola: Gondola,
    index: number,
    scaleFactor: number,
    sectionsContainer: any
}>();

const emit = defineEmits(['delete', 'edit', 'invert-shelves', 'segment-select']);

const canInvertShelves = computed(() => {
    return props.section.shelves && props.section.shelves.length > 1;
});

</script>