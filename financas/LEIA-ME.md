# Finanças da Família — Guia de instalação na Hostinger

## O que este sistema faz
- Cada família tem um "código de convite" — quem se cadastra com o código entra na mesma família e todos veem os mesmos lançamentos.
- Cadastro de receitas e despesas, por categoria.
- Painel com o resumo do mês (receitas, despesas, saldo).
- Relatórios com gráficos (evolução dos últimos 6 meses e despesas por categoria).

## Passo 1 — Criar o banco de dados na Hostinger
1. Entre no **hPanel** da Hostinger.
2. Vá em **Bancos de Dados → Bancos de Dados MySQL**.
3. Crie um novo banco de dados. Anote o **nome do banco**, o **usuário** e a **senha** (a Hostinger gera nomes parecidos com `u123456789_financas`).
4. Clique em **phpMyAdmin** ao lado do banco criado.
5. Na aba **Importar**, selecione o arquivo `schema.sql` (está nesta pasta) e clique em **Executar**. Isso cria todas as tabelas automaticamente.

## Passo 2 — Configurar a conexão
1. Abra o arquivo `config.php`.
2. Substitua os valores de `DB_NAME`, `DB_USER` e `DB_PASS` pelos dados do banco que você criou no Passo 1.
3. Normalmente `DB_HOST` continua sendo `localhost` na Hostinger — não precisa mudar.

## Passo 3 — Enviar os arquivos para o servidor
1. No hPanel, vá em **Arquivos → Gerenciador de Arquivos** (ou use um cliente FTP como o FileZilla).
2. Entre na pasta `public_html` (é a pasta que fica visível no seu domínio).
3. Envie **todo o conteúdo** desta pasta `financas` para dentro de `public_html` (os arquivos ficam direto na raiz, não dentro de uma subpasta chamada "financas").

Estrutura esperada dentro de `public_html`:
```
public_html/
  auth/
  css/
  includes/
  config.php
  index.php
  transacoes.php
  transacao_form.php
  categorias.php
  relatorios.php
  schema.sql   (pode apagar depois de importar)
```

## Passo 4 — Testar
1. Acesse `https://seudominio.com/auth/register.php`
2. Crie sua conta escolhendo **"Criar uma nova família"**.
3. Anote o código de convite mostrado no painel — use-o para os outros membros da família se cadastrarem em `register.php`, escolhendo **"Entrar em uma família existente"**.

## Se algo der errado
- **Tela em branco ou erro 500**: normalmente é um dado errado no `config.php`. Confira usuário, senha e nome do banco no hPanel.
- **"Erro ao conectar ao banco de dados"**: o `DB_HOST`, `DB_USER` ou `DB_PASS` estão incorretos.
- A Hostinger exige PHP 7.4 ou superior (esse projeto usa recursos simples, compatível com qualquer versão recente). Você pode conferir/ajustar a versão do PHP em **hPanel → Avançado → PHP Configuration**.

## Próximos passos possíveis (quando quiser evoluir)
- Exportar relatórios em PDF ou Excel.
- Definir metas/orçamento mensal por categoria.
- Anexar comprovantes (upload de imagem) em cada lançamento.
- App mobile consumindo os mesmos dados via uma API.
