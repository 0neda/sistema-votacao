export type Candidate = {
    id: number;
    name: string;
    photo_url: string | null;
    votes: number;
    percentage: number;
};
export type Round = {
    id: number;
    number: number;
    status: 'agendado' | 'aberto' | 'encerrado';
    scheduled_at: string | null;
    opened_at: string | null;
    closed_at: string | null;
    selection_justification: string | null;
    total: number;
    simulated_total: number;
    outcome: 'pendente' | 'vencedor' | 'empate' | 'falha';
    leaders: number[];
    candidates: Candidate[];
};
export type Election = {
    id: number;
    title: string;
    completed_at: string | null;
    candidates: Pick<Candidate, 'id' | 'name'>[];
    rounds: Round[];
};
export type Snapshot = {
    election: Election | null;
    has_active_election: boolean;
    read_at: string;
};
export type Receipt = {
    round_id: number;
} | null;
export type Pagination<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
};
