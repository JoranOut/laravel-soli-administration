import { Head } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';

type ContactPerson = {
    id: number;
    volledige_naam: string;
    functie: string | null;
    email: string | null;
};

type Props = {
    bestuur: ContactPerson[];
    contactpersonen: ContactPerson[];
};

export default function Contact({ bestuur, contactpersonen }: Props) {
    const { t } = useTranslation();

    return (
        <AppLayout>
            <Head title={t('Contact')} />
            <div className="space-y-6 p-4">
                <h2 className="text-2xl font-bold">{t('Contact')}</h2>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Mail className="h-4 w-4" />
                            {t('Membership administration')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-muted-foreground mb-2">
                            {t('For questions about your membership, contributions, or personal data, please contact the membership administration.')}
                        </p>
                        <a href="mailto:ledenadministratie@soli.nl" className="font-medium underline">
                            ledenadministratie@soli.nl
                        </a>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Board members')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {bestuur.length > 0 ? (
                            <div className="space-y-3">
                                {bestuur.map((member) => (
                                    <div key={member.id} className="flex items-center justify-between rounded-md border p-3">
                                        <div>
                                            <p className="font-medium">
                                                {member.functie && <>{member.functie} — </>}
                                                <span className="text-muted-foreground text-sm">{member.volledige_naam}</span>
                                            </p>
                                            {member.email && (
                                                <a href={`mailto:${member.email}`} className="text-muted-foreground text-sm underline">
                                                    {member.email}
                                                </a>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-muted-foreground text-sm">{t('No board members found.')}</p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Contact persons')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {contactpersonen.length > 0 ? (
                            <div className="space-y-3">
                                {contactpersonen.map((person) => (
                                    <div key={person.id} className="flex items-center justify-between rounded-md border p-3">
                                        <div>
                                            <p className="font-medium">
                                                {person.functie && <>{person.functie} — </>}
                                                <span className="text-muted-foreground text-sm">{person.volledige_naam}</span>
                                            </p>
                                            {person.email && (
                                                <a href={`mailto:${person.email}`} className="text-muted-foreground text-sm underline">
                                                    {person.email}
                                                </a>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-muted-foreground text-sm">{t('No contact persons found.')}</p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
