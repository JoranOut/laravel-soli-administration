import { usePage } from '@inertiajs/react';
import en from '../../../lang/en.json';
import nl from '../../../lang/nl.json';

const translationsByLocale: Record<string, Record<string, string>> = { en, nl };

export function useTranslation() {
    const { locale } = usePage().props;
    const translations = translationsByLocale[locale] ?? {};

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
