import { router, useForm } from '@inertiajs/react';
import { Check, ChevronsUpDown, Pencil, Plus, X } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { DateRangeDisplay } from '@/components/admin/date-range-display';
import { cn } from '@/lib/utils';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import type { InstrumentSoort, Onderdeel, Relatie, RelatieSinds } from '@/types/admin';

type Props = {
    relatie: Relatie;
    onderdelen: Onderdeel[];
    instrumentSoorten: InstrumentSoort[];
};

function InstrumentMultiSelect({ instrumentSoorten, selectedIds, onChange }: {
    instrumentSoorten: InstrumentSoort[];
    selectedIds: number[];
    onChange: (ids: number[]) => void;
}) {
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

function AddLidmaatschapDialog({ relatieId }: { relatieId: number }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        lid_sinds: new Date().toISOString().split('T')[0],
        lid_tot: '',
        reden_vertrek: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/admin/relaties/${relatieId}/lidmaatschap`, {
            onSuccess: () => { setOpen(false); reset(); },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm"><Plus className="mr-2 h-4 w-4" />{t('Membership')}</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader><DialogTitle>{t('Add membership')}</DialogTitle></DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label>{t('Member since')}</Label>
                            <Input type="date" value={data.lid_sinds} onChange={(e) => setData('lid_sinds', e.target.value)} required />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('Member until')}</Label>
                            <Input type="date" value={data.lid_tot} onChange={(e) => setData('lid_tot', e.target.value)} />
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>{t('Reason for departure')}</Label>
                        <Textarea value={data.reden_vertrek} onChange={(e) => setData('reden_vertrek', e.target.value)} rows={3} />
                    </div>
                    <Button type="submit" disabled={processing}>{t('Save')}</Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditLidmaatschapDialog({ relatieId, rs }: { relatieId: number; rs: RelatieSinds }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const { data, setData, put, processing } = useForm({
        lid_sinds: rs.lid_sinds,
        lid_tot: rs.lid_tot ?? '',
        reden_vertrek: rs.reden_vertrek ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/relaties/${relatieId}/lidmaatschap/${rs.id}`, {
            onSuccess: () => setOpen(false),
        });
    };

    const handleEnd = () => {
        router.put(`/admin/relaties/${relatieId}/lidmaatschap/${rs.id}`, {
            ...data,
            lid_tot: new Date().toISOString().split('T')[0],
        }, { onSuccess: () => setOpen(false) });
    };

    const handleDelete = () => {
        router.delete(`/admin/relaties/${relatieId}/lidmaatschap/${rs.id}`, {
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={(v) => { setOpen(v); if (!v) setConfirmDelete(false); }}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm"><Pencil className="h-4 w-4" /></Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader><DialogTitle>{t('Edit membership')}</DialogTitle></DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label>{t('Member since')}</Label>
                            <Input type="date" value={data.lid_sinds} onChange={(e) => setData('lid_sinds', e.target.value)} required />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('Member until')}</Label>
                            <Input type="date" value={data.lid_tot} onChange={(e) => setData('lid_tot', e.target.value)} />
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>{t('Reason for departure')}</Label>
                        <Textarea value={data.reden_vertrek} onChange={(e) => setData('reden_vertrek', e.target.value)} rows={3} />
                    </div>
                    <div className="flex items-center justify-between border-t pt-4">
                        <div>
                            {!confirmDelete ? (
                                <Button type="button" variant="link" size="sm" className="text-destructive p-0 h-auto" onClick={() => setConfirmDelete(true)}>
                                    {t('Delete permanently')}
                                </Button>
                            ) : (
                                <div className="flex items-center gap-2">
                                    <span className="text-destructive text-sm">{t('Are you sure?')}</span>
                                    <Button type="button" variant="destructive" size="sm" onClick={handleDelete}>{t('Delete')}</Button>
                                    <Button type="button" variant="ghost" size="sm" onClick={() => setConfirmDelete(false)}>{t('Cancel')}</Button>
                                </div>
                            )}
                        </div>
                        <div className="flex gap-2">
                            {!rs.lid_tot && (
                                <Button type="button" variant="secondary" onClick={handleEnd} disabled={processing}>
                                    {t('End')}
                                </Button>
                            )}
                            <Button type="submit" disabled={processing}>{t('Save')}</Button>
                        </div>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AddOnderdeelDialog({ relatieId, onderdelen, instrumentSoorten }: { relatieId: number; onderdelen: Onderdeel[]; instrumentSoorten: InstrumentSoort[] }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm<{
        onderdeel_id: string;
        functie: string;
        instrument_soort_ids: number[];
        van: string;
        tot: string;
    }>({
        onderdeel_id: '',
        functie: '',
        instrument_soort_ids: [],
        van: new Date().toISOString().split('T')[0],
        tot: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/admin/relaties/${relatieId}/onderdelen`, {
            onSuccess: () => { setOpen(false); reset(); },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline"><Plus className="mr-2 h-4 w-4" />{t('Section')}</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader><DialogTitle>{t('Add section')}</DialogTitle></DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>{t('Section')}</Label>
                        <Select value={data.onderdeel_id} onValueChange={(v) => setData('onderdeel_id', v)}>
                            <SelectTrigger><SelectValue placeholder={t('Select section')} /></SelectTrigger>
                            <SelectContent>
                                {onderdelen.map((o) => (
                                    <SelectItem key={o.id} value={o.id.toString()}>{o.naam}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>{t('Function')}</Label>
                        <Input value={data.functie} onChange={(e) => setData('functie', e.target.value)} placeholder={t('e.g. Conductor, Chairman')} />
                    </div>
                    <div className="space-y-2">
                        <Label>{t('Instrument type')}</Label>
                        <InstrumentMultiSelect
                            instrumentSoorten={instrumentSoorten}
                            selectedIds={data.instrument_soort_ids}
                            onChange={(ids) => setData('instrument_soort_ids', ids)}
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label>{t('From')}</Label>
                            <Input type="date" value={data.van} onChange={(e) => setData('van', e.target.value)} required />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('Until')}</Label>
                            <Input type="date" value={data.tot} onChange={(e) => setData('tot', e.target.value)} />
                        </div>
                    </div>
                    <Button type="submit" disabled={processing}>{t('Save')}</Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditOnderdeelDialog({ relatieId, onderdeel, relatie, instrumentSoorten }: { relatieId: number; onderdeel: Onderdeel; relatie: Relatie; instrumentSoorten: InstrumentSoort[] }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);

    const currentInstrumentIds = (relatie.relatie_instrumenten ?? [])
        .filter(ri => ri.onderdeel_id === onderdeel.id)
        .map(ri => ri.instrument_soort_id);

    const { data, setData, put, processing } = useForm<{
        functie: string;
        instrument_soort_ids: number[];
        van: string;
        tot: string;
    }>({
        functie: onderdeel.pivot?.functie ?? '',
        instrument_soort_ids: currentInstrumentIds,
        van: onderdeel.pivot?.van ?? '',
        tot: onderdeel.pivot?.tot ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/relaties/${relatieId}/onderdelen/${onderdeel.pivot!.id}`, {
            onSuccess: () => setOpen(false),
        });
    };

    const handleEnd = () => {
        router.put(`/admin/relaties/${relatieId}/onderdelen/${onderdeel.pivot!.id}`, {
            ...data,
            tot: new Date().toISOString().split('T')[0],
        }, { onSuccess: () => setOpen(false) });
    };

    const handleDelete = () => {
        router.delete(`/admin/relaties/${relatieId}/onderdelen/${onderdeel.pivot!.id}`, {
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={(v) => { setOpen(v); if (!v) setConfirmDelete(false); }}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm"><Pencil className="h-4 w-4" /></Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader><DialogTitle>{t('Edit section')}</DialogTitle></DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>{t('Section')}</Label>
                        <Input value={onderdeel.naam} disabled />
                    </div>
                    <div className="space-y-2">
                        <Label>{t('Function')}</Label>
                        <Input value={data.functie} onChange={(e) => setData('functie', e.target.value)} placeholder={t('e.g. Conductor, Chairman')} />
                    </div>
                    <div className="space-y-2">
                        <Label>{t('Instrument type')}</Label>
                        <InstrumentMultiSelect
                            instrumentSoorten={instrumentSoorten}
                            selectedIds={data.instrument_soort_ids}
                            onChange={(ids) => setData('instrument_soort_ids', ids)}
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label>{t('From')}</Label>
                            <Input type="date" value={data.van} onChange={(e) => setData('van', e.target.value)} required />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('Until')}</Label>
                            <Input type="date" value={data.tot} onChange={(e) => setData('tot', e.target.value)} />
                        </div>
                    </div>
                    <div className="flex items-center justify-between border-t pt-4">
                        <div>
                            {!confirmDelete ? (
                                <Button type="button" variant="link" size="sm" className="text-destructive p-0 h-auto" onClick={() => setConfirmDelete(true)}>
                                    {t('Delete permanently')}
                                </Button>
                            ) : (
                                <div className="flex items-center gap-2">
                                    <span className="text-destructive text-sm">{t('Are you sure?')}</span>
                                    <Button type="button" variant="destructive" size="sm" onClick={handleDelete}>{t('Delete')}</Button>
                                    <Button type="button" variant="ghost" size="sm" onClick={() => setConfirmDelete(false)}>{t('Cancel')}</Button>
                                </div>
                            )}
                        </div>
                        <div className="flex gap-2">
                            {!onderdeel.pivot?.tot && (
                                <Button type="button" variant="secondary" onClick={handleEnd} disabled={processing}>
                                    {t('End')}
                                </Button>
                            )}
                            <Button type="submit" disabled={processing}>{t('Save')}</Button>
                        </div>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function RelatieLidmaatschapTab({ relatie, onderdelen, instrumentSoorten }: Props) {
    const { can } = usePermissions();
    const { t } = useTranslation();

    const getInstrumentsForOnderdeel = (onderdeelId: number): string[] => {
        return (relatie.relatie_instrumenten ?? [])
            .filter(ri => ri.onderdeel_id === onderdeelId)
            .map(ri => ri.instrument_soort?.naam ?? '');
    };

    return (
        <div className="space-y-6">
            {can('relaties.edit') && (
                <div className="flex gap-2">
                    <AddLidmaatschapDialog relatieId={relatie.id} />
                    <AddOnderdeelDialog relatieId={relatie.id} onderdelen={onderdelen} instrumentSoorten={instrumentSoorten} />
                </div>
            )}

            <Card>
                <CardHeader><CardTitle>{t('Membership periods')}</CardTitle></CardHeader>
                <CardContent>
                    {relatie.relatie_sinds && relatie.relatie_sinds.length > 0 ? (
                        <div className="space-y-3">
                            {relatie.relatie_sinds.map((rs) => (
                                <div key={rs.id} className="flex items-center justify-between rounded-md border p-3">
                                    <div>
                                        <DateRangeDisplay van={rs.lid_sinds} tot={rs.lid_tot} />
                                        {rs.reden_vertrek && (
                                            <p className="text-muted-foreground mt-1 text-xs">{t('Reason')}: {rs.reden_vertrek}</p>
                                        )}
                                    </div>
                                    {can('relaties.edit') && (
                                        <EditLidmaatschapDialog relatieId={relatie.id} rs={rs} />
                                    )}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-muted-foreground text-sm">{t('No membership periods.')}</p>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>{t('Sections')}</CardTitle></CardHeader>
                <CardContent>
                    {relatie.onderdelen && relatie.onderdelen.length > 0 ? (
                        <div className="space-y-3">
                            {relatie.onderdelen.map((onderdeel) => {
                                const instruments = getInstrumentsForOnderdeel(onderdeel.id);
                                return (
                                    <div key={`${onderdeel.id}-${onderdeel.pivot?.van}`} className="flex items-center justify-between rounded-md border p-3">
                                        <div>
                                            <p className="font-medium">{onderdeel.naam}</p>
                                            <div className="flex items-center gap-2">
                                                <Badge variant="outline">{onderdeel.type}</Badge>
                                                {onderdeel.pivot?.functie && <Badge variant="secondary">{onderdeel.pivot.functie}</Badge>}
                                                {instruments.map((soort) => (
                                                    <Badge key={soort}>{soort}</Badge>
                                                ))}
                                                {onderdeel.pivot && <DateRangeDisplay van={onderdeel.pivot.van} tot={onderdeel.pivot.tot} />}
                                            </div>
                                        </div>
                                        {can('relaties.edit') && onderdeel.pivot && (
                                            <EditOnderdeelDialog relatieId={relatie.id} onderdeel={onderdeel} relatie={relatie} instrumentSoorten={instrumentSoorten} />
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <p className="text-muted-foreground text-sm">{t('No sections.')}</p>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
