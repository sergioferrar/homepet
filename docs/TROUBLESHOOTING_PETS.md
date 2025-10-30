# Troubleshooting - Erro ao Buscar Pets

## Erro Identificado

```javascript
console.error('Erro ao buscar pets:', err);
```

## Possíveis Causas e Soluções

### 1. Erro 404 - Rota não encontrada

**Sintoma**: `Failed to fetch` ou `404 Not Found`

**Causa**: A rota `/orcamento/api/cliente/{id}/pets` não está sendo encontrada

**Solução**:
```bash
# Limpar cache do Symfony
php bin/console cache:clear

# Verificar rotas
php bin/console debug:router | grep pets
```

Deve aparecer:
```
api_cliente_pets  GET  /orcamento/api/cliente/{id}/pets
```

### 2. Erro 500 - Erro no servidor

**Sintoma**: `500 Internal Server Error`

**Causa**: Erro na query ou no banco de dados

**Solução**: Verificar logs do Symfony
```bash
tail -f var/log/dev.log
```

### 3. Erro de CORS

**Sintoma**: `CORS policy` no console

**Causa**: Requisição bloqueada por política de CORS

**Solução**: Não deve acontecer pois é mesma origem, mas se acontecer, adicione headers no controller

### 4. Campo dono_id não existe

**Sintoma**: `Unknown column 'dono_id'`

**Causa**: Nome do campo na tabela é diferente

**Solução**: Verificar estrutura da tabela
```sql
DESCRIBE pet;
```

Procure por campos como:
- `dono_id`
- `cliente_id`
- `tutor_id`
- `owner_id`

Se for diferente, ajuste no controller:
```php
->where('p.NOME_CORRETO_DO_CAMPO = :donoId')
```

### 5. Tabela pet não existe no banco correto

**Sintoma**: `Table 'database.pet' doesn't exist`

**Causa**: O `switchDB()` não está funcionando ou a tabela está em outro banco

**Solução**:
```sql
-- Ver em qual banco está a tabela
SHOW TABLES LIKE 'pet';

-- Ver qual banco está sendo usado
SELECT DATABASE();
```

## Como Debugar

### Passo 1: Abrir Console do Navegador (F12)

Veja a mensagem de erro completa. Exemplos:

```
GET http://localhost/orcamento/api/cliente/1/pets 404 (Not Found)
GET http://localhost/orcamento/api/cliente/1/pets 500 (Internal Server Error)
Failed to fetch
```

### Passo 2: Testar API Diretamente

Abra em uma nova aba:
```
http://localhost/homepet/public/orcamento/api/cliente/1/pets
```

**Resposta esperada (sucesso)**:
```json
{
  "success": true,
  "pets": [
    {
      "id": 1,
      "nome": "Rex",
      "especie": "Cachorro",
      "raca": "Labrador"
    }
  ]
}
```

**Resposta esperada (sem pets)**:
```json
{
  "success": true,
  "pets": []
}
```

**Resposta de erro**:
```json
{
  "success": false,
  "message": "Erro ao buscar pets: ..."
}
```

### Passo 3: Verificar Logs do Symfony

```bash
# Ver últimas linhas do log
tail -n 50 var/log/dev.log

# Acompanhar em tempo real
tail -f var/log/dev.log
```

### Passo 4: Testar Query Diretamente no MySQL

```sql
-- Substitua 1 pelo ID do cliente
SELECT * FROM pet WHERE dono_id = 1;

-- Ver todos os pets
SELECT id, nome, dono_id, especie, raca FROM pet LIMIT 10;

-- Ver estrutura da tabela
DESCRIBE pet;
```

## Correções Aplicadas

### 1. Adicionado estabelecimentoId na query
```php
->andWhere('p.estabelecimentoId = :estab')
->setParameter('estab', $baseId)
```

Isso garante que só busque pets do estabelecimento correto.

### 2. Usado QueryBuilder em vez de DQL
```php
// Antes (pode dar problema com underscore)
$em->createQuery('SELECT p FROM App\Entity\Pet p WHERE p.dono_id = :donoId')

// Agora (mais robusto)
$em->getRepository(\App\Entity\Pet::class)
    ->createQueryBuilder('p')
    ->where('p.dono_id = :donoId')
```

### 3. Adicionados logs detalhados
```javascript
console.error('Erro ao buscar pets:', err);
console.error('Stack trace:', err.stack);
```

## Teste Rápido

Execute este teste para verificar se está funcionando:

```javascript
// Cole no Console do navegador
fetch('/orcamento/api/cliente/1/pets')
    .then(r => r.json())
    .then(data => console.log('Resposta:', data))
    .catch(err => console.error('Erro:', err));
```

## Checklist de Verificação

- [ ] Cache do Symfony limpo
- [ ] Rota aparece no `debug:router`
- [ ] Tabela `pet` existe no banco
- [ ] Campo `dono_id` existe na tabela
- [ ] Há pelo menos 1 pet cadastrado
- [ ] API retorna JSON válido quando testada diretamente
- [ ] Console do navegador mostra logs detalhados
- [ ] Logs do Symfony não mostram erros

## Se Nada Funcionar

Envie as seguintes informações:

1. **Erro completo do Console (F12)**
2. **Resposta da API** (teste direto no navegador)
3. **Estrutura da tabela**: `DESCRIBE pet;`
4. **Exemplo de dados**: `SELECT * FROM pet LIMIT 1;`
5. **Logs do Symfony**: últimas 20 linhas de `var/log/dev.log`

Com essas informações, posso identificar exatamente o problema!
