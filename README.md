*1. Banco de dados*
O PostgreSQL armazena usuários, eleições, candidatos, participações, votos e logs. Ele aplica regras que impedem votos duplicados e votos em candidatos fora do turno.

A gravação acontece em uma transação: participação, voto e auditoria são registrados juntos. Se alguma etapa falhar, tudo é desfeito. Bloqueios organizam requisições simultâneas para evitar inconsistências.

*2. Sistema de votação*
O administrador cria a eleição com três candidatos e define abertura imediata ou agendada. Qualquer conta ativa pode votar uma vez por turno, após confirmar a escolha.

O placar é atualizado a cada cinco segundos. Quando o administrador encerra o turno, o sistema calcula o resultado: líder único vence, empate permite outro turno e ausência de votos encerra a eleição como falha. Os resultados ficam no histórico.

*3. Segurança*
As senhas são armazenadas como hash, sem guardar a senha original. Existem limite de tentativas de login e opção de autenticação em duas etapas.

As permissões são verificadas no servidor: esconder botões não é a única proteção. Contas inativas são bloqueadas nas ações protegidas.

A participação fica separada da cédula, sem vínculo direto entre usuário e candidato. Porém, o placar ao vivo permite inferir escolhas pela variação da contagem; por isso, não garantimos anonimato absoluto.

*4. Auditoria*
Registramos ações importantes, como criação e encerramento de eleições, votos aceitos ou rejeitados, acessos negados e alterações de permissões. Os registros incluem responsável, data, resultado e IP nas requisições web.

Administradores e auditores podem consultar essas evidências. Após o encerramento, participantes e cédulas aparecem em listas separadas. Proteções no banco bloqueiam alterações e exclusões comuns de votos, participações e logs.

*5. Redundância e backup*
Temos duas instâncias da aplicação, A e B. Um proxy encaminha os acessos para uma instância disponível. Se A parar, B pode continuar atendendo, preservando sessões e votos porque ambas usam o mesmo banco.

O backup permite recuperar dados perdidos. Ele inclui banco e fotos, com restauração em banco separado e conferência de integridade.

A redundância atual protege contra a falha de uma instância, mas não contra a queda do banco ou da máquina inteira.
