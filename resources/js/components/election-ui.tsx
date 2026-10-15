import { Link, usePage, usePoll } from '@inertiajs/react';
import { useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import type { Pagination, Round, Snapshot } from '@/types/voting';

export function dateTime(value: string | null) {
    return value
        ? new Intl.DateTimeFormat('pt-BR', {
              timeZone: 'America/Sao_Paulo',
              dateStyle: 'short',
              timeStyle: 'short',
          }).format(new Date(value))
        : '—';
}
export function useElectionPolling(snapshot: Snapshot, receipt = false) {
    const current = snapshot.election?.rounds.at(-1);
    const enabled =
        current?.status === 'aberto' || current?.status === 'agendado';
    const { start, stop } = usePoll(
        5000,
        { only: receipt ? ['snapshot', 'ownVote'] : ['snapshot'] },
        { autoStart: false },
    );
    useEffect(() => {
        if (enabled) {
            start();
        } else {
            stop();
        }
        return stop;
    }, [enabled, start, stop]);
}
export function InstanceFooter() {
    const { instance } = usePage().props;
    return (
        <footer className="border-t px-4 py-4 text-center text-xs text-muted-foreground">
            <span className="inline-flex items-center gap-2 rounded-md border px-3 py-1">
                <span className="size-1.5 rounded-full bg-emerald-700" />
                Servidor ativo: {instance ?? 'Instância A'}
            </span>
        </footer>
    );
}
export function Feedback() {
    const { errors, flash } = usePage().props;
    const message = (flash as { success?: string } | undefined)?.success;
    return (
        <>
            {message && (
                <div
                    role="status"
                    className="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-900"
                >
                    {message}
                </div>
            )}
            {Object.keys(errors).length > 0 && (
                <div
                    role="alert"
                    className="rounded-lg bg-red-50 p-4 text-sm text-red-900"
                >
                    <p className="font-semibold">
                        Confira os dados antes de continuar.
                    </p>
                    <ul className="mt-2 list-inside list-disc">
                        {[...new Set(Object.values(errors).map(String))].map(
                            (error) => (
                                <li key={error}>{error}</li>
                            ),
                        )}
                    </ul>
                </div>
            )}
        </>
    );
}
export function StatusBadge({ round }: { round: Round }) {
    return (
        <Badge
            variant={round.status === 'aberto' ? 'default' : 'secondary'}
            className={
                round.status === 'aberto'
                    ? 'gap-2 bg-emerald-800 text-white'
                    : ''
            }
        >
            {round.status === 'aberto' && (
                <span className="size-1.5 rounded-full bg-white motion-safe:animate-pulse" />
            )}
            {round.status.toLocaleUpperCase('pt-BR')}
        </Badge>
    );
}
export function outcomeText(round: Round) {
    if (round.status === 'agendado') {
        return (
            'Abertura prevista: ' + dateTime(round.scheduled_at) + ' (Brasília)'
        );
    }
    if (round.status === 'aberto') {
        return 'Votação aberta. Placar atualizado a cada 5 segundos.';
    }
    if (round.outcome === 'falha') {
        return 'Votação falha — sem votos. Eleição finalizada sem vencedor.';
    }
    if (round.outcome === 'empate') {
        return 'Empate na liderança. Aguardando agendamento de novo turno.';
    }
    return (
        'Eleito: ' +
        round.candidates.find((candidate) =>
            round.leaders.includes(candidate.id),
        )?.name
    );
}
export function RoundResults({ round }: { round: Round }) {
    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-3">
                <h3 className="font-semibold">Turno {round.number}</h3>
                <StatusBadge round={round} />
                <span className="text-sm text-muted-foreground">
                    {round.total} votos
                </span>
            </div>
            {round.simulated_total > 0 && (
                <p className="rounded-lg bg-amber-50 p-3 text-sm text-amber-950">
                    Demonstração: inclui {round.simulated_total} votos
                    simulados.
                </p>
            )}
            <p className="text-sm">{outcomeText(round)}</p>
            <div className="divide-y">
                {round.candidates.map((candidate) => (
                    <div
                        key={candidate.id}
                        className="flex flex-wrap justify-between gap-2 py-2 text-sm"
                    >
                        <span>
                            {candidate.name}
                            {round.leaders.includes(candidate.id) &&
                                round.total > 0 && (
                                    <span className="ml-2 font-semibold text-emerald-800">
                                        {round.status === 'aberto'
                                            ? 'Liderando'
                                            : round.outcome === 'empate'
                                              ? 'Empatado'
                                              : 'Eleito'}
                                    </span>
                                )}
                        </span>
                        <span className="tabular-nums">
                            {candidate.votes} votos ·{' '}
                            {candidate.percentage.toLocaleString('pt-BR')}%
                        </span>
                    </div>
                ))}
            </div>
            {round.selection_justification && (
                <p className="text-sm text-muted-foreground">
                    Justificativa da seleção: {round.selection_justification}
                </p>
            )}
            <p className="text-xs text-muted-foreground">
                Abertura: {dateTime(round.opened_at)} · Encerramento:{' '}
                {dateTime(round.closed_at)}
            </p>
        </div>
    );
}
export function PaginationLinks({
    pagination,
}: {
    pagination: Pick<
        Pagination<unknown>,
        'current_page' | 'last_page' | 'links'
    >;
}) {
    if (pagination.last_page <= 1) {
        return null;
    }
    return (
        <nav
            aria-label="Paginação"
            className="flex flex-wrap items-center gap-2 py-4"
        >
            {pagination.links.map((link, index) =>
                link.url ? (
                    <Link
                        key={index}
                        href={link.url}
                        preserveScroll
                        className={
                            'rounded-md border px-3 py-2 text-sm ' +
                            (link.active
                                ? 'bg-primary text-primary-foreground'
                                : 'hover:bg-muted')
                        }
                        aria-current={link.active ? 'page' : undefined}
                    >
                        {index === 0
                            ? 'Anterior'
                            : index === pagination.links.length - 1
                              ? 'Próxima'
                              : link.label}
                    </Link>
                ) : null,
            )}
        </nav>
    );
}
