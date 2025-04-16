// services/segmentService.ts 

import { Segment } from "../views/gondolas/sections/types";
import apiService from "./api";

export const useSegmentService = () => {
    const addSegment = (shelfId: string, segment: Segment) => {
        return apiService.post(`shelves/${shelfId}/segments`, {
            segment,
        });
    };

    const copySegment = (shelfId: string, segment: Segment) => {
        return apiService.post(`shelves/${shelfId}/segments/copy`, {
            segment,
        });
    };

    const updateSegment = (segmentId: string, data: Partial<Segment>) => {
        return apiService.patch(`segments/${segmentId}`, data);
    };

    const deleteSegment = (segmentId: string) => {
        return apiService.delete(`segments/${segmentId}`);
    };
    /**
        * Reordena os segmentos de uma prateleira
        */
    const reorderSegments = async (shelfId: string, ordering: any) => {
        return apiService.put(`segments/${shelfId}/reorder`, {
            ordering: ordering
        });
    };
    

    /**
     * Transfere um segmento de uma prateleira para outra
     */
    const transferSegment = async (segmentId: string, newShelfId: string, positionData?: any) => {
        return apiService.put(`segments/${segmentId}/transfer`, {
            shelf_id: newShelfId,
            ...positionData
        });
    };
    return {
        addSegment,
        copySegment,
        updateSegment,
        deleteSegment,
        reorderSegments,
        transferSegment,
    };
};