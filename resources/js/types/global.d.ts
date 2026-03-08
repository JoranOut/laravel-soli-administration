import type { RelatieType } from '@/types/admin';
import type { Auth } from '@/types/auth';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            locale: string;
            translations: Record<string, string>;
            sidebarRelatieTypes: RelatieType[];
            [key: string]: unknown;
        };
    }
}
