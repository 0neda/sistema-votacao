import { Head, useForm } from '@inertiajs/react';
import { CalendarClock, LockKeyhole } from 'lucide-react';
import { useState } from 'react';
import {
    dateTime,
    Feedback,
    outcomeText,
    StatusBadge,
    useElectionPolling,
} from '@/components/election-ui';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as createElection } from '@/routes/elections';
import { close, store as schedule } from '@/routes/rounds';
import type { Election, Round, Snapshot } from '@/types/voting';

const textAreaClass =
    'min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:opacity-50';

function ScheduleRound({
    election,
    previous,
}: {
    election: Election;
    previous: Round;
}) {
    const manual = previous.number >= 2;
    const form = useForm({
        scheduled_at: '',
        candidate_ids: manual ? previous.leaders : ([] as number[]),
        selection_justification: '',
    });
    return (
        <form
            className="space-y-5"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(schedule.url(election.id), { preserveScroll: true });
            }}
        >
            <div>
                <h3 className="text-lg font-semibold">
                    Agendar turno {previous.number + 1}
                </h3>
                <p className="mt-1 text-sm text-muted-foreground">
                    {manual
                        ? 'Selecione de 2 a 3 candidatos originais e justifique a escolha.'
                        : 'Participam exclusivamente os candidatos empatados na liderança.'}
                </p>
            </div>
            {manual ? (
                <fieldset className="space-y-3">
                    <legend className="mb-3 text-sm font-medium">
                        Participantes do novo turno
                    </legend>
                    {election.candidates.map((candidate) => (
                        <label
                            key={candidate.id}
                            className="flex items-center gap-3 text-sm"
                        >
                            <Checkbox
                                checked={form.data.candidate_ids.includes(
                                    candidate.id,
                                )}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'candidate_ids',
                                        checked
                                            ? [
                                                  ...form.data.candidate_ids,
                                                  candidate.id,
                                              ]
                                            : form.data.candidate_ids.filter(
                                                  (id) => id !== candidate.id,
                                              ),
                                    )
                                }
                            />
                            {candidate.name}
                        </label>
                    ))}
                </fieldset>
            ) : (
                <ul className="list-inside list-disc text-sm">
                    {previous.candidates
                        .filter((candidate) =>
                            previous.leaders.includes(candidate.id),
                        )
                        .map((candidate) => (
                            <li key={candidate.id}>{candidate.name}</li>
                        ))}
                </ul>
            )}
            <div className="grid max-w-sm gap-2">
                <Label htmlFor="next-schedule">
                    Abertura (horário de Brasília)
                </Label>
                <Input
                    id="next-schedule"
                    type="datetime-local"
                    required
                    value={form.data.scheduled_at}
                    onChange={(event) =>
                        form.setData('scheduled_at', event.target.value)
                    }
                />
            </div>
            {manual && (
                <div className="grid gap-2">
                    <Label htmlFor="justification">
                        Justificativa da seleção
                    </Label>
                    <textarea
                        id="justification"
                        className={textAreaClass}
                        required
                        maxLength={500}
                        value={form.data.selection_justification}
                        onChange={(event) =>
                            form.setData(
                                'selection_justification',
                                event.target.value,
                            )
                        }
                    />
                </div>
            )}
            <Button disabled={form.processing}>
                {form.processing ? 'Agendando…' : 'Agendar novo turno'}
            </Button>
        </form>
    );
}

export default function AdminElections({ snapshot }: { snapshot: Snapshot }) {
    useElectionPolling(snapshot);
    const election = snapshot.election;
    const round = election?.rounds.at(-1);
    const [closing, setClosing] = useState(false);
    const closeForm = useForm({});
    const form = useForm({
        title: '',
        opening: 'imediata',
        scheduled_at: '',
        candidates: [
            { name: '', photo: null as File | null },
            { name: '', photo: null as File | null },
            { name: '', photo: null as File | null },
        ],
    });
    function setCandidate(
        index: number,
        field: 'name' | 'photo',
        value: string | File | null,
    ) {
        form.setData(
            'candidates',
            form.data.candidates.map((candidate, candidateIndex) =>
                candidateIndex === index
                    ? { ...candidate, [field]: value }
                    : candidate,
            ),
        );
    }
    return (
        <>
            <Head title="Administração" />
            <div className="space-y-8 px-4 py-8 md:px-8 md:py-12">
                <div>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Administração
                    </h1>
                    <p className="mt-2 text-muted-foreground">
                        Gerencie uma eleição por vez, da publicação ao resultado
                        final.
                    </p>
                </div>
                <Feedback />
                {election && round && (
                    <Card className="shadow-none">
                        <CardHeader>
                            <div className="flex flex-wrap items-center gap-3">
                                <CardTitle>{election.title}</CardTitle>
                                <StatusBadge round={round} />
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <p className="text-sm">
                                Turno {round.number} · {outcomeText(round)}
                            </p>
                            {round.status === 'aberto' && (
                                <>
                                    <p className="text-sm text-muted-foreground">
                                        O encerramento é definitivo. Votos em
                                        andamento serão concluídos ou rejeitados
                                        de forma segura antes do resultado.
                                    </p>
                                    <Button
                                        variant="destructive"
                                        onClick={() => setClosing(true)}
                                    >
                                        Encerrar turno atual
                                    </Button>
                                </>
                            )}
                            {round.status === 'agendado' && (
                                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <CalendarClock className="size-4" />A
                                    abertura ocorrerá ao acessar o sistema ou
                                    atualizar o placar, após{' '}
                                    {dateTime(round.scheduled_at)}. Não é
                                    possível antecipar.
                                </p>
                            )}
                            {round.status === 'encerrado' &&
                                round.outcome === 'empate' &&
                                snapshot.has_active_election && (
                                    <ScheduleRound
                                        key={round.id}
                                        election={election}
                                        previous={round}
                                    />
                                )}
                            {round.outcome === 'falha' && (
                                <p className="rounded-lg bg-amber-50 p-4 text-sm text-amber-950">
                                    A eleição foi finalizada como falha por
                                    ausência de votos. Você pode criar uma nova
                                    eleição abaixo.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}
                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle>Nova eleição</CardTitle>
                        <p className="flex items-center gap-2 text-sm text-muted-foreground">
                            <LockKeyhole className="size-4" />
                            Ao publicar, os dados da eleição e dos candidatos
                            não poderão ser alterados.
                        </p>
                    </CardHeader>
                    <CardContent>
                        {snapshot.has_active_election && (
                            <p className="mb-6 rounded-lg bg-amber-50 p-4 text-sm text-amber-950">
                                Finalize a eleição em andamento antes de criar
                                outra. Um empate precisa ser resolvido em novos
                                turnos.
                            </p>
                        )}
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.post(createElection.url(), {
                                    forceFormData: true,
                                    preserveScroll: true,
                                    onSuccess: () => form.reset(),
                                });
                            }}
                        >
                            <fieldset
                                disabled={
                                    snapshot.has_active_election ||
                                    form.processing
                                }
                                className="space-y-6 disabled:opacity-55"
                            >
                                <div className="grid gap-5 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="title">
                                            Título da eleição
                                        </Label>
                                        <Input
                                            id="title"
                                            required
                                            maxLength={255}
                                            value={form.data.title}
                                            onChange={(event) =>
                                                form.setData(
                                                    'title',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Representante da turma"
                                        />
                                    </div>
                                </div>
                                <div className="grid gap-6 md:grid-cols-3">
                                    {form.data.candidates.map(
                                        (candidate, index) => (
                                            <fieldset
                                                key={index}
                                                className="space-y-4 border-t pt-4"
                                            >
                                                <legend className="pr-3 text-sm font-semibold">
                                                    Candidato {index + 1}
                                                </legend>
                                                <div className="grid gap-2">
                                                    <Label
                                                        htmlFor={
                                                            'candidate-name-' +
                                                            index
                                                        }
                                                    >
                                                        Nome
                                                    </Label>
                                                    <Input
                                                        id={
                                                            'candidate-name-' +
                                                            index
                                                        }
                                                        required
                                                        maxLength={120}
                                                        value={candidate.name}
                                                        onChange={(event) =>
                                                            setCandidate(
                                                                index,
                                                                'name',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label
                                                        htmlFor={
                                                            'candidate-photo-' +
                                                            index
                                                        }
                                                    >
                                                        Foto (opcional)
                                                    </Label>
                                                    <Input
                                                        id={
                                                            'candidate-photo-' +
                                                            index
                                                        }
                                                        type="file"
                                                        accept="image/jpeg,image/png,image/webp"
                                                        onChange={(event) =>
                                                            setCandidate(
                                                                index,
                                                                'photo',
                                                                event.target
                                                                    .files?.[0] ??
                                                                    null,
                                                            )
                                                        }
                                                    />
                                                    <p className="text-xs text-muted-foreground">
                                                        JPG, PNG ou WebP, até 2
                                                        MB e 3000 × 3000 pixels.
                                                    </p>
                                                </div>
                                            </fieldset>
                                        ),
                                    )}
                                </div>
                                <fieldset className="space-y-3 border-t pt-5">
                                    <legend className="pr-3 text-sm font-semibold">
                                        Abertura do primeiro turno
                                    </legend>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="radio"
                                            name="opening"
                                            checked={
                                                form.data.opening === 'imediata'
                                            }
                                            onChange={() =>
                                                form.setData(
                                                    'opening',
                                                    'imediata',
                                                )
                                            }
                                        />
                                        Abrir imediatamente após publicar
                                    </label>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="radio"
                                            name="opening"
                                            checked={
                                                form.data.opening === 'agendada'
                                            }
                                            onChange={() =>
                                                form.setData(
                                                    'opening',
                                                    'agendada',
                                                )
                                            }
                                        />
                                        Agendar abertura
                                    </label>
                                    {form.data.opening === 'agendada' && (
                                        <div className="grid max-w-sm gap-2">
                                            <Label htmlFor="first-schedule">
                                                Data e hora (Brasília)
                                            </Label>
                                            <Input
                                                id="first-schedule"
                                                type="datetime-local"
                                                required
                                                value={form.data.scheduled_at}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'scheduled_at',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                    )}
                                </fieldset>
                                <Button type="submit">
                                    {form.processing
                                        ? 'Publicando…'
                                        : 'Publicar eleição'}
                                </Button>
                            </fieldset>
                        </form>
                    </CardContent>
                </Card>
                <Dialog open={closing} onOpenChange={setClosing}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Encerrar o turno atual?</DialogTitle>
                            <DialogDescription>
                                Esta ação é definitiva. O turno não poderá ser
                                reaberto e os votos identificados ficarão
                                disponíveis à administração e à auditoria.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setClosing(false)}
                                disabled={closeForm.processing}
                            >
                                Voltar
                            </Button>
                            <Button
                                variant="destructive"
                                disabled={closeForm.processing}
                                onClick={() => {
                                    if (round) {
                                        closeForm.post(close.url(round.id), {
                                            preserveScroll: true,
                                            onFinish: () => setClosing(false),
                                        });
                                    }
                                }}
                            >
                                {closeForm.processing
                                    ? 'Encerrando…'
                                    : 'Confirmar encerramento'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
