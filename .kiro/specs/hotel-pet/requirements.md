# Requirements Document - Sistema de Hospedagem de Cães (Hotel Pet)

## Introduction

Este documento define os requisitos para um sistema completo de gerenciamento de hospedagem de cães (Hotel Pet), incluindo reservas, check-in/check-out, controle de boxes, alimentação, medicação, atividades, e relatórios financeiros.

## Glossary

- **Sistema**: Sistema de gerenciamento de hospedagem de cães
- **Hospedagem**: Período em que um pet fica hospedado no hotel
- **Box**: Espaço físico onde o pet fica hospedado
- **Check-in**: Processo de entrada do pet no hotel
- **Check-out**: Processo de saída do pet do hotel
- **Diária**: Valor cobrado por dia de hospedagem
- **Pacote**: Conjunto de serviços incluídos na hospedagem
- **Atividade**: Serviço adicional oferecido durante a hospedagem (banho, passeio, etc.)

## Requirements

### Requirement 1: Gestão de Reservas

**User Story:** Como atendente do hotel, eu quero gerenciar reservas de hospedagem, para que eu possa organizar a ocupação dos boxes e garantir disponibilidade.

#### Acceptance Criteria

1. WHEN o atendente cria uma nova reserva, THE Sistema SHALL solicitar pet, datas de entrada/saída, tipo de box e observações
2. WHEN o atendente seleciona as datas, THE Sistema SHALL exibir boxes disponíveis para o período
3. WHEN o atendente confirma a reserva, THE Sistema SHALL bloquear o box para o período selecionado
4. WHEN o atendente visualiza o calendário, THE Sistema SHALL exibir todas as reservas com status (confirmada, em andamento, concluída, cancelada)
5. WHERE uma reserva está próxima da data de entrada, THE Sistema SHALL enviar notificação ao atendente

### Requirement 2: Check-in e Check-out

**User Story:** Como atendente do hotel, eu quero realizar check-in e check-out de pets, para que eu possa controlar a entrada e saída dos hóspedes.

#### Acceptance Criteria

1. WHEN o atendente realiza check-in, THE Sistema SHALL registrar data/hora de entrada, box atribuído e condições do pet
2. WHEN o atendente realiza check-in, THE Sistema SHALL permitir upload de fotos do pet na entrada
3. WHEN o atendente realiza check-out, THE Sistema SHALL calcular valor total baseado em diárias e serviços adicionais
4. WHEN o atendente realiza check-out, THE Sistema SHALL gerar relatório de estadia com todas as atividades realizadas
5. WHERE o pet possui medicação pendente, THE Sistema SHALL alertar antes de permitir check-out

### Requirement 3: Controle de Boxes

**User Story:** Como gerente do hotel, eu quero gerenciar os boxes disponíveis, para que eu possa otimizar a ocupação e organizar os pets por porte.

#### Acceptance Criteria

1. WHEN o gerente cadastra um box, THE Sistema SHALL solicitar número, tipo (pequeno, médio, grande), localização e status
2. WHEN o gerente visualiza os boxes, THE Sistema SHALL exibir status atual (disponível, ocupado, manutenção, reservado)
3. WHEN o gerente marca box para manutenção, THE Sistema SHALL bloquear novas reservas para aquele box
4. WHEN o atendente busca box disponível, THE Sistema SHALL filtrar por porte do pet e período solicitado
5. WHERE um box está ocupado, THE Sistema SHALL exibir informações do pet hospedado

### Requirement 4: Controle de Alimentação

**User Story:** Como cuidador do hotel, eu quero registrar a alimentação dos pets, para que eu possa garantir que todos sejam alimentados corretamente.

#### Acceptance Criteria

1. WHEN o cuidador registra alimentação, THE Sistema SHALL solicitar pet, horário, tipo de ração e quantidade
2. WHEN o cuidador visualiza agenda de alimentação, THE Sistema SHALL exibir todos os pets com horários programados
3. WHEN o horário de alimentação se aproxima, THE Sistema SHALL enviar notificação ao cuidador
4. WHEN o cuidador confirma alimentação, THE Sistema SHALL registrar data/hora e responsável
5. WHERE um pet possui dieta especial, THE Sistema SHALL destacar as instruções de alimentação

### Requirement 5: Controle de Medicação

**User Story:** Como veterinário do hotel, eu quero gerenciar medicações dos pets hospedados, para que eu possa garantir tratamento adequado.

#### Acceptance Criteria

1. WHEN o veterinário prescreve medicação, THE Sistema SHALL solicitar medicamento, dosagem, frequência e duração
2. WHEN o horário de medicação se aproxima, THE Sistema SHALL enviar notificação ao responsável
3. WHEN o responsável administra medicação, THE Sistema SHALL registrar data/hora, dosagem e observações
4. WHEN o veterinário visualiza histórico, THE Sistema SHALL exibir todas as medicações administradas
5. WHERE uma medicação não foi administrada no horário, THE Sistema SHALL alertar com destaque vermelho

### Requirement 6: Atividades e Serviços Adicionais

**User Story:** Como atendente do hotel, eu quero registrar atividades e serviços adicionais, para que eu possa cobrar corretamente e manter histórico.

#### Acceptance Criteria

1. WHEN o atendente registra atividade, THE Sistema SHALL solicitar tipo (banho, tosa, passeio, recreação), data/hora e valor
2. WHEN o atendente agenda banho, THE Sistema SHALL adicionar ao calendário de atividades
3. WHEN o cuidador conclui atividade, THE Sistema SHALL permitir adicionar fotos e observações
4. WHEN o atendente visualiza serviços do pet, THE Sistema SHALL exibir lista completa com valores
5. WHERE uma atividade foi agendada, THE Sistema SHALL enviar notificação ao responsável

### Requirement 7: Relatórios e Dashboards

**User Story:** Como gerente do hotel, eu quero visualizar relatórios e indicadores, para que eu possa acompanhar o desempenho do negócio.

#### Acceptance Criteria

1. WHEN o gerente acessa dashboard, THE Sistema SHALL exibir taxa de ocupação atual e previsão
2. WHEN o gerente acessa dashboard, THE Sistema SHALL exibir receita do mês e comparativo com mês anterior
3. WHEN o gerente gera relatório financeiro, THE Sistema SHALL detalhar receitas por tipo (diárias, serviços, medicação)
4. WHEN o gerente gera relatório de ocupação, THE Sistema SHALL exibir histórico de ocupação por período
5. WHERE a taxa de ocupação está baixa, THE Sistema SHALL sugerir promoções ou descontos

### Requirement 8: Galeria de Fotos

**User Story:** Como tutor do pet, eu quero visualizar fotos do meu pet durante a hospedagem, para que eu possa acompanhar como ele está.

#### Acceptance Criteria

1. WHEN o cuidador tira foto do pet, THE Sistema SHALL associar foto à hospedagem atual
2. WHEN o cuidador adiciona foto, THE Sistema SHALL permitir adicionar legenda e data/hora
3. WHEN o tutor acessa portal, THE Sistema SHALL exibir galeria de fotos do pet
4. WHEN o atendente gera relatório de estadia, THE Sistema SHALL incluir fotos selecionadas
5. WHERE o tutor solicita, THE Sistema SHALL enviar fotos por email ou WhatsApp

### Requirement 9: Pacotes e Promoções

**User Story:** Como gerente do hotel, eu quero criar pacotes e promoções, para que eu possa atrair mais clientes e aumentar a ocupação.

#### Acceptance Criteria

1. WHEN o gerente cria pacote, THE Sistema SHALL solicitar nome, descrição, serviços incluídos e valor
2. WHEN o gerente cria promoção, THE Sistema SHALL solicitar período de validade e desconto
3. WHEN o atendente cria reserva, THE Sistema SHALL exibir pacotes disponíveis
4. WHEN o atendente aplica pacote, THE Sistema SHALL calcular valor com desconto automaticamente
5. WHERE um pacote inclui serviços, THE Sistema SHALL registrar automaticamente no check-in

### Requirement 10: Comunicação com Tutores

**User Story:** Como atendente do hotel, eu quero enviar atualizações aos tutores, para que eles fiquem tranquilos sobre seus pets.

#### Acceptance Criteria

1. WHEN o cuidador adiciona atualização, THE Sistema SHALL permitir enviar por email ou WhatsApp
2. WHEN o atendente realiza check-in, THE Sistema SHALL enviar confirmação ao tutor
3. WHEN o cuidador adiciona foto, THE Sistema SHALL permitir compartilhar com tutor
4. WHEN o check-out se aproxima, THE Sistema SHALL enviar lembrete ao tutor
5. WHERE o pet precisa de atenção especial, THE Sistema SHALL permitir envio de alerta ao tutor

## Prioridades

1. **Alta**: Gestão de Reservas, Check-in/Check-out, Controle de Boxes
2. **Alta**: Controle de Alimentação e Medicação
3. **Média**: Atividades e Serviços Adicionais, Relatórios
4. **Média**: Galeria de Fotos, Comunicação com Tutores
5. **Baixa**: Pacotes e Promoções
