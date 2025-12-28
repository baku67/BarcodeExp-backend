docker compose up --build
docker compose up -d (detached)

Paire token JWT généré et gitigoné (EZ):
SI "An error occurred while trying to encode the JWT token. Please verify your configuration (private key/passphrase) (500 Internal Server Error)" :
docker compose exec api sh -lc "rm -f config/jwt/private.pem config/jwt/public.pem" (suppr les keys)
docker compose exec api php bin/console lexik:jwt:generate-keypair (regen les keys)

MailPit depuis Tél (wifi meme reseau que machine hote):
ipconfig -> http://{ipv4}:8025

-------------------------------------

Conteneur Symfony:
    docker compose exec api php bin/console cache:clear

    docker compose exec api php bin/console make:user // docker compose exec api php bin/console make:entity user
    docker compose exec api php bin/console doctrine:migrations:diff // générer migration
    docker compose exec api php bin/console doctrine:migrations:migrate --no-interaction // appliquer migration (en live, déjà dans compose)

    Logs:
        docker compose logs api --tail=200
        docker compose exec api tail -n 200 var/log/dev.log

Conteneur DB:
    docker compose exec db psql -U app -d app
        \l, \dt, \d user, SELECT * FROM "user" LIMIT 20;, \q




---------------------------------------


PROD:
    - Dockerfile: En prod, ne garde pas ça : vise php-fpm + nginx/caddy (beaucoup plus optimisé et standard).
    - Dans manifest app Kotlin: RETIRER usesCleartextTraffic en PROD (http/https)
    - Rate-limiter /!\ (services.yaml, framework.yaml) sur resend_email par exemple

PROD-Migrations:
    Je te déconseille de lancer automatiquement les migrations au démarrage du conteneur API en prod.
    En prod, on fait ça dans le pipeline de déploiement (ou un job one-shot) pour éviter :
    migrations concurrentes si tu scales à plusieurs instances
    surprises au redémarrage