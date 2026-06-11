import { BookOpen, CalendarDays, LayoutGrid } from '@lucide/vue';
import { catalog } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { sessions as instructorSessions } from '@/routes/instructor';
import type { NavItem } from '@/types';

export type Role = 'admin' | 'instructor' | 'student';

/**
 * Pure mapping from role to main navigation. The server is the authority on
 * authorization — this only decides what is worth showing.
 */
export function navFor(role: Role): NavItem[] {
    switch (role) {
        case 'admin':
            return [
                {
                    title: 'Dashboard',
                    href: adminDashboard(),
                    icon: LayoutGrid,
                },
                { title: 'Payments', href: '/admin/payments', icon: BookOpen },
            ];
        case 'instructor':
            return [
                {
                    title: 'My sessions',
                    href: instructorSessions(),
                    icon: CalendarDays,
                },
            ];
        case 'student':
            return [{ title: 'Catalog', href: catalog(), icon: BookOpen }];
    }
}
