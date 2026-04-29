import type { User } from './auth';

export type Relatie = {
    id: number;
    relatie_nummer: number;
    user_id: number | null;
    voornaam: string;
    tussenvoegsel: string | null;
    achternaam: string;
    volledige_naam: string;
    geslacht: 'M' | 'V' | 'O';
    geboortedatum: string | null;
    actief: boolean;
    beheerd_in_admin: boolean;
    foto_url: string | null;
    geboorteplaats: string | null;
    nationaliteit: string | null;
    created_at: string;
    updated_at: string;
    user?: User;
    types?: RelatieType[];
    adressen?: Adres[];
    emails?: EmailRecord[];
    telefoons?: Telefoon[];
    giro_gegevens?: GiroGegeven[];
    relatie_sinds?: RelatieSinds[];
    onderdelen?: Onderdeel[];
    opleidingen?: Opleiding[];
    uniformen?: Uniform[];
    insignes?: Insigne[];
    diplomas?: Diploma[];
    andere_verenigingen?: AndereVereniging[];
    relatie_instrumenten?: RelatieInstrument[];
    instrument_bespelers?: InstrumentBespeler[];
};

export type RelatieType = {
    id: number;
    naam: string;
    onderdeel_koppelbaar?: boolean;
    pivot?: {
        id: number;
        van: string;
        tot: string | null;
        functie: string | null;
        email: string | null;
        onderdeel_id: number | null;
    };
};

export type Adres = {
    id: number;
    relatie_id: number;
    straat: string;
    huisnummer: string;
    huisnummer_toevoeging: string | null;
    postcode: string;
    plaats: string;
    land: string;
    volledig_adres: string;
};

export type EmailRecord = {
    id: number;
    relatie_id: number;
    email: string;
};

export type Telefoon = {
    id: number;
    relatie_id: number;
    nummer: string;
};

export type GiroGegeven = {
    id: number;
    relatie_id: number;
    iban: string;
    bic: string | null;
    tenaamstelling: string;
    machtiging: boolean;
};

export type RelatieSinds = {
    id: number;
    relatie_id: number;
    lid_sinds: string;
    lid_tot: string | null;
    reden_vertrek: string | null;
};

export type Onderdeel = {
    id: number;
    naam: string;
    afkorting: string | null;
    type: 'muziekgroep' | 'commissie' | 'bestuur' | 'staff' | 'overig';
    beschrijving: string | null;
    actief: boolean;
    created_at: string;
    updated_at: string;
    pivot?: {
        id: number;
        functie: string | null;
        van: string;
        tot: string | null;
    };
    actieve_relaties_count?: number;
};

export type Instrument = {
    id: number;
    nummer: string;
    soort: string;
    merk: string | null;
    model: string | null;
    serienummer: string | null;
    status: 'beschikbaar' | 'in_gebruik' | 'in_reparatie' | 'afgeschreven';
    eigendom: 'soli' | 'bruikleen' | 'eigen';
    aanschafjaar: number | null;
    prijs: string | null;
    locatie: string | null;
    created_at: string;
    updated_at: string;
    huidige_bespeler?: InstrumentBespeler | null;
    bespelers?: InstrumentBespeler[];
    bijzonderheden?: InstrumentBijzonderheid[];
    reparaties?: InstrumentReparatie[];
};

export type InstrumentFamilie = {
    id: number;
    naam: string;
};

export type InstrumentSoort = {
    id: number;
    naam: string;
    instrument_familie_id: number;
    instrument_familie?: InstrumentFamilie;
};

export type RelatieInstrument = {
    id: number;
    relatie_id: number;
    onderdeel_id: number;
    instrument_soort_id: number;
    instrument_soort?: InstrumentSoort;
    onderdeel?: Onderdeel;
};

export type InstrumentBespeler = {
    id: number;
    instrument_id: number;
    relatie_id: number;
    van: string;
    tot: string | null;
    is_actueel: boolean;
    relatie?: Relatie;
    instrument?: Instrument;
};

export type InstrumentBijzonderheid = {
    id: number;
    instrument_id: number;
    beschrijving: string;
    datum: string;
};

export type InstrumentReparatie = {
    id: number;
    instrument_id: number;
    beschrijving: string;
    reparateur: string | null;
    kosten: string | null;
    datum_in: string;
    datum_uit: string | null;
};

export type Opleiding = {
    id: number;
    relatie_id: number;
    naam: string;
    instituut: string | null;
    instrument: string | null;
    diploma: string | null;
    datum_start: string | null;
    datum_einde: string | null;
    opmerking: string | null;
};

export type Uniform = {
    id: number;
    relatie_id: number;
    type: string;
    maat: string | null;
    nummer: string | null;
    van: string;
    tot: string | null;
    is_actueel: boolean;
};

export type Insigne = {
    id: number;
    relatie_id: number;
    naam: string;
    datum: string;
};

export type Diploma = {
    id: number;
    relatie_id: number;
    naam: string;
    instrument: string | null;
};

export type AndereVereniging = {
    id: number;
    relatie_id: number;
    naam: string;
    functie: string | null;
    van: string;
    tot: string | null;
    is_actueel: boolean;
};

export type OauthClient = {
    id: string;
    name: string;
    redirect_uris: string[];
    setting: OauthClientSetting | null;
};

export type OauthClientSetting = {
    id: number;
    type: string;
    default_role: string | null;
    skip_authorization: boolean;
    role_mappings: ClientRoleMapping[];
    user_roles: UserRoleOverride[];
};

export type ClientRoleMapping = {
    id: number;
    relatie_type_id: number;
    relatie_type_naam: string;
    mapped_role: string;
    priority: number;
};

export type UserRoleOverride = {
    id: number;
    user_id: number;
    user_name: string;
    mapped_role: string;
};

export type GoogleContactSyncLog = {
    id: number;
    type: 'full' | 'relatie';
    relatie_id: number | null;
    relatie?: { id: number; voornaam: string; tussenvoegsel: string | null; achternaam: string } | null;
    status: 'running' | 'completed' | 'failed';
    workspace_users: number;
    contacts_created: number;
    contacts_updated: number;
    contacts_deleted: number;
    contacts_skipped: number;
    error_message: string | null;
    started_at: string;
    completed_at: string | null;
};

export type PaginatedResponse<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type DashboardStats = {
    actieve_leden: number;
    donateurs: number;
    instrumenten_in_gebruik: number;
    openstaande_reparaties: number;
    leden_joined_12m: number;
    leden_left_12m: number;
};

export type OnderdeelHistoryEntry = {
    month: string;
    [onderdeel: string]: string | number;
};

export type DashboardAlerts = {
    unlinked_users: number;
    unlinked_relaties: number;
};

export type ResidenceStats = {
    top: { plaats: string; count: number }[];
    inside_velsen: number;
    outside_velsen: number;
};

export type InstrumentStat = {
    naam: string;
    total: number;
    over_60: number;
};

export type AgeDistribution = {
    brackets: { bracket: string; count: number }[];
    average_age: number | null;
};

// Wizard form data types for relatie creation
export type RelatieTypeEntry = {
    type_id: string;
    van: string;
    tot: string;
    functie: string;
    email: string;
    onderdeel_id: string;
};

export type AdresEntry = {
    straat: string;
    huisnummer: string;
    huisnummer_toevoeging: string;
    postcode: string;
    plaats: string;
    land: string;
};

export type EmailEntry = {
    email: string;
};

export type TelefoonEntry = {
    nummer: string;
};

export type GiroGegevenEntry = {
    iban: string;
    bic: string;
    tenaamstelling: string;
    machtiging: boolean;
};

export type LidmaatschapEntry = {
    lid_sinds: string;
    lid_tot: string;
    reden_vertrek: string;
};

export type OnderdeelEntry = {
    onderdeel_id: string;
    functie: string;
    instrument_soort_ids: number[];
    van: string;
    tot: string;
};

export type OpleidingEntry = {
    naam: string;
    instituut: string;
    instrument: string;
    diploma: string;
    datum_start: string;
    datum_einde: string;
    opmerking: string;
};

export type RelatieCreateFormData = {
    relatie_nummer: number;
    voornaam: string;
    tussenvoegsel: string;
    achternaam: string;
    geslacht: 'M' | 'V' | 'O';
    geboortedatum: string;
    geboorteplaats: string;
    nationaliteit: string;
    types: RelatieTypeEntry[];
    adressen: AdresEntry[];
    emails: EmailEntry[];
    telefoons: TelefoonEntry[];
    giro_gegevens: GiroGegevenEntry[];
    lidmaatschappen: LidmaatschapEntry[];
    onderdelen: OnderdeelEntry[];
    opleidingen: OpleidingEntry[];
};
