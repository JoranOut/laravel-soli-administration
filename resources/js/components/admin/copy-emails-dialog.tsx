import { Check, Copy } from 'lucide-react';
import { useMemo, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useClipboard } from '@/hooks/use-clipboard';
import { useTranslation } from '@/hooks/use-translation';
import type { EmailRecord } from '@/types/admin';

type RelatieWithEmail = {
    volledige_naam: string;
    emails?: EmailRecord[];
    types?: Array<{ id: number; naam: string }>;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    relaties: RelatieWithEmail[];
};

export function CopyEmailsDialog({ open, onOpenChange, relaties }: Props) {
    const { t } = useTranslation();
    const [excludeDirigent, setExcludeDirigent] = useState(false);
    const [copiedText, copy] = useClipboard();

    const emailString = useMemo(() => {
        const filtered = excludeDirigent
            ? relaties.filter((r) => !r.types?.some((t) => t.naam.toLowerCase() === 'dirigent'))
            : relaties;
        return filtered
            .map((r) => {
                const email = r.emails?.[0]?.email;
                return email ? `${r.volledige_naam} <${email}>` : null;
            })
            .filter(Boolean)
            .join('; ');
    }, [relaties, excludeDirigent]);

    const handleCopy = () => {
        if (emailString) {
            copy(emailString);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{t('Copy emails')}</DialogTitle>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="exclude-dirigent"
                            checked={excludeDirigent}
                            onCheckedChange={(checked) => setExcludeDirigent(!!checked)}
                        />
                        <label htmlFor="exclude-dirigent" className="text-sm">
                            {t('Exclude dirigent')}
                        </label>
                    </div>

                    {emailString ? (
                        <pre className="bg-muted max-h-60 overflow-auto rounded-md border p-3 text-sm whitespace-pre-wrap break-all">
                            {emailString}
                        </pre>
                    ) : (
                        <p className="text-muted-foreground text-sm">{t('No emails found.')}</p>
                    )}
                </div>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">{t('Close')}</Button>
                    </DialogClose>
                    <Button onClick={handleCopy} disabled={!emailString}>
                        {copiedText ? <Check className="mr-2 h-4 w-4" /> : <Copy className="mr-2 h-4 w-4" />}
                        {copiedText ? t('Emails copied!') : t('Copy emails')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
