Esportify v3 — Backend (PHP + MySQL + MongoDB) branch de développement et test en local 

Prérequis
- Docker Desktop
- Postman (tests API)
- DBeaver (MySQL)
- MongoDB Compass (Mongo)

Lancer le projet
docker compose up -d --build

API: http://localhost:8080
Vérifier la santé
•	GET /health → doit renvoyer { ok: true, mysql: true, mongo: true }

Base de données MySQL (DBeaver)
Connexion :
•	Host: localhost
•	Port: 3306
•	Database: esportify
•	User: esportify_user
•	Password: esportify_pass

MongoDB (Mongo Compass)
Connexion :
•	Host: localhost
•	Port: 27018 (mapping docker → mongo:27017)
•	Username: mongoadmin
•	Password: mongopass
•	Auth DB: admin
DB utilisée côté app: esportify_chat
Collection: event_messages

Initialisation DB
MySQL
Exécuter le script :
•	backend/scripts/mysql_schema.sql
Mongo (index)
Dans Mongo Compass (ou mongosh) :
•	backend/scripts/mongo_indexes.js
Workflow API (Postman)
1) CSRF
•	GET /csrf → récupérer csrfToken
•	Mettre le header sur les requêtes POST protégées :
o	X-CSRF-Token: <token>
2) Auth
•	POST /register
{ "email":"...", "password":"...", "pseudo":"..." }
•	POST /login
{ "email":"...", "password":"..." }
•	GET /me → utilisateur courant (si cookie session OK)
3) Events
•	GET /events
•	POST /events (ORGANIZER)
4) Inscriptions
•	POST /events/{id}/register (PLAYER)
•	POST /events/{id}/unregister (PLAYER)
•	GET /events/{id}/registrations (ORGANIZER/ADMIN)
5) Lifecycle / Résultats
•	POST /events/{id}/start (ORGANIZER)
•	GET /events/{id}/join (PLAYER)
•	POST /events/{id}/finish (ORGANIZER)
•	GET /events/{id}/standings

6) Chat (Mongo)
•	GET /events/{id}/chat
•	POST /events/{id}/chat
{ "message": "Hello team!" }

Notes sécurité
•	Sessions HTTPOnly + SameSite=Lax
•	CSRF token obligatoire sur POST sensibles
•	RBAC via AuthMiddleware::requireRole()
•	Endpoints /users/{id}/... protégés (ADMIN ou soi-même)

# 3) Commit exact
git add README.md backend/scripts/mysql_schema.sql backend/scripts/mongo_indexes.js
git commit -m "docs(setup): add README and db init scripts (mysql + mongo)"
git push
________________________________________
Ensuite (dernière étape “projet”)
Comme tu m’as dit : à la fin on expliquera tout depuis le début → on fera un document “Rapport / Explications” :
•	arborescence
•	docker
•	auth + csrf
•	RBAC
•	mysql + mongo
•	postman flows
•	points sécurité

