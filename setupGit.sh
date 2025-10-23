#!/bin/bash
# 🚀 Script para atualizar e enviar projeto Symfony para o GitHub
# Autor: Sergio Ferrari

echo "=== 🚀 Atualizando e enviando alterações do projeto Symfony ==="

# 1. Verifica se o Git está instalado
if ! command -v git &> /dev/null; then
    echo "❌ Git não encontrado. Instale com: sudo apt install git -y"
    exit 1
fi

# 2. Verifica se existe repositório git
if [ ! -d ".git" ]; then
    echo "⚠️ Este diretório não é um repositório Git."
    echo "Execute 'git init' e configure o repositório antes de rodar este script."
    exit 1
fi

# 3. Mostra o repositório remoto atual
echo ""
echo "📡 Repositório remoto configurado:"
git remote -v
echo ""

# 4. Atualiza o código antes de subir mudanças
echo "⬇️ Fazendo pull para sincronizar com o repositório remoto..."
git pull origin main 2>/dev/null || git pull origin master 2>/dev/null

# 5. Adiciona novos arquivos e mudanças
echo "🌀 Adicionando alterações..."
git add .

# 6. Solicita uma mensagem de commit
read -p "✏️  Digite a mensagem do commit: " MSG
if [ -z "$MSG" ]; then
    MSG="Atualização automática do projeto"
fi

# 7. Cria o commit
git commit -m "$MSG"

# 8. Faz push para o repositório remoto
echo "🚀 Enviando alterações para o GitHub..."
git push origin main 2>/dev/null || git push origin master 2>/dev/null

# 9. Mensagem final
echo ""
echo "✅ Projeto atualizado com sucesso!"
echo "Verifique no GitHub para confirmar as mudanças."

