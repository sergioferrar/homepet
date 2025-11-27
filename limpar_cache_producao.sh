#!/bin/bash

# Script para limpar cache em produção
echo "🧹 Limpando cache de produção..."

cd /home/u199209817/domains/systemhomepet.com/public_html

# Limpar cache do Symfony
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod

# Limpar cache de templates Twig
rm -rf var/cache/prod/twig

# Limpar opcache do PHP (se disponível)
if command -v php &> /dev/null; then
    php -r "if(function_exists('opcache_reset')) opcache_reset();"
fi

# Ajustar permissões
chmod -R 755 var/cache/prod
chmod -R 755 var/log

echo "✅ Cache limpo com sucesso!"
echo ""
echo "Se o problema persistir, execute também:"
echo "  - Limpe o cache do navegador (Ctrl+Shift+Delete)"
echo "  - Acesse em modo anônimo para testar"
