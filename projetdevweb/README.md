# 🧺 L'Atelier du Blanchisseur – Laverie Connectée

Projet réalisé dans le cadre du module **Développement Web - ING1 GM** (Avril 2025)

## 📌 Description

**L'Atelier du Blanchisseur** est une plateforme web de gestion intelligente d'une laverie automatique. Le site permet aux utilisateurs de :
- Réserver des machines (lave-linge, sèche-linge, fers à repasser)
- Payer en ligne
- Utiliser un système de **points et de grades** (gamification)
- Accéder à des **services domotiques** (lampe, TV, musique, robot, etc.)
- Commander des **produits** (lessive, assouplissant, etc.) via des distributeurs
- Gérer leur historique de réservation et leurs points
- Interagir via un formulaire de contact

Une **interface administrateur** permet de :
- Suivre les statistiques et performances
- Gérer les utilisateurs, produits, services et équipements
- Effectuer de la maintenance préventive

## 👨‍💻 Équipe

- **Berard Thierry**
- **Bohain Mathis**
- **Guerin Enzo**
- **Cerfaux Baptiste**
- **Boumedine Imrane**

## 🚀 Fonctionnalités principales

- 🔐 Connexion avec gestion des rôles (Visiteur, Chiffon Recrue, Écuyer, Chevalier, Baron/Administrateur)
- 📅 Réservation en ligne des machines avec statut en temps réel
- 💳 Paiement 
- 🧼 Commande de produits de lavage
- 📊 Tableau de bord administrateur
- 🏆 Système de points, grades et historique
- 🏠 Contrôle de services

## 🛠️ Technologies utilisées

- **Front-end** : HTML5, CSS3, JavaScript natif, Bootstrap 5
- **Back-end** : PHP natif
- **Base de données** : MySQL
- **Sécurité** : Hash des mots de passe, sessions PHP, protection des accès

## 🧪 Données de test

Les données de test sont insérées dans `database.sql` :
- 4 utilisateurs avec différents grades (Chiffon → Baron)
- Machines et services préconfigurés
- Produits de lavage avec stock
- Réservations simulées sur plusieurs jours

## ✅ Pour démarrer le projet

1. Importer la base `laverie` dans MySQL à partir de `database.sql` avec la commande  : mysql -u root -p < database.sql
2. Mettez votre mot de passe mysql dans le fichier 'includes/config.php' avec : define('DB_PASS', 'cytech0001');

3. Déployer le projet dans un serveur local par exemple: php -S localhost:8080 
4. Accéder à l’interface via 'localhost:8080/'

## 📈 Statistiques et perspectives

- Ajout futur d'une application mobile
- Amélioration possible : notifications, gestion des abonnements, IA pour la prédiction des créneaux

---

© Projet pédagogique – CY TECH
