import { Check, ChevronsUpDown, X } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { useTranslation } from '@/hooks/use-translation';
import type { InstrumentSoort } from '@/types/admin';

type Props = {
    instrumentSoorten: InstrumentSoort[];
    selectedIds: number[];
    onChange: (ids: number[]) => void;
};

export function InstrumentMultiSelect({ instrumentSoorten, selectedIds, onChange }: Props) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);

    const grouped = instrumentSoorten.reduce<Record<string, InstrumentSoort[]>>((acc, is) => {
        const familieNaam = is.instrument_familie?.naam ?? '';
        (acc[familieNaam] ??= []).push(is);
        return acc;
    }, {});

    const selectedNames = selectedIds
        .map(id => instrumentSoorten.find(is => is.id === id)?.naam)
        .filter(Boolean);

    const toggle = (id: number) => {
        onChange(
            selectedIds.includes(id)
                ? selectedIds.filter(v => v !== id)
                : [...selectedIds, id]
        );
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button variant="outline" role="combobox" aria-expanded={open} className="w-full justify-between h-auto min-h-9 font-normal">
                    {selectedNames.length > 0 ? (
                        <div className="flex flex-wrap gap-1">
                            {selectedNames.map((name) => (
                                <Badge key={name} variant="secondary" className="text-xs">
                                    {name}
                                    <button
                                        type="button"
                                        className="ml-1 rounded-full outline-none"
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            const id = instrumentSoorten.find(is => is.naam === name)?.id;
                                            if (id) toggle(id);
                                        }}
                                    >
                                        <X className="h-3 w-3" />
                                    </button>
                                </Badge>
                            ))}
                        </div>
                    ) : (
                        <span className="text-muted-foreground">{t('Select instruments')}</span>
                    )}
                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-[--radix-popover-trigger-width] p-0" align="start">
                <Command>
                    <CommandInput placeholder={t('Search...')} />
                    <CommandList>
                        <CommandEmpty>{t('No results.')}</CommandEmpty>
                        {Object.entries(grouped).map(([familie, items]) => (
                            <CommandGroup key={familie} heading={familie}>
                                {items.map((is) => (
                                    <CommandItem key={is.id} value={is.naam} onSelect={() => toggle(is.id)}>
                                        <Check className={cn("mr-2 h-4 w-4", selectedIds.includes(is.id) ? "opacity-100" : "opacity-0")} />
                                        {is.naam}
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        ))}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
