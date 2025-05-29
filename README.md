# VolleyCoachPro — Frontend 🎯🏐
📍 `https://volleycoachpro.alwaysdata.net`

Bienvenue sur le dépôt du **frontend** de *VolleyCoachPro*, une application web dédiée à la gestion et au suivi des performances d’une équipe de volley-ball réalisée dans le cadre d'un projet académique.  
Cette interface permet aux coachs de consulter les joueurs, gérer les rencontres, noter les performances et visualiser les statistiques.

## 🧱 Stack technique

- **Langage :** PHP (serveur), HTML5, CSS3, JavaScript (vanilla)
- **Base de données :** MySQL (gérée côté API)
- **Connexion API :** Requêtes `cURL` sécurisées via `Bearer token`

## 🔐 Authentification

L'accès aux pages de gestion est restreint via session PHP et `Bearer token`.  
L'authentification se fait auprès de l’API `https://volleycoachpro.alwaysdata.net/authapi/`.

## 🔗 Intégration avec l’API

Toutes les opérations de **CRUD** passent par une **API REST** :  
📍 `https://volleycoachpro.alwaysdata.net/volleyapi/`.
Pour plus d'informations : `https://github.com/Dracolf/-projet-R4.01-VolleyAPI`

## 🧪 Fonctionnalités principales

### Gestion des joueurs
- Ajout, modification et suppression de joueurs
- Recherche en temps réel

### Gestion des matchs
- Ajout de rencontres avec composition d’équipe (12 rôles)
- Mise à jour des scores avec validation règlementaire (3 sets gagnants, tie-break…)
- Attribution de notes aux joueurs par rôle
- Modification et suppression de matchs existants.

### Statistiques
- Moyennes de notes par joueur
- Statistiques des rencontres (victoires, défaites)
- Nombre de titularisations de chaque joueur


