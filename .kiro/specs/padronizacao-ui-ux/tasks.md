# Implementation Plan - Padronização UI/UX

- [x] 1. Criar arquivo de estilos globais padronizados
  - Criar arquivo `public/css/design-system.css` com design tokens e classes reutilizáveis
  - Incluir variáveis CSS para cores, espaçamentos, sombras e bordas
  - Adicionar classes utilitárias para cards, botões e animações
  - Importar o arquivo em `templates/base.html.twig`
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 5.1, 5.2, 5.3, 5.4_

- [x] 2. Padronizar tela de Dashboard (Clínica)
  - [x] 2.1 Atualizar welcome card com gradiente padronizado
    - Aplicar gradiente primário no card de boas-vindas
    - Adicionar ícones e data/hora no formato padrão
    - _Requirements: 1.1, 5.1_
  
  - [x] 2.2 Padronizar cards de estatísticas
    - Aplicar gradientes específicos para cada tipo de estatística
    - Centralizar ícones, números e textos
    - Garantir altura uniforme dos cards
    - _Requirements: 1.4, 5.2_
  
  - [x] 2.3 Padronizar cards de listagem
    - Aplicar estilo consistente nos cards de "Últimos atendimentos", "Animais cadastrados", etc.
    - Adicionar hover effect padronizado
    - _Requirements: 4.1, 4.2_

- [x] 3. Padronizar tela de Clientes
  - [x] 3.1 Atualizar header da página
    - Posicionar botão "Novo Cliente" no canto superior direito
    - Adicionar ícone padronizado
    - _Requirements: 1.2, 2.1, 2.2_
  
  - [x] 3.2 Padronizar campo de busca
    - Posicionar abaixo do header
    - Aplicar estilo consistente
    - _Requirements: 1.5, 4.5_
  
  - [x] 3.3 Padronizar cards de clientes
    - Aplicar sombra, bordas arredondadas e hover effect
    - Garantir ícones consistentes para CPF, email, telefone
    - _Requirements: 4.1, 4.2, 4.3_
  
  - [x] 3.4 Verificar paginação
    - Garantir que usa TabelaDefault.js
    - Validar 5 itens por página
    - _Requirements: 4.4_

- [x] 4. Padronizar tela de Pets
  - [x] 4.1 Atualizar header com botão "Novo Pet" à direita
    - _Requirements: 1.2, 2.1_
  
  - [x] 4.2 Padronizar cards de pets
    - Aplicar estilo consistente
    - Adicionar hover effect
    - _Requirements: 4.1, 4.2_
  
  - [x] 4.3 Padronizar busca e paginação
    - _Requirements: 1.5, 4.4, 4.5_

- [x] 5. Padronizar tela de Agendamentos
  - [x] 5.1 Remover botão "Novo Agendamento" do header
    - Manter apenas o calendário com funcionalidade integrada
    - _Requirements: 3.1, 3.2, 3.3_
  
  - [x] 5.2 Padronizar visualização do calendário
    - Aplicar cores e estilos consistentes
    - _Requirements: 5.1, 5.4_

- [x] 6. Padronizar tela de Serviços
  - [x] 6.1 Atualizar header com botão "Novo Serviço" à direita
    - _Requirements: 1.2, 2.1_
  
  - [x] 6.2 Padronizar cards de serviços
    - Aplicar estilo consistente
    - Adicionar hover effect
    - _Requirements: 4.1, 4.2_
  
  - [x] 6.3 Validar busca e filtro funcionando
    - Garantir que busca e filtro por tipo funcionam corretamente
    - _Requirements: 4.5_

- [x] 7. Padronizar tela de Orçamentos
  - [x] 7.1 Atualizar header com botão "Novo Orçamento" à direita
    - _Requirements: 1.2, 2.1_
  
  - [x] 7.2 Padronizar listagem de orçamentos
    - Aplicar cards com estilo consistente
    - Adicionar paginação se necessário
    - _Requirements: 4.1, 4.2, 4.4_

- [x] 8. Padronizar tela de Detalhes do Pet
  - [x] 8.1 Adicionar botão "Voltar" no canto superior esquerdo
    - _Requirements: 1.3, 2.3, 6.1_
  
  - [x] 8.2 Padronizar abas (tabs)
    - Aplicar estilo consistente do Bootstrap
    - _Requirements: 6.5_
  
  - [x] 8.3 Padronizar modais de venda/serviço
    - Garantir backdrop estático
    - Aplicar modal de confirmação ao fechar
    - _Requirements: 6.4, 7.4_

- [x] 9. Padronizar tela de Internações
  - [x] 9.1 Atualizar header da ficha de internação
    - Adicionar botão "Voltar" à esquerda
    - _Requirements: 1.3, 2.3_
  
  - [x] 9.2 Padronizar cards de informações
    - Aplicar estilo consistente
    - _Requirements: 4.1, 4.2_
  
  - [x] 9.3 Padronizar timeline de eventos
    - Aplicar cores e ícones consistentes
    - _Requirements: 5.4_

- [x] 10. Padronizar formulários em todas as telas
  - [x] 10.1 Adicionar asterisco (*) em campos obrigatórios
    - _Requirements: 7.2_
  
  - [x] 10.2 Padronizar botões de formulário
    - Posicionar "Cancelar" à esquerda e "Salvar" à direita
    - Aplicar cores consistentes
    - _Requirements: 2.1, 2.2, 2.5_
  
  - [x] 10.3 Adicionar feedback visual em submissões
    - Usar toasts para sucesso/erro
    - _Requirements: 7.3_
  
  - [x] 10.4 Garantir modal de confirmação ao cancelar com dados
    - _Requirements: 7.4_

- [x] 11. Implementar responsividade
  - [x] 11.1 Testar todas as telas em mobile
    - Validar que cards empilham verticalmente
    - _Requirements: 8.1, 8.2_
  
  - [x] 11.2 Ajustar botões para mobile
    - Empilhar verticalmente quando necessário
    - _Requirements: 8.5_
  
  - [x] 11.3 Ajustar formulários para mobile
    - Campos com largura total
    - _Requirements: 8.3_

- [x] 12. Validação final e testes
  - [x] 12.1 Revisar todas as telas padronizadas
    - Usar checklist de validação do design document
    - Verificar consistência visual
    - _Requirements: Todos_
  
  - [x] 12.2 Testar navegação entre telas
    - Validar que botões "Voltar" funcionam corretamente
    - Verificar que não há botões duplicados
    - _Requirements: 3.3, 6.1, 6.2_
  
  - [x] 12.3 Testar funcionalidades
    - Validar que busca, filtros e paginação funcionam
    - Verificar que modais abrem e fecham corretamente
    - _Requirements: 4.5, 6.4_

## Notas de Implementação

- Começar pela criação do arquivo de estilos globais (Task 1)
- Implementar telas em ordem de prioridade: Dashboard → Clientes → Pets → Agendamentos → Serviços
- Testar cada tela após padronização antes de prosseguir
- Manter backup das telas originais caso seja necessário reverter
- Usar componentes reutilizáveis sempre que possível
- Validar que funcionalidades existentes não foram quebradas

## Ordem de Execução Recomendada

1. Task 1 (Estilos globais) - Base para todas as outras
2. Task 2 (Dashboard) - Tela principal, define o padrão
3. Task 3 (Clientes) - Tela de listagem padrão
4. Task 5 (Agendamentos) - Remove redundância importante
5. Task 6 (Serviços) - Já foi parcialmente trabalhada
6. Task 4, 7, 8, 9 (Outras telas)
7. Task 10 (Formulários)
8. Task 11 (Responsividade)
9. Task 12 (Validação final)
