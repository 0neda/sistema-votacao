import type { ReactNode } from 'react';

export function AppShell({ children }: { children: ReactNode }) {
    return <div className="flex min-h-screen w-full flex-col">{children}</div>;
}
