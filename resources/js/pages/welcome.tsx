import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Clock3, Vote } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    dateTime,
    Feedback,
    outcomeText,
    PaginationLinks,
    RoundResults,
    StatusBadge,
    useElectionPolling,
} from '@/components/election-ui';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { login } from '@/routes';
import { store as vote } from '@/routes/votes';
import type {
    Candidate,
    Election,
    Pagination,
    Receipt,
    Snapshot,
} from '@/types/voting';

export default function Welcome({
    snapshot,
    history,
    ownVote,
}: {
    snapshot: Snapshot;
    history: Pagination<Election>;
    ownVote: Receipt;
}) {
    useElectionPolling(snapshot, true);
    const wasActive = useRef(snapshot.has_active_election);
    useEffect(() => {
        if (wasActive.current && !snapshot.has_active_election) {
            router.reload({ only: ['history'] });
        }
        wasActive.current = snapshot.has_active_election;
    }, [snapshot.has_active_election]);
    const { auth } = usePage().props;
    const election = snapshot.election;
    const round = election?.rounds.at(-1);
    const [selected, setSelected] = useState<Candidate | null>(null);
    const form = useForm({ candidate_id: 0, confirmed: false });
    const receipt = ownVote?.round_id === round?.id ? ownVote : null;
    const canVote =
        auth.user?.is_active && round?.status === 'aberto' && !receipt;
    function select(candidate: Candidate) {
        form.setData({ candidate_id: candidate.id, confirmed: false });
        form.clearErrors();
        setSelected(candidate);
    }
    function confirm() {
        if (!round) {
            return;
        }
        form.post(vote.url(round.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setSelected(null);
            },
            onError: () => setSelected(null),
        });
    }
    return (
        <>
            <Head title="Central da eleição" />
            <div className="space-y-8 px-4 py-8 md:px-8 md:py-12">
                <Feedback />
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-semibold tracking-tight md:text-4xl">
                            Central da eleição
                        </h1>
                        <p className="mt-2 max-w-2xl text-muted-foreground">
                            Escolha quem vai representar o grupo. Acompanhe cada
                            voto e o resultado de cada turno.
                        </p>
                    </div>
                    <a
                        href="#historico"
                        className="text-sm font-medium underline underline-offset-4"
                    >
                        Consultar histórico
                    </a>
                </div>
                {!snapshot.has_active_election && (
                    <div
                        role="status"
                        className="rounded-lg border bg-muted/40 p-4 text-sm"
                    >
                        Nenhuma eleição em andamento.
                        {election &&
                            ' Confira abaixo o resultado da última eleição.'}
                    </div>
                )}
                {auth.user && !auth.user.is_active && (
                    <p
                        role="status"
                        className="rounded-lg bg-amber-50 p-4 text-sm text-amber-950"
                    >
                        Sua conta está inativa. Você pode acompanhar os
                        resultados públicos, mas não pode votar.
                    </p>
                )}
                {election && round ? (
                    <>
                        <section
                            className="rounded-xl bg-emerald-950 p-6 text-white md:p-8"
                            aria-labelledby="eleicao-titulo"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-4">
                                <div className="flex flex-wrap items-center gap-3">
                                    <span className="text-sm text-emerald-100">
                                        Turno {round.number}
                                    </span>
                                    <StatusBadge round={round} />
                                </div>
                                <span className="text-sm text-emerald-100 tabular-nums">
                                    {round.total}{' '}
                                    {round.total === 1
                                        ? 'voto registrado'
                                        : 'votos registrados'}
                                </span>
                            </div>
                            <h2
                                id="eleicao-titulo"
                                className="mt-6 text-2xl font-semibold tracking-tight md:text-3xl"
                            >
                                {election.title}
                            </h2>
                            <p className="mt-6 font-medium">
                                {outcomeText(round)}
                            </p>
                            <p className="mt-3 flex items-center gap-2 text-xs text-emerald-100">
                                <Clock3 className="size-3.5" />
                                Última leitura: {dateTime(snapshot.read_at)} ·
                                Horário de Brasília
                            </p>
                        </section>
                        {round.simulated_total > 0 && (
                            <p
                                role="status"
                                className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950"
                            >
                                Ambiente de demonstração: este placar inclui{' '}
                                {round.simulated_total} votos simulados.
                            </p>
                        )}
                        {receipt && (
                            <p
                                role="status"
                                className="rounded-lg border border-emerald-700 bg-emerald-50 p-4 text-sm text-emerald-950"
                            >
                                Sua participação neste turno está registrada. A
                                escolha do candidato não fica vinculada à sua
                                conta.
                            </p>
                        )}
                        {auth.user &&
                            !auth.user.is_active &&
                            round.status === 'aberto' && (
                                <p
                                    role="status"
                                    className="rounded-lg border bg-muted p-4 text-sm text-muted-foreground"
                                >
                                    Sua conta está inativa. Solicite a ativação
                                    a um administrador para votar.
                                </p>
                            )}
                        <section
                            aria-label="Candidatos e placar"
                            className="grid gap-5 md:grid-cols-3"
                        >
                            {round.candidates.map((candidate) => {
                                const leader =
                                    round.total > 0 &&
                                    round.leaders.includes(candidate.id);
                                return (
                                    <Card
                                        key={candidate.id}
                                        className="rounded-xl shadow-none"
                                    >
                                        <CardContent className="flex h-full flex-col gap-5">
                                            {candidate.photo_url && (
                                                <img
                                                    src={candidate.photo_url}
                                                    alt={
                                                        'Foto de ' +
                                                        candidate.name
                                                    }
                                                    className="aspect-4/3 w-full rounded-lg object-cover"
                                                    loading="lazy"
                                                />
                                            )}
                                            {round.total > 0 && (
                                                <div className="flex min-h-6 flex-wrap items-center gap-2">
                                                    {leader && (
                                                        <Badge
                                                            variant="secondary"
                                                            className="bg-emerald-50 text-emerald-900"
                                                        >
                                                            {round.status ===
                                                            'aberto'
                                                                ? 'Liderando'
                                                                : round.outcome ===
                                                                    'empate'
                                                                  ? 'Empatado'
                                                                  : 'Eleito'}
                                                        </Badge>
                                                    )}
                                                </div>
                                            )}
                                            <div>
                                                <h3 className="text-xl font-semibold">
                                                    {candidate.name}
                                                </h3>
                                            </div>
                                            <div className="mt-auto space-y-3">
                                                <div className="flex justify-between text-sm tabular-nums">
                                                    <span>
                                                        {candidate.votes} votos
                                                    </span>
                                                    <span className="font-semibold">
                                                        {candidate.percentage.toLocaleString(
                                                            'pt-BR',
                                                        )}
                                                        %
                                                    </span>
                                                </div>
                                                <div
                                                    role="progressbar"
                                                    aria-label={
                                                        'Percentual de ' +
                                                        candidate.name
                                                    }
                                                    aria-valuemin={0}
                                                    aria-valuemax={100}
                                                    aria-valuenow={
                                                        candidate.percentage
                                                    }
                                                    className="h-2 overflow-hidden rounded-full bg-muted"
                                                >
                                                    <div
                                                        className="h-full rounded-full bg-emerald-800 motion-safe:transition-[width] motion-safe:duration-500"
                                                        style={{
                                                            width:
                                                                candidate.percentage +
                                                                '%',
                                                        }}
                                                    />
                                                </div>
                                            </div>
                                            {canVote ? (
                                                <Button
                                                    className="w-full bg-emerald-800 hover:bg-emerald-900"
                                                    onClick={() =>
                                                        select(candidate)
                                                    }
                                                >
                                                    <Vote className="size-4" />
                                                    Votar neste candidato
                                                </Button>
                                            ) : !auth.user &&
                                              round.status === 'aberto' ? (
                                                <Button
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <Link href={login()}>
                                                        Entrar para votar
                                                    </Link>
                                                </Button>
                                            ) : auth.user?.is_active ? (
                                                <Button
                                                    variant="outline"
                                                    disabled
                                                >
                                                    {receipt
                                                        ? 'Voto já registrado'
                                                        : round.status ===
                                                            'agendado'
                                                          ? 'Aguardando abertura'
                                                          : round.status ===
                                                              'encerrado'
                                                            ? 'Turno encerrado'
                                                            : 'Aguardando votação'}
                                                </Button>
                                            ) : (
                                                <p className="text-sm text-muted-foreground">
                                                    Acompanhamento do placar
                                                </p>
                                            )}
                                        </CardContent>
                                    </Card>
                                );
                            })}
                        </section>
                    </>
                ) : (
                    <section className="rounded-xl border border-dashed p-10 text-center">
                        <Vote className="mx-auto size-10 text-muted-foreground" />
                        <h2 className="mt-4 text-xl font-semibold">
                            A primeira eleição ainda não foi criada
                        </h2>
                        <p className="mt-2 text-muted-foreground">
                            Quando a administração publicar a eleição, os
                            candidatos e o placar aparecerão aqui.
                        </p>
                    </section>
                )}
                <section id="historico" className="space-y-5 border-t pt-8">
                    <h2 className="text-2xl font-semibold tracking-tight">
                        Histórico de eleições e turnos
                    </h2>
                    {election && election.rounds.length > 1 && (
                        <div className="space-y-3">
                            <h3 className="font-medium">
                                Turnos anteriores de {election.title}
                            </h3>
                            {election.rounds.slice(0, -1).map((previous) => (
                                <details
                                    key={previous.id}
                                    className="rounded-lg border p-4"
                                >
                                    <summary className="cursor-pointer text-sm font-medium">
                                        Turno {previous.number} ·{' '}
                                        {previous.total} votos
                                    </summary>
                                    <div className="pt-4">
                                        <RoundResults round={previous} />
                                    </div>
                                </details>
                            ))}
                        </div>
                    )}
                    {history.data.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Nenhuma eleição finalizada até o momento.
                        </p>
                    ) : (
                        history.data.map((past) => (
                            <details
                                key={past.id}
                                className="rounded-lg border p-5"
                            >
                                <summary className="cursor-pointer font-medium">
                                    {past.title}{' '}
                                    <span className="text-sm font-normal text-muted-foreground">
                                        · {dateTime(past.completed_at)}
                                    </span>
                                </summary>
                                <div className="mt-5 space-y-6">
                                    {past.rounds.map((previous) => (
                                        <RoundResults
                                            key={previous.id}
                                            round={previous}
                                        />
                                    ))}
                                </div>
                            </details>
                        ))
                    )}
                    <PaginationLinks pagination={history} />
                </section>
                <Dialog
                    open={selected !== null}
                    onOpenChange={(open) => {
                        if (!open && !form.processing) {
                            setSelected(null);
                        }
                    }}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                Confirmar voto em {selected?.name}?
                            </DialogTitle>
                            <DialogDescription>
                                Seu voto é definitivo e não pode ser alterado ou
                                excluído. Sua participação será registrada
                                separadamente da cédula, sem vínculo armazenado
                                entre sua conta e o candidato. O placar ao vivo
                                permite inferências pela variação da contagem.
                            </DialogDescription>
                        </DialogHeader>
                        <label className="flex items-start gap-3 text-sm">
                            <Checkbox
                                checked={form.data.confirmed}
                                onCheckedChange={(checked) =>
                                    form.setData('confirmed', checked === true)
                                }
                            />
                            <span>
                                Estou ciente de que meu voto é definitivo e
                                auditável.
                            </span>
                        </label>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                disabled={form.processing}
                                onClick={() => setSelected(null)}
                            >
                                Voltar
                            </Button>
                            <Button
                                disabled={
                                    !form.data.confirmed || form.processing
                                }
                                onClick={confirm}
                            >
                                {form.processing
                                    ? 'Registrando voto…'
                                    : 'Confirmar voto definitivo'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
