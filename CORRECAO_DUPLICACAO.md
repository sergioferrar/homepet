# 🔧 Correção: Duplicação no Fluxo de Caixa

## 🐛 Problema Identificado

Quando uma venda era registrada no PDV, ela aparecia **2 vezes** no Fluxo de Caixa:

```
1. Venda PDV - Cliente | 1 item(ns)
   10:46 | PDV | dinheiro
   + R$ 130,00 ENTRADA

2. Venda PDV - Cliente | 1 item(ns)  ← DUPLICADO
   10:46 | PDV - Caixa | Caixa
   + R$ 130,00 ENTRADA
```

## 🔍 Causa Raiz

O código estava registrando a mesma venda em **dois lugares**:

1. **Tabela Financeiro** (tipo ENTRADA)
2. **Tabela CaixaMovimento** (tipo ENTRADA)

Ambas as tabelas eram consultadas pelo Fluxo de Caixa, resultando em duplicação.

## ✅ Solução Implementada

### 1. Removida Duplicação no Registro de Vendas

**Arquivo**: `src/Controller/PdvController.php`

**Antes**:
```php
$fin = new Financeiro();
// ... configuração do financeiro
$em->persist($fin);

// ❌ Registrava também no CaixaMovimento
$caixaMov = new CaixaMovimento();
$caixaMov->setDescricao($descricaoCompleta);
$caixaMov->setValor($dados['total']);
$caixaMov->setTipo('ENTRADA');
$em->persist($caixaMov);
```

**Depois**:
```php
$fin = new Financeiro();
// ... configuração do financeiro
$em->persist($fin);

// ✅ NÃO registra no CaixaMovimento
// O Financeiro já é capturado no fluxo de caixa
```

### 2. Ajustado Fluxo de Caixa para Buscar Apenas Saídas do Caixa

**Arquivo**: `src/Controller/FinanceiroController.php`

**Antes**:
```php
// Buscava TODAS as movimentações do caixa (entradas e saídas)
$movimentosCaixa = $em->getRepository(\App\Entity\CaixaMovimento::class)
    ->createQueryBuilder('c')
    ->where('c.estabelecimentoId = :estab')
    ->andWhere('c.data BETWEEN :inicio AND :fim')
    // ... sem filtro de tipo
```

**Depois**:
```php
// Busca APENAS SAÍDAS do caixa
$movimentosCaixa = $em->getRepository(\App\Entity\CaixaMovimento::class)
    ->createQueryBuilder('c')
    ->where('c.estabelecimentoId = :estab')
    ->andWhere('c.data BETWEEN :inicio AND :fim')
    ->andWhere('c.tipo = :tipo')  // ✅ Filtro adicionado
    ->setParameter('tipo', 'SAIDA')
```

## 📊 Fluxo Correto Agora

### Venda no PDV
```
✅ Registra em: Financeiro (tipo ENTRADA)
❌ NÃO registra em: CaixaMovimento
📍 Aparece no Fluxo de Caixa: 1 vez (origem "PDV")
```

### Saída Manual de Caixa
```
✅ Registra em: CaixaMovimento (tipo SAIDA)
❌ NÃO registra em: Financeiro (opcional)
📍 Aparece no Fluxo de Caixa: 1 vez (origem "PDV - Saída Manual")
```

### Lançamento Financeiro Normal
```
✅ Registra em: Financeiro (tipo ENTRADA ou SAIDA)
❌ NÃO registra em: CaixaMovimento
📍 Aparece no Fluxo de Caixa: 1 vez (origem "Financeiro")
```

## 🎯 Resultado

Agora cada transação aparece **apenas 1 vez** no Fluxo de Caixa:

```
✅ Venda PDV - Renata Maria Otoni de Assis | 1 item(ns)
   10:46 | PDV | dinheiro
   + R$ 130,00 ENTRADA
```

## 📋 Tabelas e Suas Funções

| Tabela | Função | Aparece no Fluxo? |
|--------|--------|-------------------|
| **Financeiro** | Registra todas as entradas e saídas financeiras | ✅ Sim (ENTRADA e SAIDA) |
| **CaixaMovimento** | Registra apenas saídas manuais do caixa PDV | ✅ Sim (apenas SAIDA) |
| **Venda** | Registra detalhes das vendas | ❌ Não (usa Financeiro) |
| **VendaItem** | Itens de cada venda | ❌ Não |
| **EstoqueMovimento** | Movimentação de estoque | ❌ Não |

## 🔄 Compatibilidade

Esta correção **não afeta**:
- ✅ Fechamento de Caixa PDV (continua funcionando)
- ✅ Relatórios existentes
- ✅ Histórico de vendas
- ✅ Controle de estoque
- ✅ Dados já registrados no banco

## 🧪 Como Testar

1. **Registre uma venda no PDV**
   - Acesse: PDV → Registrar Venda
   - Complete uma venda

2. **Verifique o Fluxo de Caixa**
   - Acesse: Gestão Financeira → Fluxo de Caixa
   - Confirme que a venda aparece **apenas 1 vez**

3. **Registre uma saída manual**
   - Acesse: PDV → Registrar Saída
   - Complete uma saída

4. **Verifique novamente**
   - A saída deve aparecer **apenas 1 vez**
   - Com origem "PDV - Saída Manual"

## ✨ Benefícios

1. **Dados Corretos**: Saldo real sem duplicação
2. **Confiabilidade**: Relatórios precisos
3. **Clareza**: Cada transação aparece uma vez
4. **Performance**: Menos registros duplicados no banco

---

**Status**: ✅ Corrigido
**Data**: 30/10/2025
**Impacto**: Baixo (apenas melhoria, sem quebra)
