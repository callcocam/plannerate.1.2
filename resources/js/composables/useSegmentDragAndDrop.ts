import { ref, watch } from 'vue';
import type { Segment } from '@plannerate/types/segment';
import type { Shelf } from '@plannerate/types/shelves';

export function useSegmentDragAndDrop(
    props: { segment: Segment; shelf: Shelf },
    emit: (event: 'segment-drag-start' | 'segment-drag-end' | 'segment-drag-over', ...args: any[]) => void
) {
    const dragEnterCount = ref(0);
    const dragSegmentActive = ref(false);
    const segmentText = ref(`Arrastando Prateleira (Pos: ${props.shelf.shelf_position.toFixed(1)}cm)`);

    watch(dragSegmentActive, (newValue) => {
        segmentText.value = `Arrastando Prateleira (Pos: ${props.shelf.shelf_position.toFixed(1)}cm)`;
    });

    const isAcceptedDataType = (dataTransfer: DataTransfer | null): boolean => {
        if (!dataTransfer) return false;
        const types = dataTransfer.types;
        return types.includes('text/product') ||
            types.includes('text/products-multiple') ||
            types.includes('text/segment') ||
            types.includes('text/segment/copy');
    };

    const isSegmentBeingDragged = (dataTransfer: DataTransfer | null): boolean => {
        if (!dataTransfer) return false;
        const types = dataTransfer.types;
        return types.includes('text/segment') || types.includes('text/segment/copy');
    };

    const handleDragEnter = (event: DragEvent) => {
        if (!isAcceptedDataType(event.dataTransfer)) return;
        event.preventDefault();
        dragEnterCount.value++;
        dragSegmentActive.value = true;

        if (isSegmentBeingDragged(event.dataTransfer)) {
            emit('segment-drag-over', props.segment, props.shelf, true);
        }

        if (event.currentTarget) {
            (event.currentTarget as HTMLElement).classList.add('drag-over-segment');
        }
    };

    const handleDragOver = (event: DragEvent) => {
        if (!isAcceptedDataType(event.dataTransfer)) {
            if (event.dataTransfer) event.dataTransfer.dropEffect = 'none';
            if (dragSegmentActive.value) {
                dragSegmentActive.value = false;
                dragEnterCount.value = 0;
                if (event.currentTarget) {
                    (event.currentTarget as HTMLElement).classList.remove('drag-over-segment');
                }
            }
            return;
        }
        event.preventDefault();

        if (!dragSegmentActive.value) {
            dragSegmentActive.value = true;
            if (event.currentTarget) {
                (event.currentTarget as HTMLElement).classList.add('drag-over-segment');
            }
        }

        if (event.dataTransfer) {
            let effect: DataTransfer["dropEffect"] = 'move';
            if (event.dataTransfer.types.includes('text/segment/copy') ||
                event.dataTransfer.types.includes('text/product') ||
                event.dataTransfer.types.includes('text/products-multiple')) {
                effect = 'copy';
            }
            event.dataTransfer.dropEffect = effect;
        }
    };

    const handleDragLeave = (event: DragEvent) => {
        if (dragEnterCount.value > 0) {
            dragEnterCount.value--;
            if (dragEnterCount.value === 0) {
                if (dragSegmentActive.value) {
                    dragSegmentActive.value = false;
                    if (event.dataTransfer && isSegmentBeingDragged(event.dataTransfer)) {
                        emit('segment-drag-over', props.segment, props.shelf, false);
                    }
                    if (event.currentTarget) {
                        (event.currentTarget as HTMLElement).classList.remove('drag-over-segment');
                    }
                }
            }
        }
    };

    const handleDrop = (event: DragEvent) => {
        event.preventDefault();
        // A lógica de drop principal está comentada no original, mantendo assim.
        // Se precisar reativar, ela seria movida para cá.
        dragEnterCount.value = 0;
        dragSegmentActive.value = false;
        if (event.currentTarget) {
            (event.currentTarget as HTMLElement).classList.remove('drag-over-segment');
        }
    };

    const onDragStart = (event: DragEvent) => {
        if (!event.dataTransfer) return;
        const isCtrlOrMetaPressed = event.ctrlKey || event.metaKey;
        const segmentData = { ...props.segment, shelf_id: props.shelf.id };

        if (isCtrlOrMetaPressed) {
            event.dataTransfer.effectAllowed = 'copy';
            event.dataTransfer.setData('text/segment/copy', JSON.stringify(segmentData));
        } else {
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/segment', JSON.stringify(segmentData));
        }
        emit('segment-drag-start', props.segment, props.shelf);
    };

    const onDragEnd = (event: DragEvent) => {
        emit('segment-drag-end', props.segment, props.shelf);
    };

    return {
        onDragStart,
        onDragEnd,
        handleDragEnter,
        handleDragOver,
        handleDragLeave,
        handleDrop
    };
}
