import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            instance: string;
            flash: { success: string | null };
            auth: Auth;
            [key: string]: unknown;
        };
    }
}
