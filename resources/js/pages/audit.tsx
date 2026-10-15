import { Head, router, useForm } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { dateTime, Feedback, PaginationLinks } from '@/components/election-ui';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { index } from '@/routes/audit';
import type { Pagination } from '@/types/voting';

type Log = {
    id: number;
    created_at: string;
    actor: string;
    event: string;
    target: string;
    result: string;
    ip_address: string | null;
    metadata: Record<string, unknown>;
};
type Ballot = { hash: string; candidate: string; is_simulated: boolean };
type Participant = { id: number; name: string; email: string };
type Filters = {
    event?: string;
    from?: string;
    until?: string;
    round_id?: number;
    tab?: string;
};
const events = [
    'CRIACAO_ELEICAO',
    'ABERTURA_MANUAL',
    'ABERTURA_AUTOMATICA',
    'ENCERRAMENTO_TURNO',
    'AGENDAMENTO_TURNO',
    'VOTO_ACEITO',
    'VOTO_REJEITADO',
    'VOTO_DUPLICADO',
    'ACESSO_NEGADO',
    'CONSULTA_CEDULAS',
    'ALTERACAO_ACESSO_USUARIO',
    'EXCLUSAO_USUARIO',
    'SIMULACAO_VOTOS',
];
const selectClass =
    'h-10 w-full rounded-md border bg-background px-3 text-sm focus-visible:outline-2 focus-visible:outline-ring';
const cellClass = 'px-4 py-3 align-top';
export default function Audit({
    logs,
    filters,
    closedRounds,
    ballots,
    participants,
}: {
    logs: Pagination<Log>;
    filters: Filters;
    closedRounds: { id: number; label: string }[];
    ballots: Pagination<Ballot> | null;
    participants: Pagination<Participant> | null;
}) {
    const [tab, setTab] = useState(
        filters.tab ?? (filters.round_id ? 'votes' : 'logs'),
    );
    const [selectedRound, setSelectedRound] = useState(
        String(filters.round_id ?? ''),
    );
    const form = useForm({
        event: filters.event ?? '',
        from: filters.from ?? '',
        until: filters.until ?? '',
        tab: 'logs',
    });
    return (
        <>
            <Head title="Auditoria e segurança" />
            <div className="space-y-8 px-4 py-8 md:px-8 md:py-12">
                <div className="flex items-start gap-4">
                    <ShieldCheck className="mt-1 size-8 shrink-0 text-emerald-800" />
                    <div>
                        <h1 className="text-3xl font-semibold tracking-tight">
                            Auditoria e segurança
                        </h1>
                        <p className="mt-2 max-w-2xl text-muted-foreground">
                            Trilha de ações imutável e consulta restrita dos
                            votos de turnos encerrados. Horários de Brasília.
                        </p>
                    </div>
                </div>
                <Feedback />
                <Tabs value={tab} onValueChange={setTab}>
                    <TabsList aria-label="Dados de auditoria">
                        <TabsTrigger value="logs">
                            Trilha de auditoria
                        </TabsTrigger>
                        <TabsTrigger value="votes">
                            Cédulas e participação
                        </TabsTrigger>
                    </TabsList>
                    <TabsContent value="logs">
                        <form
                            className="mb-6 flex flex-wrap items-end gap-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.get(index.url(), {
                                    preserveState: true,
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <div className="grid min-w-48 gap-2">
                                <Label htmlFor="event">Evento</Label>
                                <select
                                    id="event"
                                    className={selectClass}
                                    value={form.data.event}
                                    onChange={(event) =>
                                        form.setData(
                                            'event',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="">Todos os eventos</option>
                                    {events.map((event) => (
                                        <option key={event} value={event}>
                                            {event.replaceAll('_', ' ')}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="from">De</Label>
                                <Input
                                    id="from"
                                    type="date"
                                    value={form.data.from}
                                    onChange={(event) =>
                                        form.setData('from', event.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="until">Até</Label>
                                <Input
                                    id="until"
                                    type="date"
                                    value={form.data.until}
                                    onChange={(event) =>
                                        form.setData(
                                            'until',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                            <Button disabled={form.processing}>
                                Filtrar registros
                            </Button>
                            <Button
                                variant="outline"
                                type="button"
                                onClick={() => {
                                    form.reset();
                                    router.get(
                                        index.url(),
                                        {},
                                        { preserveState: false },
                                    );
                                }}
                            >
                                Limpar
                            </Button>
                        </form>
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <caption className="sr-only">
                                    Registros de auditoria
                                </caption>
                                <thead className="border-b bg-muted/50">
                                    <tr>
                                        {[
                                            'Data e hora',
                                            'Ator',
                                            'Evento',
                                            'Alvo',
                                            'Resultado',
                                            'IP',
                                            'Metadados seguros',
                                        ].map((title) => (
                                            <th
                                                key={title}
                                                className={
                                                    cellClass + ' font-medium'
                                                }
                                            >
                                                {title}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {logs.data.map((log) => (
                                        <tr key={log.id}>
                                            <td
                                                className={
                                                    cellClass +
                                                    ' whitespace-nowrap tabular-nums'
                                                }
                                            >
                                                {dateTime(log.created_at)}
                                            </td>
                                            <td className={cellClass}>
                                                {log.actor}
                                            </td>
                                            <td className={cellClass}>
                                                {log.event.replaceAll('_', ' ')}
                                            </td>
                                            <td className={cellClass}>
                                                {log.target}
                                            </td>
                                            <td className={cellClass}>
                                                <Badge
                                                    variant="secondary"
                                                    className={
                                                        log.result === 'sucesso'
                                                            ? 'bg-emerald-50 text-emerald-900'
                                                            : 'bg-red-50 text-red-900'
                                                    }
                                                >
                                                    {log.result === 'sucesso'
                                                        ? 'Sucesso'
                                                        : 'Rejeitado'}
                                                </Badge>
                                            </td>
                                            <td className={cellClass}>
                                                {log.ip_address ?? '—'}
                                            </td>
                                            <td
                                                className={
                                                    cellClass +
                                                    ' min-w-40 break-words'
                                                }
                                            >
                                                {Object.entries(
                                                    log.metadata ?? {},
                                                ).map(([key, value]) => (
                                                    <div key={key}>
                                                        {key}: {String(value)}
                                                    </div>
                                                ))}
                                            </td>
                                        </tr>
                                    ))}
                                    {logs.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="p-8 text-center text-muted-foreground"
                                            >
                                                Nenhum registro corresponde aos
                                                filtros.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <PaginationLinks pagination={logs} />
                    </TabsContent>
                    <TabsContent value="votes">
                        <p className="mb-5 rounded-lg bg-amber-50 p-4 text-sm text-amber-950">
                            Cada consulta às cédulas é registrada na trilha de
                            auditoria. Turnos abertos ou agendados não podem ser
                            consultados. A lista de participantes é separada das
                            cédulas: não existe vínculo armazenado entre conta e
                            candidato.
                        </p>
                        <form
                            className="mb-6 flex flex-wrap items-end gap-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                router.get(
                                    index.url(),
                                    { round_id: selectedRound, tab: 'votes' },
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                    },
                                );
                            }}
                        >
                            <div className="grid w-full max-w-xl gap-2">
                                <Label htmlFor="closed-round">
                                    Turno encerrado
                                </Label>
                                <select
                                    id="closed-round"
                                    className={selectClass}
                                    required
                                    value={selectedRound}
                                    onChange={(event) =>
                                        setSelectedRound(event.target.value)
                                    }
                                >
                                    <option value="">Selecione um turno</option>
                                    {closedRounds.map((round) => (
                                        <option key={round.id} value={round.id}>
                                            {round.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <Button disabled={!selectedRound}>
                                Consultar votos
                            </Button>
                        </form>
                        {ballots && participants ? (
                            <div className="space-y-8">
                                <section className="space-y-4">
                                    <h2 className="text-xl font-semibold">
                                        Participantes ({participants.total})
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Confirma quem participou, sem código de
                                        cédula ou candidato.
                                    </p>
                                    <div className="overflow-x-auto rounded-lg border">
                                        <table className="w-full text-left text-sm">
                                            <caption className="sr-only">
                                                Participantes do turno
                                            </caption>
                                            <thead className="border-b bg-muted/50">
                                                <tr>
                                                    <th className={cellClass}>
                                                        Nome
                                                    </th>
                                                    <th className={cellClass}>
                                                        E-mail
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y">
                                                {participants.data.map(
                                                    (participant) => (
                                                        <tr
                                                            key={participant.id}
                                                        >
                                                            <td
                                                                className={
                                                                    cellClass
                                                                }
                                                            >
                                                                {
                                                                    participant.name
                                                                }
                                                            </td>
                                                            <td
                                                                className={
                                                                    cellClass
                                                                }
                                                            >
                                                                {
                                                                    participant.email
                                                                }
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                    {participants.total === 0 && (
                                        <p className="text-sm text-muted-foreground">
                                            Nenhuma participação registrada.
                                        </p>
                                    )}
                                    <PaginationLinks
                                        pagination={participants}
                                    />
                                </section>
                                <section className="space-y-4">
                                    <h2 className="text-xl font-semibold">
                                        Cédulas ({ballots.total})
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Códigos aleatórios independentes das
                                        contas, sem identificação ou horário do
                                        voto.
                                    </p>
                                    <div className="overflow-x-auto rounded-lg border">
                                        <table className="w-full text-left text-sm">
                                            <caption className="sr-only">
                                                Cédulas do turno encerrado
                                            </caption>
                                            <thead className="border-b bg-muted/50">
                                                <tr>
                                                    <th className={cellClass}>
                                                        Código da cédula
                                                    </th>
                                                    <th className={cellClass}>
                                                        Candidato
                                                    </th>
                                                    <th className={cellClass}>
                                                        Origem
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y">
                                                {ballots.data.map((ballot) => (
                                                    <tr key={ballot.hash}>
                                                        <td
                                                            className={
                                                                cellClass +
                                                                ' max-w-72 font-mono text-xs break-all'
                                                            }
                                                        >
                                                            {ballot.hash}
                                                        </td>
                                                        <td
                                                            className={
                                                                cellClass
                                                            }
                                                        >
                                                            {ballot.candidate}
                                                        </td>
                                                        <td
                                                            className={
                                                                cellClass
                                                            }
                                                        >
                                                            {ballot.is_simulated ? (
                                                                <Badge variant="secondary">
                                                                    Simulação
                                                                </Badge>
                                                            ) : (
                                                                'Votação'
                                                            )}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                    {ballots.total === 0 && (
                                        <p className="text-sm text-muted-foreground">
                                            Nenhuma cédula registrada.
                                        </p>
                                    )}
                                    <PaginationLinks pagination={ballots} />
                                </section>
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Selecione um turno encerrado para consultar.
                            </p>
                        )}
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}
