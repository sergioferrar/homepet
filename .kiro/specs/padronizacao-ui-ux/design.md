# Design Document - Padronização UI/UX do Sistema

## Overview

Este documento define o design system completo para padronização da interface do Sistema Pet. Estabelece componentes reutilizáveis, padrões visuais, e guidelines de implementação para garantir consistência em todas as telas.

## Architecture

### Component Hierarchy

```
Base Layout
├── Header Component
│   ├── Title Section (center)
│   ├── Back Button (left)
│   └── Action Buttons (right)
├── Search Component (optional)
├── Content Area
│   ├── Card Grid
│   └── Pagination
└── Modals/Offcanvas
```

### Design Tokens

#### Colors

```css
/* Primary Gradient */
--gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);

/* Card Gradients */
--gradient-pets: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
--gradient-clients: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
--gradient-debts: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
--gradient-time: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);

/* Semantic Colors */
--color-success: #198754;
--color-danger: #dc3545;
--color-warning: #ffc107;
--color-info: #0dcaf0;
--color-primary: #0d6efd;
--color-secondary: #6c757d;

/* Neutral Colors */
--color-text-primary: #212529;
--color-text-muted: #6c757d;
--color-bg-light: #f8f9fa;
--color-border: #dee2e6;
```

#### Spacing

```css
--spacing-xs: 0.25rem;  /* 4px */
--spacing-sm: 0.5rem;   /* 8px */
--spacing-md: 1rem;     /* 16px */
--spacing-lg: 1.5rem;   /* 24px */
--spacing-xl: 2rem;     /* 32px */
--spacing-xxl: 3rem;    /* 48px */
```

#### Typography

```css
--font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
--font-size-xs: 0.75rem;   /* 12px */
--font-size-sm: 0.875rem;  /* 14px */
--font-size-base: 1rem;    /* 16px */
--font-size-lg: 1.125rem;  /* 18px */
--font-size-xl: 1.25rem;   /* 20px */
--font-size-2xl: 1.5rem;   /* 24px */
--font-size-3xl: 2rem;     /* 32px */
```

#### Shadows

```css
--shadow-sm: 0 2px 4px rgba(0,0,0,0.08);
--shadow-md: 0 4px 12px rgba(0,0,0,0.15);
--shadow-lg: 0 8px 24px rgba(0,0,0,0.1);
--shadow-hover: 0 0 0 4px #e5eefe;
```

#### Border Radius

```css
--radius-sm: 0.375rem;  /* 6px */
--radius-md: 0.5rem;    /* 8px */
--radius-lg: 0.75rem;   /* 12px */
--radius-xl: 1rem;      /* 16px */
```

## Components and Interfaces

### 1. Page Header Component

```html
<div class="container mt-4">
    <!-- Welcome Card (optional - apenas dashboard) -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body text-white p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h3 class="mb-2">🐾 Bem-vindo ao System Pet</h3>
                            <p class="mb-0 opacity-75">Descrição da página</p>
                        </div>
                        <div class="text-end">
                            <h5 class="mb-0">{{ "now"|date("d/m/Y") }}</h5>
                            <small class="opacity-75">{{ "now"|date("H:i") }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Bar -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <!-- Botão Voltar (se aplicável) -->
            <a href="{{ path('dashboard') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Voltar
            </a>
        </div>
        <div class="col-md-6 text-end">
            <!-- Botões de Ação -->
            <a href="{{ path('entity_novo') }}" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Novo [Entidade]
            </a>
        </div>
    </div>
</div>
```

### 2. Search Component

```html
<div class="row mb-4">
    <div class="col-md-8">
        <input type="text" id="searchInput" class="form-control" 
               placeholder="🔍 Pesquisar por nome, CPF, e-mail ou telefone..." />
    </div>
    <div class="col-md-4">
        <!-- Filtros adicionais (opcional) -->
        <select id="filterType" class="form-select">
            <option value="">Todos os tipos</option>
            <option value="type1">Tipo 1</option>
        </select>
    </div>
</div>
```

### 3. Card Component

```html
<div class="col-12 card-item" data-search="[searchable text]">
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="flex-grow-1">
                    <h5 class="card-title mb-1 text-primary fw-bold">
                        <a href="#" class="text-decoration-none">Título</a>
                    </h5>
                    <p class="card-text text-muted mb-1">
                        <i class="bx bx-icon me-1"></i>Informação 1
                    </p>
                    <p class="card-text text-muted mb-0">
                        <i class="bx bx-icon me-1"></i>Informação 2
                    </p>
                </div>
                <div class="d-flex align-items-center gap-1">
                    <div class="dropdown">
                        <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" 
                                data-bs-toggle="dropdown">
                            Ação
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#">Ver Detalhes</a></li>
                            <li><a class="dropdown-item" href="#">Editar</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="#">Deletar</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
```

### 4. Statistics Cards Component

```html
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100 text-white" 
             style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
                <div class="mb-3">
                    <i class="fas fa-paw" style="font-size: 2.5rem; opacity: 0.9;"></i>
                </div>
                <h2 class="mb-2 text-white fw-bold">{{ count }}</h2>
                <p class="mb-0 small text-white opacity-75">Descrição</p>
            </div>
        </div>
    </div>
</div>
```

### 5. Pagination Component

```html
<div class="row mt-4">
    <div class="col text-center">
        <nav aria-label="Paginação">
            <ul id="pagination" class="pagination justify-content-center mb-0"></ul>
        </nav>
    </div>
</div>
```

### 6. Modal Component

```html
<div class="modal fade" id="modalName" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title">
                    <i class="fas fa-icon me-2"></i>Título
                </h5>
                <button type="button" class="btn-close btn-close-white" 
                        data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Conteúdo -->
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" id="btn-cancelar">
                    Cancelar
                </button>
                <button type="button" class="btn btn-primary">
                    Confirmar
                </button>
            </div>
        </div>
    </div>
</div>
```

### 7. Confirmation Modal Component

```html
<div class="modal fade" id="modalConfirmacao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark border-0">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>Atenção
                </h5>
            </div>
            <div class="modal-body text-center py-4">
                <p class="mb-0" id="mensagem-confirmacao">
                    Mensagem de confirmação
                </p>
            </div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-secondary" id="btn-nao">
                    <i class="fas fa-times me-1"></i>Não
                </button>
                <button type="button" class="btn btn-danger" id="btn-sim">
                    <i class="fas fa-check me-1"></i>Sim
                </button>
            </div>
        </div>
    </div>
</div>
```

## Data Models

### Page Configuration Model

```javascript
{
    title: "Nome da Página",
    icon: "fas fa-icon",
    hasWelcomeCard: boolean,
    hasBackButton: boolean,
    backUrl: "route_name",
    primaryAction: {
        label: "Novo [Entidade]",
        icon: "fas fa-plus",
        url: "route_name"
    },
    hasSearch: boolean,
    hasFilters: boolean,
    itemsPerPage: 5
}
```

## Error Handling

### Form Validation

- Campos obrigatórios devem ter asterisco (*)
- Mensagens de erro devem aparecer abaixo do campo
- Usar classes Bootstrap: `is-invalid` e `invalid-feedback`

### API Errors

- Usar toasts para feedback de sucesso/erro
- Fallback para alert() se Notify não estiver disponível
- Mensagens claras e acionáveis

## Testing Strategy

### Visual Regression Testing

1. Capturar screenshots de cada tela antes da padronização
2. Aplicar mudanças
3. Comparar visualmente
4. Validar que funcionalidades não foram quebradas

### Checklist de Validação

Para cada tela padronizada:

- [ ] Header com título e ícone
- [ ] Botões posicionados corretamente
- [ ] Cards com estilo consistente
- [ ] Hover effects funcionando
- [ ] Busca funcionando
- [ ] Paginação funcionando
- [ ] Responsivo em mobile
- [ ] Sem botões duplicados
- [ ] Cores e tipografia corretas
- [ ] Ícones consistentes

## Implementation Guidelines

### CSS Classes Padrão

```css
/* Cards */
.card-item { transition: box-shadow 0.2s; }
.card-item:hover { box-shadow: 0 0 0 4px #e5eefe; }

/* Buttons */
.btn { transition: all 0.2s; }
.btn:hover { transform: translateY(-1px); }

/* Animations */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.row > div { animation: fadeInUp 0.5s ease-out; }
```

### JavaScript Padrão

```javascript
// Inicializar paginação
new TabelaDefault({
    cardContainerId: 'cardContainer',
    paginationId: 'pagination',
    searchInputId: 'searchInput',
    itemsPerPage: 5
});

// Modal de confirmação
const modalConfirmacao = new bootstrap.Modal($('#modalConfirmacao')[0]);
```

## Telas Específicas

### Agendamentos

- **Remover**: Botão "Novo Agendamento" do header
- **Manter**: Apenas o calendário com funcionalidade integrada de criar agendamento
- **Justificativa**: Evitar redundância, o calendário já permite criar novos agendamentos

### Dashboard

- **Adicionar**: Welcome card com gradiente
- **Manter**: Cards de estatísticas com gradientes
- **Padronizar**: Cards de listagem (últimos atendimentos, animais cadastrados, etc.)

### Listagens (Clientes, Pets, Serviços)

- **Padronizar**: Header com botão "Novo" à direita
- **Adicionar**: Campo de busca consistente
- **Manter**: Paginação com TabelaDefault.js
- **Padronizar**: Cards com mesmo estilo e hover effect

## Responsive Breakpoints

```css
/* Mobile First */
@media (max-width: 576px) {
    /* Stack buttons vertically */
    /* Full width cards */
}

@media (min-width: 768px) {
    /* Tablet layout */
}

@media (min-width: 992px) {
    /* Desktop layout */
}
```
