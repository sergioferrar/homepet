# Design Document - Sistema de Hospedagem de Cães (Hotel Pet)

## Overview

Sistema completo de gerenciamento de hotel para pets com foco em hospedagem de cães, incluindo reservas, controle de boxes, alimentação, medicação, atividades e comunicação com tutores.

## Architecture

### Database Schema

```sql
-- Tabela de Hospedagens
hospedagem (
    id, pet_id, box_id, reserva_id,
    data_entrada, data_saida_prevista, data_saida_real,
    valor_diaria, valor_total, status,
    observacoes_entrada, observacoes_saida,
    created_at, updated_at
)

-- Tabela de Reservas
reserva (
    id, pet_id, cliente_id, box_id,
    data_entrada, data_saida,
    valor_estimado, status,
    observacoes, created_at
)

-- Tabela de Boxes
box (
    id, numero, tipo, localizacao,
    status, capacidade, valor_diaria,
    observacoes
)

-- Tabela de Alimentação
hospedagem_alimentacao (
    id, hospedagem_id, data_hora_programada,
    data_hora_realizada, tipo_racao, quantidade,
    responsavel_id, observacoes
)

-- Tabela de Medicação
hospedagem_medicacao (
    id, hospedagem_id, medicamento,
    dosagem, frequencia_horas,
    data_hora_inicio, data_hora_fim,
    observacoes
)

-- Tabela de Administração de Medicação
hospedagem_medicacao_admin (
    id, medicacao_id, data_hora_programada,
    data_hora_realizada, responsavel_id,
    observacoes
)

-- Tabela de Atividades
hospedagem_atividade (
    id, hospedagem_id, tipo,
    data_hora, valor, status,
    responsavel_id, observacoes
)

-- Tabela de Fotos
hospedagem_foto (
    id, hospedagem_id, caminho,
    legenda, data_hora, tipo
)

-- Tabela de Pacotes
pacote (
    id, nome, descricao, valor,
    servicos_inclusos, ativo
)
```

## Components and Interfaces

### 1. Dashboard Hotel Pet

```twig
<!-- Cards de Estatísticas -->
- Boxes Ocupados / Total
- Check-ins Hoje
- Check-outs Hoje
- Receita do Mês

<!-- Calendário de Ocupação -->
- Visualização mensal com boxes
- Cores por status (disponível, ocupado, reservado, manutenção)

<!-- Alertas -->
- Medicações pendentes
- Alimentações pendentes
- Check-outs do dia
```

### 2. Gestão de Reservas

```twig
<!-- Formulário de Nova Reserva -->
- Seleção de Pet/Cliente
- Datas de entrada e saída
- Seleção de box (filtrado por porte e disponibilidade)
- Pacote/Promoção (opcional)
- Observações

<!-- Calendário de Reservas -->
- Visualização por mês/semana
- Drag and drop para reagendar
- Filtros por status
```

### 3. Check-in

```twig
<!-- Formulário de Check-in -->
- Dados da reserva
- Confirmação de box
- Upload de fotos do pet
- Avaliação de condições (peso, comportamento)
- Instruções especiais (alimentação, medicação)
- Assinatura digital do tutor
```

### 4. Check-out

```twig
<!-- Formulário de Check-out -->
- Resumo da estadia
- Lista de serviços adicionais
- Cálculo de valor total
- Upload de fotos finais
- Relatório de estadia (PDF)
- Processamento de pagamento
```

### 5. Controle de Boxes

```twig
<!-- Grid de Boxes -->
- Cards com status visual
- Informações do pet hospedado
- Ações rápidas (limpar, manutenção)
- Filtros por tipo e status
```

### 6. Agenda de Alimentação

```twig
<!-- Timeline Diária -->
- Lista de pets com horários
- Checkbox para confirmar alimentação
- Observações rápidas
- Alertas para atrasos
```

### 7. Agenda de Medicação

```twig
<!-- Timeline Diária -->
- Lista de medicações programadas
- Checkbox para confirmar administração
- Dosagem e instruções
- Alertas críticos
```

### 8. Galeria de Fotos

```twig
<!-- Grid de Fotos -->
- Upload múltiplo
- Legendas e tags
- Compartilhamento com tutores
- Seleção para relatório
```

## Data Models

### Hospedagem Model
```php
class Hospedagem {
    private int $id;
    private int $petId;
    private int $boxId;
    private ?int $reservaId;
    private DateTime $dataEntrada;
    private DateTime $dataSaidaPrevista;
    private ?DateTime $dataSaidaReal;
    private float $valorDiaria;
    private float $valorTotal;
    private string $status; // 'ativa', 'concluida', 'cancelada'
}
```

### Box Model
```php
class Box {
    private int $id;
    private string $numero;
    private string $tipo; // 'pequeno', 'medio', 'grande'
    private string $status; // 'disponivel', 'ocupado', 'manutencao', 'reservado'
    private float $valorDiaria;
}
```

## Error Handling

- Validar disponibilidade de box antes de confirmar reserva
- Alertar sobre conflitos de horários
- Validar datas (entrada < saída)
- Verificar medicações pendentes antes de check-out

## Testing Strategy

- Testar fluxo completo: Reserva → Check-in → Atividades → Check-out
- Validar cálculos de valores
- Testar notificações de alimentação e medicação
- Validar upload e exibição de fotos

## Implementation Guidelines

### Cores do Hotel Pet

```css
--hotel-primary: #FF6B6B;
--hotel-secondary: #4ECDC4;
--hotel-success: #95E1D3;
--hotel-warning: #FFE66D;
--hotel-danger: #FF6B6B;
```

### Ícones

- Hospedagem: 🏠
- Box: 🏠
- Alimentação: 🍖
- Medicação: 💊
- Atividade: 🎾
- Foto: 📸
- Check-in: ✅
- Check-out: 🚪
