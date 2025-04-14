import { useRouter } from "vue-router";

export function useRedirect(router) { 

    const redirectToLogin = () => {
        router.push({ name: 'login' });
    }

    const redirectToHome = () => {
        router.push({ name: 'home' });
    }

    // Function to redirect to a specific route
    const redirectTo = (routeName: string) => {
        router.push({ name: routeName });
    }

    // Function to redirect to a specific route with parameters
    const redirectToWithParams = (routeName: string, params: Record<string, any>) => {
        router.push({ name: routeName, params });
    }

    // Function to redirect to a specific route with query parameters
    const redirectToWithQuery = (routeName: string, query: Record<string, any>) => {
        router.push({ name: routeName, query });
    }
    // Function to redirect to a specific route with both parameters and query
    const redirectToWithParamsAndQuery = (routeName: string, params: Record<string, any>, query: Record<string, any>) => {
        router.push({ name: routeName, params, query });
    }
    // Function to redirect to a specific route with a full URL
    const redirectToWithFullUrl = (url: string) => {
    }

    const redirectRemoveGondola = (gondola) => {
        
        router.push({
            name: 'plannerate.view',
            params: { id: gondola.planogram_id },
        });
    }

    return { redirectToLogin, redirectToHome, redirectRemoveGondola, redirectTo, redirectToWithParams, redirectToWithQuery, redirectToWithParamsAndQuery, redirectToWithFullUrl };
}