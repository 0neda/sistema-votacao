# Sistema de votação

Laravel 13, React/Inertia 3, Tailwind/shadcn e PostgreSQL. Uma eleição por vez, três candidatos iniciais e voto definitivo com cédula aleatória independente da participação por turno.

## Regras consolidadas

As decisões posteriores à SPEC-votacao.md prevalecem:

- Uma eleição em andamento bloqueia novas eleições, inclusive enquanto aguarda desempate.
- Um turno encerrado sem votos finaliza a eleição como **Votação falha**, sem novo turno.
- Turnos abrem imediatamente na criação ou automaticamente sob demanda após o horário agendado; não há antecipação nem cron. O encerramento é manual.
- O segundo turno inclui somente os líderes empatados. A partir do terceiro, o administrador seleciona 2–3 candidatos originais e justifica a seleção.
- Fotos opcionais: JPG/PNG/WebP, até 2 MB e 3000 × 3000 pixels, convertidas para WebP e reduzidas a até 800 pixels. O campo descritivo de período foi removido.
- Administração de usuários permite alterar perfil e ativação. Contas com votos, auditoria ou eleições criadas só podem ser desativadas. O último administrador ativo e a própria conta têm proteções contra exclusão.
- A Home reúne placar, votação, comprovante e histórico. Sem eleição vigente, mostra o último resultado e o aviso correspondente. Ações sem permissão ficam ocultas e o backend as recusa.

## Setup (PowerShell)

Requisitos: PHP 8.3+ com PDO PostgreSQL e GD/WebP, Composer, Node/npm, PostgreSQL e ferramentas pg_dump/pg_restore/psql da mesma versão principal do servidor.

```powershell
composer install
npm ci
Copy-Item .env.example .env
php artisan key:generate --no-interaction
```

Crie os bancos separados e um usuário proprietário, depois configure DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME e DB_PASSWORD na .env. Não substitua uma .env já configurada. Para este ambiente os bancos votacao e votacao_test e o usuário votacao_app já foram preparados.

```powershell
php artisan migrate --no-interaction
php artisan storage:link --no-interaction
php artisan db:seed --class=DemoUserSeeder --no-interaction
npm run build
composer run dev
```

Se aparecer **could not find driver**, confira `php --ini` e `php -r "print_r(PDO::getAvailableDrivers());"`. O PHP do terminal e do servidor precisam ter pdo_pgsql. Não misture executáveis de PHP com configurações diferentes.

### Contas locais

O seeder só executa em local/testing e pode ser repetido.

| E-mail              | Perfil        | Senha de demonstração |
| ------------------- | ------------- | --------------------- |
| alex@teste.dev      | Administrador | 123456                |
| layne@teste.dev     | Auditor       | 123456                |
| guilherme@teste.dev | Eleitor       | 123456                |

Novos cadastros sempre recebem perfil eleitor e conta ativa, independentemente do formulário.

### Telas

- `/`: central pública com votação de qualquer perfil ativo, fotos, comprovante e histórico.
- `/admin/eleicoes`: criação, encerramento e agendamento de desempates.
- `/admin/usuarios`: perfis, ativação e exclusão permitida.
- `/auditoria`: logs paginados e participantes e cédulas sem vínculo entre si, exclusivamente de turnos encerrados.

## Testes e qualidade

Configure uma .env.testing com a mesma APP_KEY e credenciais locais, APP_ENV=testing e DB_DATABASE=votacao_test. Ela não deve ser versionada. A suíte usa exclusivamente PostgreSQL, incluindo testes reais das triggers; RefreshDatabase apaga o banco de testes.

```powershell
php artisan test --compact
php vendor/bin/phpstan analyse
npm run types:check
npm run check
npm run build
```

Não use o banco de desenvolvimento ou de produção como banco de testes. Os nomes votacao, votacao_test e votacao_restore_test são distintos.

## Demonstração de redundância A/B

Use APP_URL apontando para o proxy (porta 8080). Use a mesma pasta de código, APP_KEY, PostgreSQL, armazenamento de fotos, SESSION_DRIVER=database e CACHE_STORE=database. Em duas máquinas, compartilhe também as fotos. Para esta demonstração, as duas instâncias rodam na mesma máquina.

Gere os assets antes. Não use Vite dev na apresentação: encerre o processo npm de desenvolvimento para remover public/hot. Mantenha o mesmo domínio no proxy para preservar o cookie.

Terminal A:

```powershell
$env:APP_INSTANCE = "Instância A"
php artisan serve --host=127.0.0.1 --port=8001 --no-reload
```

Terminal B:

```powershell
$env:APP_INSTANCE = "Instância B"
php artisan serve --host=127.0.0.1 --port=8002 --no-reload
```

Terminal do proxy (Caddy instalado):

```powershell
caddy run --config Caddyfile
```

Alternativa Nginx, também incluída e validada com duas instâncias:

```powershell
New-Item -ItemType Directory -Force storage/framework/nginx-runtime/logs, storage/framework/nginx-runtime/temp
nginx -p "$PWD/storage/framework/nginx-runtime/" -c "$PWD/nginx.conf"
```

Use somente um proxy por vez na porta 8080. No Nginx, a troca ocorre na primeira tentativa de conexão que falhar; no Caddy, também há health checks periódicos.

Abra o proxy na porta 8080, faça login e mantenha um turno aberto. O rodapé identifica a instância a cada leitura, inclusive no polling parcial. Pare somente A com Ctrl+C; após o health check, a próxima atualização deve mostrar B mantendo sessão e votos. Vote em B e confira o resultado e a auditoria. Reiniciar A volta a priorizá-la.

Não rode config:cache com um APP_INSTANCE compartilhado: ele deve ser lido separadamente por processo. Em produção real, cada implantação deve gerar seu próprio cache de configuração.

Limite acadêmico: isso demonstra redundância de processo da aplicação; não cobre falha do PostgreSQL, do proxy, do armazenamento ou da máquina.

## Backup e restauração isolada

As ferramentas leem as credenciais da configuração Laravel/.env. Senhas são passadas por PGPASSWORD apenas durante o processo e não são impressas. Pausar ações durante a comparação permite comparar o backup com a origem sem novos logs.

```powershell
$env:PGBIN = "C:\laragon\bin\postgresql\postgresql\bin"
.\backup.ps1 -OutputFile seminario.dump
```

O backup de dados usa formato custom do pg_dump. As fotos são arquivadas separadamente em seminario.dump.photos.zip. Guarde ambos.

Crie **um banco vazio separado**, cujo proprietário seja o usuário da aplicação, utilizando sua conta administrativa do PostgreSQL:

```powershell
& "$env:PGBIN\createdb.exe" -U postgres -O votacao_app votacao_restore_test
.\restore-test.ps1 -BackupFile seminario.dump
```

O script restaura exclusivamente em votacao_restore_test, em uma transação, sem limpeza automática de tabelas. Compara contagens e hashes do conteúdo de votes, participations e audit_logs. Para repetir, prepare novamente um banco de restauração vazio; nunca limpe o banco principal.

Para recuperar as fotos, extraia o ZIP em uma pasta separada e confira o conteúdo antes de copiá-lo para storage/app/public. O banco mantém os caminhos relativos.

## Evidências para o seminário

| Mecanismo                                          | Risco mitigado                               | Evidência/teste                                       |
| -------------------------------------------------- | -------------------------------------------- | ----------------------------------------------------- |
| Policies e contas ativas verificadas no servidor   | Acesso indevido e sessão de conta desativada | VotingTest e UserAdministrationTest                   |
| Índices parciais de eleição vigente e turno aberto | Eleições/turnos simultâneos                  | Migration, testes de constraints                      |
| Lock do turno e transação com voto + log           | Corrida com encerramento e votos duplicados  | Voto único, turno fechado, integridade das transações |
| UNIQUE por eleitor/turno e FK composta             | Voto duplicado ou candidato fora do turno    | VotingTest                                            |
| Triggers PostgreSQL de imutabilidade               | Alteração/exclusão de votos e auditoria      | UPDATE/DELETE rejeitados em VotingTest                |
| Props agregadas e cédulas sem identidade           | Vínculo nominal entre conta e candidato      | Testes de privacidade e auditoria                     |
| Confirmação explícita do voto                      | Voto sem ciência da definitividade           | Teste de confirmação e modal                          |
| Validação e recodificação de imagens               | Arquivos executáveis e imagens inválidas     | CandidatePhotoTest                                    |
| Sessões em PostgreSQL e proxy A/B                  | Queda de um processo PHP                     | Rodapé com APP_INSTANCE e roteiro A/B                 |
| pg_dump + pg_restore em banco separado             | Perda de dados e backup inválido             | Contagem/hash de votos e logs restaurados             |

CSRF nativo permanece ativo. React escapa conteúdo textual, Eloquent parametriza valores e os cookies de sessão usam HttpOnly/SameSite=Lax. Em produção, configure APP_DEBUG=false, HTTPS e SESSION_SECURE_COOKIE=true; não execute o seeder de demonstração.

## Sigilo e votos simulados

Todos os perfis ativos (eleitor, administrador e auditor) podem votar uma vez por turno. `participations` confirma quem participou; `votes` guarda candidato e código aleatório de 256 bits. Não existe chave ligando a conta à cédula. Cédulas não têm usuário, IP ou horário, e o comprovante da conta confirma somente participação. Logs e sessão não guardam o candidato ou o código da cédula.

O placar ao vivo permanece habilitado por decisão do usuário e permite inferir escolhas pela diferença de contagem. A separação impede a consulta relacional explícita, mas não oferece anonimato criptográfico contra quem controla o servidor/banco: requisições, metadados de transação, WAL e backups antigos podem permitir correlação. Backups anteriores à mudança ainda podem conter votos identificados; não devem ser apresentados como anônimos. Restaurá-los exige tratar esse legado antes da migração. A migração recusa votos identificados existentes para evitar conversão destrutiva silenciosa. Não existe conversão reversa capaz de recuperar a identidade de cédulas novas; reverter a migration descarta as novas tabelas de votos e participação.

Somente em `APP_ENV=local` ou `testing`, com turno aberto:

```powershell
# Listar IDs dos candidatos, sem criar votos
php artisan votacao:simular
# Adicionar 10 votos simulados ao candidato 2
php artisan votacao:simular 2 10
# Selecionar explicitamente o turno
php artisan votacao:simular 2 10 --turno=1
```

As simulações entram no placar e na apuração, mas aparecem sinalizadas no placar, no histórico e na auditoria. Não criam contas nem consomem a participação de pessoas reais. O comando bloqueia produção, quantidade inválida, turno fechado/agendado e candidato fora do turno.
