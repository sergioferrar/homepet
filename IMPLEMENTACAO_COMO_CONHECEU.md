# Implementação: "Como Nos Conheceu"

## Resumo
Foi implementado um sistema completo para capturar e reportar como os clientes conheceram a empresa, incluindo:

1. **Novo campo no cadastro de clientes**
2. **Relatório gerencial com gráficos**
3. **Integração no dashboard**

## Arquivos Modificados

### 1. Entity Cliente (`src/Entity/Cliente.php`)
- Adicionado propriedade `comoConheceu`
- Adicionados métodos `getComoConheceu()` e `setComoConheceu()`

### 2. Repository Cliente (`src/Repository/ClienteRepository.php`)
- Atualizado método `save()` para incluir o novo campo
- Atualizado método `update()` para incluir o novo campo
- Adicionado método `relatorioComoConheceu()` para gerar dados do relatório

### 3. Controller Cliente (`src/Controller/ClienteController.php`)
- Modificados métodos `novo()` e `editar()` para processar o novo campo

### 4. Controller Relatório (`src/Controller/Clinica/RelatorioController.php`)
- Adicionado método `comoConheceu()` para gerar o relatório
- Adicionado import da Entity Cliente

### 5. Templates
- **`templates/cliente/novo.html.twig`**: Adicionado select com opções
- **`templates/cliente/editar.html.twig`**: Adicionado select com valor atual
- **`templates/clinica/relatorio_como_conheceu.html.twig`**: Novo template do relatório
- **`templates/clinica/dashboard.html.twig`**: Adicionado link para o relatório

## Arquivos Criados

### 1. Template do Relatório (`templates/clinica/relatorio_como_conheceu.html.twig`)
- Filtros por período
- Gráfico de pizza interativo (Chart.js)
- Tabela com percentuais
- Função de impressão
- Design responsivo

### 2. Script de Migration (`adicionar_como_conheceu.sql`)
- Adiciona coluna `como_conheceu` na tabela cliente
- Verificação se coluna já existe
- Comentários e instruções de uso

## Funcionalidades Implementadas

### Campo no Cadastro
- **Localização**: Formulários de cadastro e edição de cliente
- **Tipo**: Select com opções pré-definidas
- **Opções disponíveis**:
  - Google
  - Instagram
  - Facebook
  - Indicação de amigos
  - Placa/Fachada
  - WhatsApp
  - TikTok
  - Panfleto/Folheto
  - Outro

### Relatório Gerencial
- **Rota**: `/dashboard/clinica/relatorios/como-conheceu`
- **Acesso**: Apenas usuários com permissão financeira
- **Recursos**:
  - Filtro por período (padrão: últimos 30 dias)
  - Gráfico de pizza com distribuição
  - Tabela com quantidades e percentuais
  - Total de clientes no período
  - Lista de clientes por canal (para impressão)
  - Função de impressão formatada

### Integração no Dashboard
- **Localização**: Dashboard da clínica, seção "Ações Rápidas"
- **Botão**: "Como Conheceram" (verde, ícone de gráfico)
- **Restrição**: Apenas visível para usuários com permissão financeira

## Como Usar

### 1. Executar Migration
```sql
-- No banco de cada estabelecimento (homepet_XXX)
ALTER TABLE cliente 
ADD COLUMN IF NOT EXISTS como_conheceu VARCHAR(255) NULL 
COMMENT 'Canal por onde o cliente conheceu a empresa';
```

### 2. Cadastrar Clientes
1. Acessar "Clientes" > "Novo Cliente"
2. Preencher dados normalmente
3. Selecionar opção em "Como nos conheceu?"
4. Salvar

### 3. Visualizar Relatório
1. Ir para o Dashboard da Clínica
2. Clicar em "Como Conheceram" (seção Ações Rápidas)
3. Aplicar filtros de período se necessário
4. Visualizar gráfico e dados
5. Imprimir se necessário

## Notas Técnicas

- Campo é **opcional** (nullable)
- Relatório funciona mesmo com registros antigos (sem o campo preenchido)
- Registros sem o campo aparecem como "Não informado"
- Gráfico usa Chart.js (CDN)
- Layout responsivo com Bootstrap
- Função de impressão remove elementos desnecessários

## Benefícios

1. **Análise de Marketing**: Identificar quais canais trazem mais clientes
2. **ROI de Campanhas**: Medir efetividade de investimentos em marketing
3. **Otimização de Recursos**: Focar nos canais mais eficazes
4. **Tomada de Decisão**: Dados concretos para estratégias futuras
5. **Histórico**: Acompanhar tendências ao longo do tempo

## Manutenção

- Para adicionar novas opções: editar os templates de formulário
- Para modificar o gráfico: editar o JavaScript no template do relatório
- Para ajustar períodos padrão: modificar o controller do relatório