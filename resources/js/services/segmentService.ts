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

    return {
        addSegment,
        copySegment,
        updateSegment,
        deleteSegment,
    };
};