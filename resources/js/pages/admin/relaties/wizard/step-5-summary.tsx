import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import type { Onderdeel, RelatieCreateFormData, RelatieType } from '@/types/admin';

type Props = {
    data: RelatieCreateFormData;
    relatieTypes: RelatieType[];
    onderdelen: Onderdeel[];
    onNavigateToStep: (step: number) => void;
};

function SectionHeading({ children, step, onNavigateToStep }: { children: React.ReactNode; step: number; onNavigateToStep: (step: number) => void }) {
    return (
        <button type="button" onClick={() => onNavigateToStep(step)} className="text-primary hover:underline text-left">
            {children}
        </button>
    );
}

function EmptyNotice({ message }: { message: string }) {
    return <p className="text-muted-foreground text-sm italic">{message}</p>;
}

const formatDate = (date: string | null | undefined) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('nl-NL', { day: 'numeric', month: 'short', year: 'numeric' });
};

const genderLabel = (g: string) => {
    switch (g) {
        case 'M': return 'Man';
        case 'V': return 'Vrouw';
        default: return 'Anders';
    }
};

export default function Step5Summary({ data, relatieTypes, onderdelen, onNavigateToStep }: Props) {
    const { t } = useTranslation();

    const resolveTypeName = (typeId: string) => relatieTypes.find((rt) => rt.id.toString() === typeId)?.naam ?? typeId;
    const resolveOnderdeelName = (id: string) => onderdelen.find((o) => o.id.toString() === id)?.naam ?? id;

    return (
        <div className="space-y-6">
            {/* Personal info */}
            <Card>
                <CardHeader>
                    <CardTitle>
                        <SectionHeading step={1} onNavigateToStep={onNavigateToStep}>
                            {t('Personal information')}
                        </SectionHeading>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <dl className="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2">
                        <div>
                            <dt className="text-muted-foreground text-sm">{t('Relation number')}</dt>
                            <dd className="font-medium">{data.relatie_nummer}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground text-sm">{t('Name')}</dt>
                            <dd className="font-medium">
                                {[data.voornaam, data.tussenvoegsel, data.achternaam].filter(Boolean).join(' ')}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground text-sm">{t('Gender')}</dt>
                            <dd>{genderLabel(data.geslacht)}</dd>
                        </div>
                        {data.geboortedatum && (
                            <div>
                                <dt className="text-muted-foreground text-sm">{t('Date of birth')}</dt>
                                <dd>{formatDate(data.geboortedatum)}</dd>
                            </div>
                        )}
                        {data.geboorteplaats && (
                            <div>
                                <dt className="text-muted-foreground text-sm">{t('Place of birth')}</dt>
                                <dd>{data.geboorteplaats}</dd>
                            </div>
                        )}
                        {data.nationaliteit && (
                            <div>
                                <dt className="text-muted-foreground text-sm">{t('Nationality')}</dt>
                                <dd>{data.nationaliteit}</dd>
                            </div>
                        )}
                    </dl>

                    {data.types.length > 0 && (
                        <div className="mt-4">
                            <h4 className="text-muted-foreground mb-2 text-sm">{t('Types')}</h4>
                            <div className="flex flex-wrap gap-2">
                                {data.types.map((entry, i) => (
                                    <Badge key={i} variant="secondary">
                                        {resolveTypeName(entry.type_id)}
                                        {entry.functie && ` — ${entry.functie}`}
                                        {entry.email && ` (${entry.email})`}
                                        {' '}({formatDate(entry.van)})
                                    </Badge>
                                ))}
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Contact */}
            <Card>
                <CardHeader>
                    <CardTitle>
                        <SectionHeading step={2} onNavigateToStep={onNavigateToStep}>
                            {t('Contact')}
                        </SectionHeading>
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    {data.adressen.length === 0 && data.emails.length === 0 && data.telefoons.length === 0 && data.giro_gegevens.length === 0 ? (
                        <EmptyNotice message={t('No data entered')} />
                    ) : (
                        <>
                            {data.adressen.length > 0 && (
                                <div>
                                    <h4 className="text-muted-foreground mb-1 text-sm">{t('Addresses')}</h4>
                                    {data.adressen.map((a, i) => (
                                        <div key={i} className="rounded-md border p-2 mb-2">
                                            <p className="font-medium">{a.straat} {a.huisnummer}{a.huisnummer_toevoeging ? ` ${a.huisnummer_toevoeging}` : ''}</p>
                                            <p className="text-sm">{a.postcode} {a.plaats}</p>
                                            <p className="text-muted-foreground text-xs">{a.land}</p>
                                        </div>
                                    ))}
                                </div>
                            )}
                            {data.emails.length > 0 && (
                                <div>
                                    <h4 className="text-muted-foreground mb-1 text-sm">{t('Emails')}</h4>
                                    {data.emails.map((e, i) => (
                                        <div key={i} className="rounded-md border p-2 mb-2">
                                            <div className="flex items-center gap-2">
                                                <p className="font-medium">{e.email}</p>
                                                {i === 0 && <Badge variant="secondary">{t('Login email')}</Badge>}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                            {data.telefoons.length > 0 && (
                                <div>
                                    <h4 className="text-muted-foreground mb-1 text-sm">{t('Phones')}</h4>
                                    {data.telefoons.map((tel, i) => (
                                        <div key={i} className="rounded-md border p-2 mb-2">
                                            <p className="font-medium">{tel.nummer}</p>
                                        </div>
                                    ))}
                                </div>
                            )}
                            {data.giro_gegevens.length > 0 && (
                                <div>
                                    <h4 className="text-muted-foreground mb-1 text-sm">{t('Bank details')}</h4>
                                    {data.giro_gegevens.map((g, i) => (
                                        <div key={i} className="rounded-md border p-2 mb-2">
                                            <p className="font-medium">{g.iban}</p>
                                            <p className="text-sm">{g.tenaamstelling}</p>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </>
                    )}
                </CardContent>
            </Card>

            {/* Membership & Sections */}
            <Card>
                <CardHeader>
                    <CardTitle>
                        <SectionHeading step={3} onNavigateToStep={onNavigateToStep}>
                            {t('Membership & sections')}
                        </SectionHeading>
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    {data.lidmaatschappen.length === 0 && data.onderdelen.length === 0 ? (
                        <EmptyNotice message={t('No data entered')} />
                    ) : (
                        <>
                            {data.lidmaatschappen.length > 0 && (
                                <div>
                                    <h4 className="text-muted-foreground mb-1 text-sm">{t('Membership periods')}</h4>
                                    {data.lidmaatschappen.map((lid, i) => (
                                        <div key={i} className="rounded-md border p-2 mb-2">
                                            <p className="font-medium">{formatDate(lid.lid_sinds)} – {formatDate(lid.lid_tot)}</p>
                                            {lid.reden_vertrek && <p className="text-muted-foreground text-xs">{t('Reason')}: {lid.reden_vertrek}</p>}
                                        </div>
                                    ))}
                                </div>
                            )}
                            {data.onderdelen.length > 0 && (
                                <div>
                                    <h4 className="text-muted-foreground mb-1 text-sm">{t('Sections')}</h4>
                                    {data.onderdelen.map((o, i) => (
                                        <div key={i} className="rounded-md border p-2 mb-2">
                                            <p className="font-medium">{resolveOnderdeelName(o.onderdeel_id)}</p>
                                            <div className="flex items-center gap-2">
                                                {o.functie && <Badge variant="secondary">{o.functie}</Badge>}
                                                <span className="text-muted-foreground text-xs">{formatDate(o.van)} – {formatDate(o.tot)}</span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </>
                    )}
                </CardContent>
            </Card>

            {/* Education */}
            <Card>
                <CardHeader>
                    <CardTitle>
                        <SectionHeading step={4} onNavigateToStep={onNavigateToStep}>
                            {t('Training')}
                        </SectionHeading>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    {data.opleidingen.length === 0 ? (
                        <EmptyNotice message={t('No data entered')} />
                    ) : (
                        <div className="space-y-2">
                            {data.opleidingen.map((opl, i) => (
                                <div key={i} className="rounded-md border p-2">
                                    <p className="font-medium">{opl.naam}</p>
                                    <div className="flex flex-wrap items-center gap-2 text-sm">
                                        {opl.instituut && <span>{opl.instituut}</span>}
                                        {opl.instrument && <Badge variant="outline">{opl.instrument}</Badge>}
                                        {opl.diploma && <Badge variant="secondary">{opl.diploma}</Badge>}
                                    </div>
                                    <p className="text-muted-foreground text-xs">{formatDate(opl.datum_start)} – {formatDate(opl.datum_einde)}</p>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
