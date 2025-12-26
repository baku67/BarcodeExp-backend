docker compose up --build
docker compose up -d (detached)

Paire token JWT généré et gitigoné (EZ):
php bin/console lexik:jwt:generate-keypair


-------------------------------------

Conteneur Symfony:
    docker compose exec api php bin/console make:user
    docker compose exec api php bin/console doctrine:migrations:diff // générer migration
    docker compose exec api php bin/console doctrine:migrations:migrate --no-interaction // appliquer migration (déjà dans compose)

Conteneur DB:
    docker compose exec db psql -U app -d app
        \l, \dt, \d user, SELECT * FROM "user" LIMIT 20;, \q




---------------------------------------


PROD:
    Dockerfile: En prod, ne garde pas ça : vise php-fpm + nginx/caddy (beaucoup plus optimisé et standard).
    Dans manifest app Kotlin: RETIRER usesCleartextTraffic en PROD (http/https)

PROD-Migrations:
    Je te déconseille de lancer automatiquement les migrations au démarrage du conteneur API en prod.
    En prod, on fait ça dans le pipeline de déploiement (ou un job one-shot) pour éviter :
    migrations concurrentes si tu scales à plusieurs instances
    surprises au redémarrage