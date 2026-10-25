#!/bin/bash

echo "Aguardando o PostgreSQL iniciar..."
until PGPASSWORD=$DB_PASSWORD psql -h "$DB_HOST" -U "$DB_USERNAME" -d "$DB_DATABASE" -c '\q' 2>/dev/null; do
  echo "Postgres ainda não está pronto - aguardando..."
  sleep 2
done

echo "PostgreSQL está pronto!"

if [ -d "/var/www/database/migrations" ]; then
    echo "Aplicando migrations..."
    for file in /var/www/database/migrations/*.sql; do
        if [ -f "$file" ]; then
            echo "Executando $file..."
            # O '|| true' garante que se a tabela já existir, o script continue rodando
            PGPASSWORD=$DB_PASSWORD psql -h "$DB_HOST" -U "$DB_USERNAME" -d "$DB_DATABASE" -f "$file" || true
        fi
    done
    echo "Migrations concluídas."
fi

exec "$@"
