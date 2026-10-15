import { Head, useForm, usePage } from '@inertiajs/react';
import { UsersRound } from 'lucide-react';
import { useState } from 'react';
import { Feedback, PaginationLinks } from '@/components/election-ui';
import { Button } from '@/components/ui/button';
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
import { users as userList } from '@/routes/admin';
import { destroy, update } from '@/routes/users';
import type { User } from '@/types';
import type { Pagination } from '@/types/voting';

type ManagedUser = Pick<
    User,
    'id' | 'name' | 'email' | 'role' | 'is_active'
> & { can_delete: boolean; can_change_access: boolean };
const roles = {
    eleitor: 'Eleitor',
    administrador: 'Administrador',
    auditor: 'Auditor',
};
function UserRow({ user }: { user: ManagedUser }) {
    const form = useForm({ role: user.role, is_active: user.is_active });
    const { auth } = usePage().props;
    const [deleting, setDeleting] = useState(false);
    const deletion = useForm({});
    return (
        <tr className="border-t">
            <td className="p-4">
                <span className="font-medium">{user.name}</span>
                {auth.user.id === user.id && (
                    <span className="ml-2 text-xs text-muted-foreground">
                        (você)
                    </span>
                )}
                <p className="mt-1 text-xs text-muted-foreground">
                    {user.email}
                </p>
            </td>
            <td className="p-4" colSpan={2}>
                {user.can_change_access ? (
                    <form
                        id={'access-' + user.id}
                        className="flex flex-wrap items-center gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.patch(update.url(user.id), {
                                preserveScroll: true,
                            });
                        }}
                    >
                        <label className="sr-only" htmlFor={'role-' + user.id}>
                            Perfil de {user.name}
                        </label>
                        <select
                            id={'role-' + user.id}
                            className="h-9 rounded-md border bg-background px-3 text-sm"
                            value={form.data.role}
                            onChange={(event) =>
                                form.setData(
                                    'role',
                                    event.target.value as User['role'],
                                )
                            }
                        >
                            {Object.entries(roles).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                        <label className="flex items-center gap-2">
                            <Checkbox
                                checked={form.data.is_active}
                                onCheckedChange={(checked) =>
                                    form.setData('is_active', checked === true)
                                }
                            />
                            Conta ativa
                        </label>
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={form.processing || !form.isDirty}
                            type="submit"
                        >
                            {form.processing
                                ? 'Salvando…'
                                : 'Salvar permissões'}
                        </Button>
                    </form>
                ) : (
                    <p className="text-sm">
                        Administrador ativo{' '}
                        <span className="ml-2 text-xs text-muted-foreground">
                            Último administrador; acesso protegido
                        </span>
                    </p>
                )}
            </td>
            <td className="p-4">
                {user.can_delete ? (
                    <>
                        <Button
                            size="sm"
                            variant="destructive"
                            onClick={() => setDeleting(true)}
                        >
                            Excluir conta
                        </Button>
                        <Dialog open={deleting} onOpenChange={setDeleting}>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>
                                        Excluir a conta de {user.name}?
                                    </DialogTitle>
                                    <DialogDescription>
                                        Esta conta não possui votos ou registros
                                        históricos. A exclusão é definitiva.
                                        Para apenas impedir o acesso, desative a
                                        conta.
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter>
                                    <Button
                                        variant="outline"
                                        disabled={deletion.processing}
                                        onClick={() => setDeleting(false)}
                                    >
                                        Voltar
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        disabled={deletion.processing}
                                        onClick={() =>
                                            deletion.delete(
                                                destroy.url(user.id),
                                                {
                                                    preserveScroll: true,
                                                    onFinish: () =>
                                                        setDeleting(false),
                                                },
                                            )
                                        }
                                    >
                                        {deletion.processing
                                            ? 'Excluindo…'
                                            : 'Confirmar exclusão'}
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </>
                ) : (
                    <p className="text-xs text-muted-foreground">
                        {auth.user.id === user.id
                            ? 'Sua conta está protegida'
                            : 'Histórico preservado; desative para bloquear'}
                    </p>
                )}
            </td>
        </tr>
    );
}
export default function Users({
    users,
    search,
}: {
    users: Pagination<ManagedUser>;
    search: string;
}) {
    const form = useForm({ search });
    return (
        <>
            <Head title="Usuários" />
            <div className="space-y-8 px-4 py-8 md:px-8 md:py-12">
                <div className="flex items-start gap-4">
                    <UsersRound className="mt-1 size-8 text-emerald-800" />
                    <div>
                        <h1 className="text-3xl font-semibold tracking-tight">
                            Usuários e permissões
                        </h1>
                        <p className="mt-2 max-w-2xl text-muted-foreground">
                            Gerencie os acessos de contas já cadastradas. Contas
                            com histórico são preservadas e podem ser
                            desativadas.
                        </p>
                    </div>
                </div>
                <Feedback />
                <form
                    className="flex max-w-lg items-end gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.get(userList.url(), { preserveState: true });
                    }}
                >
                    <div className="grid flex-1 gap-2">
                        <Label htmlFor="user-search">Nome ou e-mail</Label>
                        <Input
                            id="user-search"
                            value={form.data.search}
                            onChange={(event) =>
                                form.setData('search', event.target.value)
                            }
                            placeholder="Buscar usuários"
                            maxLength={100}
                        />
                    </div>
                    <Button disabled={form.processing}>Buscar</Button>
                </form>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <caption className="sr-only">
                            Gerenciamento de usuários cadastrados
                        </caption>
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="p-4 font-medium">Usuário</th>
                                <th className="p-4 font-medium" colSpan={2}>
                                    Perfil e situação
                                </th>
                                <th className="p-4 font-medium">Exclusão</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.map((user) => (
                                <UserRow
                                    key={
                                        user.id +
                                        '-' +
                                        user.role +
                                        '-' +
                                        user.is_active
                                    }
                                    user={user}
                                />
                            ))}
                            {users.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="p-8 text-center text-muted-foreground"
                                    >
                                        Nenhum usuário encontrado.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <PaginationLinks pagination={users} />
            </div>
        </>
    );
}
