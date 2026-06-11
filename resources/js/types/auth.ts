export type Role = 'admin' | 'instructor' | 'student';

// Mirrors the skinny shared auth.user (HandleInertiaRequests): the server
// never shares the full model.
export type User = {
    id: number;
    name: string;
    email: string;
    role: Role;
    avatar?: string;
    email_verified_at?: string | null;
    two_factor_enabled?: boolean;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
