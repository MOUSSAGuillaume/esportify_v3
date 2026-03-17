Esportify v3 🚀

 🎯 Présentation

Esportify v3 est une plateforme web de gestion d’événements e-sport conçue avec une architecture moderne, sécurisée et maintenable.

👉 Cette version représente une **évolution complète** du projet initial :

* passage à une architecture **POO + MVC**
* séparation claire des responsabilités
* sécurisation avancée
* conteneurisation avec Docker

---

 ⚙️ Stack technique

* Backend : PHP (architecture MVC / POO)
* Base principale : MySQL
* Base secondaire : MongoDB (chat)
* Serveur : Nginx (via Docker)
* Outils : Docker, Postman, DBeaver

---

 🧠 Architecture

```
Controller → Service → Repository → Database
```

* **Controller** : gestion des requêtes HTTP
* **Service** : logique métier
* **Repository** : accès aux données
* **Security** : auth, CSRF, hash password

---

 🔐 Sécurité

* Authentification sécurisée (hash password)
* Protection CSRF
* Sessions HTTPOnly
* RBAC (gestion des rôles)
* Prévention XSS côté front (textContent)

👉 Améliorations prévues :

* MFA (double authentification)
* RGPD

---

 🚀 Installation (local)

```bash
git clone https://github.com/MOUSSAGuillaume/esportify_v3.git
cd esportify_v3
git checkout dev
```

```bash
docker compose up -d --build
```

👉 Accès API : [http://localhost:8080](http://localhost:8080)

---

 🧪 Tests API

  1. Récupérer CSRF :

```http
GET /csrf
```

  2. Login :

```http
POST /login
```

  3. Events :

```http
GET /events
POST /events
```

---

 📦 Déploiement (UpCloud)

👉 Exemple de déploiement sécurisé (SANS données sensibles)

  1. Créer un serveur

* Aller sur [https://hub.upcloud.com](https://hub.upcloud.com)
* Créer un serveur Ubuntu (22.04 recommandé)
* Configuration conseillée : 1 CPU / 2GB RAM

---

 2. Connexion SSH

```bash
ssh root@YOUR_SERVER_IP
```

---

   3. Installer Docker

```bash
apt update && apt upgrade -y
apt install docker.io docker-compose -y
systemctl start docker
systemctl enable docker
```

---

  4. Cloner le projet

```bash
git clone https://github.com/MOUSSAGuillaume/esportify_v3.git
cd esportify_v3
```

---

  5. Configuration (.env)

⚠️ IMPORTANT : ne jamais exposer les vraies données

Exemple de fichier `.env` :

```
DB_HOST=mysql
DB_NAME=example_db
DB_USER=example_user
DB_PASS=example_password

MONGO_HOST=mongo
MONGO_PORT=27017
MONGO_USER=example_mongo_user
MONGO_PASS=example_mongo_password
```

👉 Ces valeurs sont des **exemples uniquement**

---

  6. Lancer le projet

```bash
docker compose up -d --build
```

---

  7. Ouvrir les ports

* 80 (HTTP)
* 443 (HTTPS recommandé)

---

   8. Accès

👉 http://YOUR_SERVER_IP

---

 🔥 Bonnes pratiques production

* Utiliser HTTPS (Certbot + Nginx)
* Ne jamais exposer `.env` (ajouter au `.gitignore`)
* Utiliser des mots de passe forts
* Restreindre accès base de données
* Mettre en place des backups

---

 📁 Structure

```
backend/
 ├── Controller/
 ├── Service/
 ├── Repository/
 ├── Security/
```

---

 🧪 Améliorations futures

* MFA (double authentification)
* RGPD
* Monitoring
* Dashboard React complet

---

 👨‍💻 Auteur

Projet réalisé dans le cadre d’une formation développeur web.

---
 📜 Licence

Projet pédagogique - 2026
