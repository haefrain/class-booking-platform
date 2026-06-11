// Hand-written mirrors of the API resources (no transformer — drift is
// caught by AssertableInertia shape assertions shipped with each page).

export type ClassTypeSummary = {
    id: number;
    name: string;
    slug: string;
    duration_minutes: number;
    price_cents: number;
    is_free: boolean;
};

export type InstructorSummary = {
    id: number;
    name: string;
};

export type SessionSummary = {
    id: number;
    starts_at: string; // ISO UTC
    ends_at: string;
    capacity: number;
    spots_left: number;
    status: 'scheduled' | 'cancelled';
    class_type: ClassTypeSummary | null;
    instructor: InstructorSummary | null;
};

export type WeekNav = {
    start: string;
    prev: string;
    next: string;
};

export type ViewerCta = 'login' | 'closed';
