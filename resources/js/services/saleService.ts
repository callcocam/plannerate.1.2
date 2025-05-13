import apiService from './api';


export const useSaleService = () => {
    const getSales = async (products: any[]) => {
        const response = await apiService.get(`/sales`, {
            params: {
                 products
            }
        });
        return response;
    }

    return {
        getSales
    }
}   
