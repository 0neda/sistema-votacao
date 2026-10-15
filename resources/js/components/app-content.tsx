import type { ComponentProps } from 'react';

export function AppContent({ children, ...props }: ComponentProps<'main'>) {
    return (
        <main
            className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 rounded-xl"
            {...props}
        >
            {children}
        </main>
    );
}
