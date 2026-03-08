import { useTranslation } from '@/hooks/use-translation';

type DateRangeDisplayProps = {
    van: string;
    tot: string | null;
};

export function DateRangeDisplay({ van, tot }: DateRangeDisplayProps) {
    const { t } = useTranslation();

    const formatDate = (date: string) => {
        return new Date(date).toLocaleDateString('nl-NL', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    };

    return (
        <span className="text-muted-foreground text-sm">
            {formatDate(van)} – {tot ? formatDate(tot) : t('present')}
        </span>
    );
}
