export type ResolvedAppearance = 'light' | 'dark';
export type Appearance = ResolvedAppearance | 'system';
export function initializeTheme(): void {
    if (typeof document === 'undefined') {
        return;
    }
    document.documentElement.classList.remove('dark');
    document.documentElement.style.colorScheme = 'light';
    localStorage.setItem('appearance', 'light');
    document.cookie = 'appearance=light;path=/;max-age=31536000;SameSite=Lax';
}
export function useAppearance(): {
    appearance: Appearance;
    resolvedAppearance: ResolvedAppearance;
    updateAppearance: (mode: Appearance) => void;
} {
    return {
        appearance: 'light' as const,
        resolvedAppearance: 'light' as const,
        updateAppearance: (_mode: Appearance) => initializeTheme(),
    };
}
