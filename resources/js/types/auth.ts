export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    relaties_count?: number;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type PermissionResource = 'relaties' | 'onderdelen' | 'instrumenten' | 'instrumentsoorten' | 'users';
export type PermissionAction = 'view' | 'create' | 'edit' | 'delete';
export type Permission = `${PermissionResource}.${PermissionAction}`;
export type Role = 'admin' | 'bestuur' | 'ledenadministratie' | 'muziekbeheer' | 'member';

export type Auth = {
    user: User;
    permissions: Permission[];
    roles: Role[];
    relatie_ids: number[];
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
