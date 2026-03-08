import { router } from '@inertiajs/react';
import { useTranslation } from '@/hooks/use-translation';

export function LocaleSwitcher() {
    const { locale } = useTranslation();

    function switchLocale(newLocale: string) {
        router.post(`/locale/${newLocale}`, {}, { preserveState: true });
    }

    return (
        <div className="flex items-center gap-1 px-2 text-xs">
            <button
                onClick={() => switchLocale('nl')}
                className={`rounded px-1.5 py-0.5 ${locale === 'nl' ? 'bg-primary text-primary-foreground font-medium' : 'text-muted-foreground hover:text-foreground'}`}
            >
                NL
            </button>
            <span className="text-muted-foreground">/</span>
            <button
                onClick={() => switchLocale('en')}
                className={`rounded px-1.5 py-0.5 ${locale === 'en' ? 'bg-primary text-primary-foreground font-medium' : 'text-muted-foreground hover:text-foreground'}`}
            >
                EN
            </button>
        </div>
    );
}
