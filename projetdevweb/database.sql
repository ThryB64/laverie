-- Création de la base de données
DROP DATABASE IF EXISTS laverie;
CREATE DATABASE IF NOT EXISTS laverie;
USE laverie;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    points INT DEFAULT 1,
    grade VARCHAR(50) DEFAULT 'Chiffon Recrue',
    is_admin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des machines
CREATE TABLE IF NOT EXISTS machines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('lave-linge', 'seche-linge', 'fer-a-repasser') NOT NULL,
    numero INT NOT NULL,
    price INT NOT NULL,
    status ENUM('disponible', 'reserve', 'en_marche', 'maintenance') DEFAULT 'disponible',
    actif BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des services
CREATE TABLE IF NOT EXISTS services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL,
    status BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des produits
CREATE TABLE IF NOT EXISTS products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    distributor_id INT,
    stock INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (distributor_id) REFERENCES services(id)
);

-- Table des réservations
CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    machine_id INT NOT NULL,
    status ENUM('active', 'running', 'completed', 'cancelled', 'pending_payment') NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    duration INT NOT NULL,
    program VARCHAR(50) NOT NULL,
    temperature VARCHAR(20) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50),
    paid_at DATETIME,
    cancelled_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (machine_id) REFERENCES machines(id)
);

-- Table des commandes
CREATE TABLE IF NOT EXISTS orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    product_id INT,
    quantity INT DEFAULT 1,
    total_price DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Table des états des services
CREATE TABLE IF NOT EXISTS etats_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    etat BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id)
);

-- Table des points history
CREATE TABLE points_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    points INT NOT NULL,
    date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    description VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des achats de produits
CREATE TABLE IF NOT EXISTS product_achats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantite INT NOT NULL DEFAULT 1,
    price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Table des messages de contact
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    email VARCHAR(255),
    user_id INT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insertion des utilisateurs
INSERT INTO users (username, password, grade, points) VALUES
('baron', '$2y\$10\$KF2fdMY2jlPc2xqXLXmbz.IxVM4aZuUSK6yZCaVUJDav0esNGIqei' , 'Baron du Blanchiment', 5000),
('chiffon', '$2b$12$wrBYhcJchRJbbh/hVNQp9eNbir08HZUJCpuE1POwI.dRe14G0MJiK', 'Chiffon recrue', 60);

-- Insertion des machines
INSERT INTO machines (name, type, numero, price) VALUES
('Lave-linge 1', 'lave-linge', 1, 5),
('Lave-linge 2', 'lave-linge', 2, 5),
('Sèche-linge 1', 'seche-linge', 1, 3),
('Sèche-linge 2', 'seche-linge', 2, 3),
('Fer à repasser 1', 'fer-a-repasser', 1, 2),
('Fer à repasser 2', 'fer-a-repasser', 2, 2);

-- Insertion des services de base
INSERT INTO services (name, type) VALUES
('Lampe Salon', 'lampe'),
('TV Principale', 'tv'),
('Musique Ambiance', 'musique'),
('Robot Aspirateur', 'robot'),
('Fenêtre Auto', 'fenetre'),
('Climatisation', 'climatisation'),
('Distributeur Lessive', 'distributeur'),
('Distributeur Assouplissant', 'distributeur'),
('Distributeur Produits', 'distributeur');

-- Insertion des produits
INSERT INTO products (name, price, distributor_id, stock) VALUES
-- Distributeur 7 : Lessive
('Lessive en poudre', 10.00, 7, 100),
('Lessive liquide', 11.00, 7, 100),
('Lessive capsule', 12.00, 7, 100),
-- Distributeur 8 : Assouplissant
('Assouplissant doux', 8.00, 8, 100),
('Assouplissant parfumé', 9.00, 8, 100),
('Assouplissant concentré', 10.00, 8, 100),
('Nettoyant sol', 12.00, 9, 100),
('Spray vitres', 11.00, 9, 100),
('Dégraissant cuisine', 13.00, 9, 100);






UPDATE users SET is_admin = 1 WHERE username = 'baron';

DELIMITER //

CREATE TRIGGER after_points_update 
AFTER UPDATE ON users
FOR EACH ROW 
BEGIN
    IF OLD.points != NEW.points THEN
        INSERT INTO points_history (user_id, points, description)
        VALUES (NEW.id, NEW.points, CONCAT('Points mis à jour de ', OLD.points, ' à ', NEW.points));
    END IF;
END//

DELIMITER ;
 
























 