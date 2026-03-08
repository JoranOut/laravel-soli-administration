import { usePage } from '@inertiajs/react';

export function useTranslation() {
    const { locale, translations } = usePage().props;

    function t(key: string, replacements?: Record<string, string | number>): string {
        let value = translations[key] ?? key;

        if (replacements) {
            Object.entries(replacements).forEach(([placeholder, replacement]) => {
                value = value.replace(`:${placeholder}`, String(replacement));
            });
        }

        return value;
    }

    return { t, locale };
}
