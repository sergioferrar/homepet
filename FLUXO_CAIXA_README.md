# 💰 Nova Aba: Fluxo de Caixa

## 📊 Visão Geral

Foi adicionada uma nova aba **"Fluxo de Caixa"** na tela de Gestão Financeira que consolida TODOS os movimentos financeiros do sistema em um único lugar.

## 🎯 O que é incluído no Fluxo de Caixa?

### ✅ ENTRADAS (Créditos)
1. **Vendas do PDV** - Todas as vendas registradas no sistema PDV
2. **Recebimentos do Financeiro** - Lançamentos marcados como "ENTRADA"
3. **Entradas do Caixa** - Movimentos de entrada registrados manualmente no caixa

### ❌ SAÍDAS (Débitos)
1. **Despesas do Financeiro** - Lançamentos marcados como "SAIDA"
2. **Saídas do Caixa PDV** - Retiradas e pagamentos registrados no PDV
3. **Pagamentos diversos** - Qualquer saída registrada no sistema

## 📍 Localização

**Caminho**: Gestão Financeira → Aba "Fluxo de Caixa" (ao lado de "Inativos")

## 🎨 Recursos da Aba

### 1. Cards de Resumo
- **Total de Entradas**: Soma de todas as entradas do dia
- **Total de Saídas**: Soma de todas as saídas do dia
- **Saldo do Dia**: Diferença entre entradas e saídas (Entradas - Saídas)

### 2. Filtro por Data
- Permite visualizar o fluxo de caixa de qualquer dia
- Padrão: Data atual
- Atualização automática ao selecionar nova data

### 3. Listagem Detalhada
Cada movimento exibe:
- **Descrição**: O que foi registrado
- **Horário**: Hora exata do movimento
- **Origem**: De onde veio (PDV, Financeiro, Caixa)
- **Método**: Forma de pagamento (Dinheiro, Cartão, PIX, etc)
- **Tipo**: ENTRADA ou SAÍDA (com badge colorido)
- **Valor**: Formatado em reais com sinal + ou -

### 4. Busca e Paginação
- Campo de busca para filtrar movimentos
- Paginação automática (10 itens por página)
- Busca por: descrição, origem, método ou valor

## 🎨 Visual

### Cores e Indicadores
- **Verde**: Entradas (positivo)
- **Vermelho**: Saídas (negativo)
- **Borda lateral**: Verde para entradas, vermelha para saídas
- **Badges**: Identificam claramente o tipo de movimento

### Layout Responsivo
- Cards adaptáveis para mobile e desktop
- Informações organizadas de forma clara
- Fácil leitura e navegação

## 💡 Casos de Uso

### 1. Fechamento de Caixa Diário
```
Acesse: Gestão Financeira → Fluxo de Caixa
Visualize: Todas as entradas e saídas do dia
Confira: O saldo final do dia
```

### 2. Auditoria de Movimentos
```
Filtre: Selecione uma data específica
Busque: Digite uma palavra-chave
Analise: Todos os movimentos relacionados
```

### 3. Conciliação Financeira
```
Compare: Entradas vs Saídas
Verifique: Origem de cada movimento
Valide: Métodos de pagamento utilizados
```

## 🔄 Integração com Outros Módulos

### PDV (Ponto de Venda)
- ✅ Vendas registradas aparecem como ENTRADA no Financeiro
- ✅ Saídas manuais de caixa aparecem como SAIDA
- ✅ Identificadas com origem "PDV"

### Financeiro
- ✅ Lançamentos tipo ENTRADA são incluídos (vendas, recebimentos)
- ✅ Lançamentos tipo SAIDA são incluídos (despesas, pagamentos)
- ✅ Identificados com origem "Financeiro" ou "PDV"

### Caixa Movimento
- ✅ Apenas SAÍDAS manuais do caixa
- ✅ Identificadas com origem "PDV - Saída Manual"
- ✅ Evita duplicação com vendas do PDV

## ⚠️ Importante: Evitando Duplicação

Para evitar que vendas apareçam duplicadas no fluxo de caixa:
- **Vendas do PDV**: Registradas APENAS no Financeiro (tipo ENTRADA)
- **Saídas do PDV**: Registradas APENAS no CaixaMovimento (tipo SAIDA)
- **Resultado**: Cada transação aparece uma única vez no fluxo de caixa

## 📊 Exemplo de Visualização

```
Data: 30/10/2025

┌─────────────────────────────────────────┐
│ Total Entradas: R$ 2.500,00            │
│ Total Saídas:   R$ 800,00              │
│ Saldo do Dia:   R$ 1.700,00            │
└─────────────────────────────────────────┘

Movimentos:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
[ENTRADA] Venda PDV - João Silva
09:30 | PDV - Caixa | Dinheiro
+ R$ 150,00

[ENTRADA] Recebimento Consulta
10:15 | Financeiro | Cartão
+ R$ 200,00

[SAIDA] Compra de Material
11:00 | PDV - Caixa | Dinheiro
- R$ 50,00

[ENTRADA] Venda PDV - Maria Santos
14:30 | PDV - Caixa | PIX
+ R$ 300,00
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

## 🚀 Benefícios

1. **Visão Consolidada**: Todos os movimentos em um só lugar
2. **Transparência Total**: Rastreamento completo de entradas e saídas
3. **Facilita Auditoria**: Histórico detalhado e pesquisável
4. **Controle Financeiro**: Saldo em tempo real
5. **Tomada de Decisão**: Dados claros para gestão

## 🔧 Tecnologias Utilizadas

- **Backend**: Symfony PHP
- **Frontend**: Bootstrap 5, JavaScript
- **Banco de Dados**: Doctrine ORM
- **Componentes**: TabelaDefault.js (paginação e busca)

## 📝 Observações

- Os dados são filtrados por estabelecimento (multi-tenant)
- Apenas movimentos do dia selecionado são exibidos
- O cálculo do saldo é automático e em tempo real
- Todos os valores são formatados em Real (R$)
- A ordenação é cronológica (mais antigos primeiro)

---

**Desenvolvido para**: Sistema HomePet - Gestão Veterinária
**Data**: 30/10/2025
