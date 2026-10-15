import { Link, usePage } from '@inertiajs/react';
import { Vote, Menu } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { UserMenuContent } from '@/components/user-menu-content';
import { home, login, register } from '@/routes';
import { elections, users } from '@/routes/admin';
import { index as audit } from '@/routes/audit';
import type { BreadcrumbItem } from '@/types';

export function AppHeader(_props: { breadcrumbs?: BreadcrumbItem[] }) {
    const { auth } = usePage().props;
    const user = auth.user;
    const navigation = [{ label: 'Eleição atual', href: home() }];
    if (user?.is_active && user.role === 'administrador') {
        navigation.push(
            { label: 'Administração', href: elections() },
            { label: 'Usuários', href: users() },
        );
    }
    if (
        user?.is_active &&
        ['administrador', 'auditor'].includes(String(user.role))
    ) {
        navigation.push({ label: 'Auditoria', href: audit() });
    }
    const links = navigation.map((item) => (
        <Link
            key={item.label}
            href={item.href}
            className="rounded-md px-3 py-2 text-sm font-medium hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
        >
            {item.label}
        </Link>
    ));
    return (
        <header className="border-b bg-background">
            <div className="mx-auto flex min-h-18 max-w-7xl items-center gap-4 px-4 md:px-8">
                <Link
                    href={home()}
                    className="flex items-center gap-2 font-semibold"
                >
                    <Vote className="size-7 text-emerald-800" />
                    <span>
                        Votação
                        <span className="hidden text-sm font-normal text-muted-foreground sm:inline">
                            {' '}
                            / Representante do grupo
                        </span>
                    </span>
                </Link>
                <nav
                    aria-label="Navegação principal"
                    className="ml-6 hidden gap-1 lg:flex"
                >
                    {links}
                </nav>
                <div className="ml-auto flex items-center gap-2">
                    <div className="lg:hidden">
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Abrir navegação"
                                >
                                    <Menu />
                                </Button>
                            </SheetTrigger>
                            <SheetContent side="left">
                                <SheetHeader>
                                    <SheetTitle>Navegação</SheetTitle>
                                </SheetHeader>
                                <nav className="flex flex-col gap-2 p-4">
                                    {links}
                                </nav>
                            </SheetContent>
                        </Sheet>
                    </div>
                    {user ? (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="outline"
                                    className="max-w-40 truncate"
                                    aria-label="Menu do usuário"
                                >
                                    {user.name}
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <UserMenuContent user={user} />
                            </DropdownMenuContent>
                        </DropdownMenu>
                    ) : (
                        <>
                            <Button variant="ghost" asChild>
                                <Link href={login()}>Entrar</Link>
                            </Button>
                            <Button asChild>
                                <Link href={register()}>Cadastrar</Link>
                            </Button>
                        </>
                    )}
                </div>
            </div>
        </header>
    );
}
