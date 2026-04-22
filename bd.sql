CREATE TABLE Prestataire (
    id_prestataire INT,
    nom_prestataire VARCHAR(100) NOT NULL,
    type_prestataire VARCHAR(100),
    email_prestataire VARCHAR(150),
    telephone_prestataire VARCHAR(20),
    CONSTRAINT pk_prestataire PRIMARY KEY (id_prestataire)
);

CREATE TABLE Visiteur (
    id_visiteur INT,
    nom_visiteur VARCHAR(50) NOT NULL,
    prenom_visiteur VARCHAR(50) NOT NULL,
    email_visiteur VARCHAR(150),
    CONSTRAINT pk_visiteur PRIMARY KEY (id_visiteur)
);

CREATE TABLE Role (
    id_role INT,
    nom_role VARCHAR(50) NOT NULL,
    CONSTRAINT pk_role PRIMARY KEY (id_role)
);

CREATE TABLE Prestation (
    id_prestation INT,
    nom_prestation VARCHAR(50) NOT NULL,
    CONSTRAINT pk_prestation PRIMARY KEY (id_prestation)
);

CREATE TABLE Soin (
    id_soin INT,
    nom_soin VARCHAR(100) NOT NULL,
    type_soin VARCHAR(100),
    description VARCHAR(500),
    CONSTRAINT pk_soin PRIMARY KEY (id_soin)
);

CREATE TABLE Espece (
    id_espece INT,
    nom_usuel VARCHAR(50) NOT NULL,
    nom_latin VARCHAR(50),
    est_menacee INT DEFAULT 0,
    CONSTRAINT pk_espece PRIMARY KEY (id_espece),
    CONSTRAINT chk_espece_menacee CHECK (est_menacee IN (0,1))
);

CREATE TABLE Cohabiter (
    id_espece1 INT NOT NULL,
    id_espece2 INT NOT NULL,
    CONSTRAINT pk_cohabiter PRIMARY KEY (id_espece1, id_espece2),
    CONSTRAINT fk_cohabiter_espece1 FOREIGN KEY (id_espece1) REFERENCES Espece(id_espece) ON DELETE CASCADE,
    CONSTRAINT fk_cohabiter_espece2 FOREIGN KEY (id_espece2) REFERENCES Espece(id_espece) ON DELETE CASCADE,
    CONSTRAINT chk_cohabiter_diff CHECK (id_espece1 <> id_espece2)
);

CREATE TABLE Personnel (
    id_personnel INT,
    nom_personnel VARCHAR(100) NOT NULL,
    prenom_personnel VARCHAR(100) NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    date_entree DATE NOT NULL,
    salaire NUMBER(10,2),
    id_manager INT,
    CONSTRAINT pk_personnel PRIMARY KEY (id_personnel),
    CONSTRAINT fk_personnel_manager FOREIGN KEY (id_manager) REFERENCES Personnel(id_personnel) ON DELETE SET NULL
);

CREATE TABLE Historique_emploi (
    id_historique_emploi INT,
    date_debut DATE NOT NULL,
    date_fin DATE,
    id_personnel INT NOT NULL,
    id_role INT NOT NULL,
    CONSTRAINT pk_historique_emploi PRIMARY KEY (id_historique_emploi),
    CONSTRAINT fk_historique_emploi_personnel FOREIGN KEY (id_personnel) REFERENCES Personnel(id_personnel) ON DELETE CASCADE,
    CONSTRAINT fk_historique_emploi_role FOREIGN KEY (id_role) REFERENCES Role(id_role) ON DELETE CASCADE
);

CREATE TABLE Zone (
    id_zone INT,
    nom_zone VARCHAR(100) NOT NULL,
    id_historique_emploi INT,
    CONSTRAINT pk_zone PRIMARY KEY (id_zone),
    CONSTRAINT fk_zone_historique FOREIGN KEY (id_historique_emploi) REFERENCES Historique_emploi(id_historique_emploi) ON DELETE SET NULL
);

CREATE TABLE Boutique (
    id_boutique INT,
    type_boutique VARCHAR(100),
    id_personnel INT,
    id_zone INT,
    CONSTRAINT pk_boutique PRIMARY KEY (id_boutique),
    CONSTRAINT fk_boutique_personnel FOREIGN KEY (id_personnel) REFERENCES Personnel(id_personnel) ON DELETE SET NULL,
    CONSTRAINT fk_boutique_zone FOREIGN KEY (id_zone) REFERENCES Zone(id_zone) ON DELETE SET NULL
);

CREATE TABLE Chiffre_affaires (
    id_ca INT,
    id_boutique INT NOT NULL,
    date_ca DATE NOT NULL,
    montant NUMBER(12,2) NOT NULL,
    CONSTRAINT pk_chiffre_affaires PRIMARY KEY (id_ca),
    CONSTRAINT fk_ca_boutique FOREIGN KEY (id_boutique) REFERENCES Boutique(id_boutique) ON DELETE CASCADE
);

CREATE TABLE Travailler (
    id_personnel INT NOT NULL,
    id_boutique INT NOT NULL,
    CONSTRAINT pk_travailler PRIMARY KEY (id_personnel, id_boutique),
    CONSTRAINT fk_travailler_personnel FOREIGN KEY (id_personnel) REFERENCES Personnel(id_personnel) ON DELETE CASCADE,
    CONSTRAINT fk_travailler_boutique FOREIGN KEY (id_boutique) REFERENCES Boutique(id_boutique) ON DELETE CASCADE
);

CREATE TABLE Enclos (
    id_enclos INT,
    surface NUMBER(10,2),
    latitude NUMBER(9,6),
    longitude NUMBER(9,6),
    particularites VARCHAR(200),
    id_zone INT,
    CONSTRAINT pk_enclos PRIMARY KEY (id_enclos),
    CONSTRAINT fk_enclos_zone FOREIGN KEY (id_zone) REFERENCES Zone(id_zone) ON DELETE SET NULL
);

CREATE TABLE Reparation (
    id_reparation INT,
    date_reparation DATE NOT NULL,
    nature VARCHAR(200),
    id_enclos INT,
    id_prestataire INT,
    id_personnel INT,
    CONSTRAINT pk_reparation PRIMARY KEY (id_reparation),
    CONSTRAINT fk_reparation_enclos FOREIGN KEY (id_enclos) REFERENCES Enclos(id_enclos) ON DELETE SET NULL,
    CONSTRAINT fk_reparation_prestataire FOREIGN KEY (id_prestataire) REFERENCES Prestataire(id_prestataire) ON DELETE SET NULL,
    CONSTRAINT fk_reparation_personnel FOREIGN KEY (id_personnel) REFERENCES Personnel(id_personnel) ON DELETE SET NULL
);

CREATE TABLE Animal (
    rfid VARCHAR(50),
    nom_animal VARCHAR(100),
    date_naissance DATE,
    poids NUMBER(10,2),
    regime_alimentaire VARCHAR(200),
    zoo VARCHAR(100),
    id_espece INT,
    id_enclos INT,
    id_personnel INT,
    CONSTRAINT pk_animal PRIMARY KEY (rfid),
    CONSTRAINT fk_animal_espece FOREIGN KEY (id_espece) REFERENCES Espece(id_espece) ON DELETE SET NULL,
    CONSTRAINT fk_animal_enclos FOREIGN KEY (id_enclos) REFERENCES Enclos(id_enclos) ON DELETE SET NULL,
    CONSTRAINT fk_animal_personnel FOREIGN KEY (id_personnel) REFERENCES Personnel(id_personnel) ON DELETE SET NULL
);

CREATE TABLE Historique_soins (
    id_historique_soins INT,
    type_soigneur VARCHAR(50),
    date_soin DATE NOT NULL,
    id_soin INT,
    id_personnel INT,
    rfid VARCHAR(50),
    CONSTRAINT pk_historique_soins PRIMARY KEY (id_historique_soins),
    CONSTRAINT fk_hsoins_soin FOREIGN KEY (id_soin) REFERENCES Soin(id_soin) ON DELETE SET NULL,
    CONSTRAINT fk_hsoins_personnel FOREIGN KEY (id_personnel) REFERENCES Personnel(id_personnel) ON DELETE SET NULL,
    CONSTRAINT fk_hsoins_animal FOREIGN KEY (rfid) REFERENCES Animal(rfid) ON DELETE CASCADE
);

CREATE TABLE Nourrissage (
    id_nourrissage INT,
    date_nourrissage DATE NOT NULL,
    dose_nourrissage NUMBER(10,2),
    nom_aliment VARCHAR(50),
    remarques_nourrissage VARCHAR(500),
    id_personnel INT,
    rfid VARCHAR(50),
    CONSTRAINT pk_nourrissage PRIMARY KEY (id_nourrissage),
    CONSTRAINT fk_nourrissage_personnel FOREIGN KEY (id_personnel) REFERENCES Personnel(id_personnel) ON DELETE SET NULL,
    CONSTRAINT fk_nourrissage_animal FOREIGN KEY (rfid) REFERENCES Animal(rfid) ON DELETE CASCADE
);

CREATE TABLE Parrainage (
    id_parrainage INT,
    date_debut_parrainage DATE NOT NULL,
    date_fin_parrainage DATE,
    niveau VARCHAR(50),
    rfid VARCHAR(50),
    id_visiteur INT,
    CONSTRAINT pk_parrainage PRIMARY KEY (id_parrainage),
    CONSTRAINT fk_parrainage_animal FOREIGN KEY (rfid) REFERENCES Animal(rfid) ON DELETE CASCADE,
    CONSTRAINT fk_parrainage_visiteur FOREIGN KEY (id_visiteur) REFERENCES Visiteur(id_visiteur) ON DELETE CASCADE
);

CREATE TABLE Offrir (
    id_parrainage INT NOT NULL,
    id_prestation INT NOT NULL,
    CONSTRAINT pk_offrir PRIMARY KEY (id_parrainage, id_prestation),
    CONSTRAINT fk_offrir_parrainage FOREIGN KEY (id_parrainage) REFERENCES Parrainage(id_parrainage) ON DELETE CASCADE,
    CONSTRAINT fk_offrir_prestation FOREIGN KEY (id_prestation) REFERENCES Prestation(id_prestation) ON DELETE CASCADE
);

CREATE TABLE Parent_fils (
    id_animal_parent VARCHAR(50) NOT NULL,
    id_animal_fils VARCHAR(50) NOT NULL,
    CONSTRAINT pk_parent_fils PRIMARY KEY (id_animal_parent, id_animal_fils),
    CONSTRAINT fk_parent_fils_parent FOREIGN KEY (id_animal_parent) REFERENCES Animal(rfid) ON DELETE CASCADE,
    CONSTRAINT fk_parent_fils_fils FOREIGN KEY (id_animal_fils) REFERENCES Animal(rfid) ON DELETE CASCADE,
    CONSTRAINT chk_parent_fils_diff CHECK (id_animal_parent <> id_animal_fils)
);

CREATE TABLE Specialiser (
    id_personnel INT NOT NULL,
    id_espece INT NOT NULL,
    CONSTRAINT pk_specialiser PRIMARY KEY (id_personnel, id_espece),
    CONSTRAINT fk_specialiser_personnel FOREIGN KEY (id_personnel) REFERENCES Personnel(id_personnel) ON DELETE CASCADE,
    CONSTRAINT fk_specialiser_espece FOREIGN KEY (id_espece) REFERENCES Espece(id_espece) ON DELETE CASCADE
);


/* ============================================================================
Prestataire
============================================================================ */
INSERT INTO Prestataire (id_prestataire, nom_prestataire, type_prestataire, email_prestataire, telephone_prestataire)
VALUES (1, 'Martin BTP', 'Construction', 'martin@btp.fr', '0601020304');

INSERT INTO Prestataire (id_prestataire, nom_prestataire, type_prestataire, email_prestataire, telephone_prestataire)
VALUES (2, 'Electricite Pro', 'Electricite', 'contact@elecpro.fr', '0605060708');

INSERT INTO Prestataire (id_prestataire, nom_prestataire, type_prestataire, email_prestataire, telephone_prestataire)
VALUES (3, 'Plomberie Dupont', 'Plomberie', 'dupont@plomb.fr', '0609101112');

INSERT INTO Prestataire (id_prestataire, nom_prestataire, type_prestataire, email_prestataire, telephone_prestataire)
VALUES (4, 'Clotures Expert', 'Securite', 'contact@clotures-expert.fr', '0610101010');

INSERT INTO Prestataire (id_prestataire, nom_prestataire, type_prestataire, email_prestataire, telephone_prestataire)
VALUES (5, 'Aqua Systeme', 'Maintenance eau', 'support@aquasysteme.fr', '0620202020');


/* ============================================================================
Visiteur
============================================================================ */
INSERT INTO Visiteur (id_visiteur, nom_visiteur, prenom_visiteur, email_visiteur)
VALUES (1, 'Durand', 'Alice', 'alice.durand@mail.fr');

INSERT INTO Visiteur (id_visiteur, nom_visiteur, prenom_visiteur, email_visiteur)
VALUES (2, 'Leroy', 'Baptiste', 'baptiste.leroy@mail.fr');

INSERT INTO Visiteur (id_visiteur, nom_visiteur, prenom_visiteur, email_visiteur)
VALUES (3, 'Moreau', 'Claire', 'claire.moreau@mail.fr');

INSERT INTO Visiteur (id_visiteur, nom_visiteur, prenom_visiteur, email_visiteur)
VALUES (4, 'Girard', 'David', 'david.girard@mail.fr');

INSERT INTO Visiteur (id_visiteur, nom_visiteur, prenom_visiteur, email_visiteur)
VALUES (5, 'Bonnet', 'Elena', 'elena.bonnet@mail.fr');

INSERT INTO Visiteur (id_visiteur, nom_visiteur, prenom_visiteur, email_visiteur)
VALUES (6, 'Roussel', 'Hugo', 'hugo.roussel@mail.fr');

INSERT INTO Visiteur (id_visiteur, nom_visiteur, prenom_visiteur, email_visiteur)
VALUES (7, 'Paris', 'Lea', 'lea.paris@mail.fr');


/* ============================================================================
Role
============================================================================ */
INSERT INTO Role (id_role, nom_role) VALUES (1, 'Administrateur');
INSERT INTO Role (id_role, nom_role) VALUES (2, 'Directeur');
INSERT INTO Role (id_role, nom_role) VALUES (3, 'Soigneur chef');
INSERT INTO Role (id_role, nom_role) VALUES (4, 'Soigneur');
INSERT INTO Role (id_role, nom_role) VALUES (5, 'Veterinaire');
INSERT INTO Role (id_role, nom_role) VALUES (6, 'Personnel technique');
INSERT INTO Role (id_role, nom_role) VALUES (7, 'Personnel entretien');
INSERT INTO Role (id_role, nom_role) VALUES (8, 'Responsable boutique');
INSERT INTO Role (id_role, nom_role) VALUES (9, 'Vendeur');
INSERT INTO Role (id_role, nom_role) VALUES (10, 'Comptable');


/* ============================================================================
Prestation
============================================================================ */
INSERT INTO Prestation (id_prestation, nom_prestation) VALUES (1, 'Photo de l animal');
INSERT INTO Prestation (id_prestation, nom_prestation) VALUES (2, 'Fond d ecran');
INSERT INTO Prestation (id_prestation, nom_prestation) VALUES (3, 'Visite gratuite');
INSERT INTO Prestation (id_prestation, nom_prestation) VALUES (4, 'Goodies exclusifs');
INSERT INTO Prestation (id_prestation, nom_prestation) VALUES (5, 'Acces coulisses');


/* ============================================================================
Soin
============================================================================ */
INSERT INTO Soin (id_soin, nom_soin, type_soin, description)
VALUES (1, 'Vaccination', 'Simple', 'Preventif: Injection de vaccin annuel');

INSERT INTO Soin (id_soin, nom_soin, type_soin, description)
VALUES (2, 'Detartrage', 'Simple', 'Dentaire: Nettoyage des dents');

INSERT INTO Soin (id_soin, nom_soin, type_soin, description)
VALUES (3, 'Pansement', 'Simple', 'Curatif: Traitement de blessure superficielle');

INSERT INTO Soin (id_soin, nom_soin, type_soin, description)
VALUES (4, 'Operation', 'Complexe', 'Chirurgical: Intervention chirurgicale complexe');

INSERT INTO Soin (id_soin, nom_soin, type_soin, description)
VALUES (5, 'Bilan de sante', 'Simple', 'Preventif: Controle general de l etat de sante');

INSERT INTO Soin (id_soin, nom_soin, type_soin, description)
VALUES (6, 'Analyse sanguine', 'Complexe', 'Diagnostic: Analyse sanguine complete');

INSERT INTO Soin (id_soin, nom_soin, type_soin, description)
VALUES (7, 'Nettoyage plaie', 'Simple', 'Test: soin simple');

INSERT INTO Soin (id_soin, nom_soin, type_soin, description)
VALUES (8, 'Chirurgie lourde', 'Complexe', 'Test: soin complexe');

INSERT INTO Soin (id_soin, nom_soin, type_soin, description)
VALUES (9, 'Controle routine', 'Simple', 'Test: verification generale');

INSERT INTO Soin (id_soin, nom_soin, type_soin, description)
VALUES (10, 'Radiographie', 'Complexe', 'Test: examen radiologique');


/* ============================================================================
Espece
============================================================================ */
INSERT INTO Espece (id_espece, nom_usuel, nom_latin, est_menacee) VALUES (1, 'Lion', 'Panthera leo', 0);
INSERT INTO Espece (id_espece, nom_usuel, nom_latin, est_menacee) VALUES (2, 'Elephant d Afrique', 'Loxodonta africana', 1);
INSERT INTO Espece (id_espece, nom_usuel, nom_latin, est_menacee) VALUES (3, 'Panda geant', 'Ailuropoda melanoleuca', 1);
INSERT INTO Espece (id_espece, nom_usuel, nom_latin, est_menacee) VALUES (4, 'Aigle royal', 'Aquila chrysaetos', 0);
INSERT INTO Espece (id_espece, nom_usuel, nom_latin, est_menacee) VALUES (5, 'Grizzly', 'Ursus arctos horribilis', 0);
INSERT INTO Espece (id_espece, nom_usuel, nom_latin, est_menacee) VALUES (6, 'Girafe', 'Giraffa camelopardalis', 0);
INSERT INTO Espece (id_espece, nom_usuel, nom_latin, est_menacee) VALUES (7, 'Zebre', 'Equus quagga', 0);


/* ============================================================================
Cohabiter
============================================================================ */
INSERT INTO Cohabiter (id_espece1, id_espece2) VALUES (1, 2);
INSERT INTO Cohabiter (id_espece1, id_espece2) VALUES (2, 3);
INSERT INTO Cohabiter (id_espece1, id_espece2) VALUES (4, 5);
INSERT INTO Cohabiter (id_espece1, id_espece2) VALUES (6, 7);


/* ============================================================================
Personnel
============================================================================ */
/* mot de passe temporaire : zooland123 */
INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (20, 'admin', 'admin', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2024-01-01','YYYY-MM-DD'), 2500, NULL);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (21, 'soigneur', 'soigneur', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2024-01-01','YYYY-MM-DD'), 2500, NULL);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (22, 'boutique', 'boutique', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2024-01-01','YYYY-MM-DD'), 2500, NULL);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (23, 'Djabri', 'Amine', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2024-01-10','YYYY-MM-DD'), 3500, NULL);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (24, 'Djafri', 'Cylia', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2024-02-15','YYYY-MM-DD'), 3600, NULL);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (1, 'Bernard', 'Paul', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2010-01-10','YYYY-MM-DD'), 4500.00, NULL);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (2, 'Petit', 'Sophie', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2012-03-15','YYYY-MM-DD'), 3200.00, 1);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (3, 'Garcia', 'Lucas', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2013-06-01','YYYY-MM-DD'), 2100.00, 2);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (4, 'Dupuis', 'Marie', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2014-09-01','YYYY-MM-DD'), 2100.00, 2);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (5, 'Renard', 'Thomas', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2015-02-14','YYYY-MM-DD'), 3500.00, 1);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (6, 'Fontaine', 'Julie', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2016-05-20','YYYY-MM-DD'), 1900.00, 1);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (7, 'Colin', 'Marc', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2017-01-10','YYYY-MM-DD'), 1800.00, 1);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (8, 'Lebrun', 'Emma', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2018-11-03','YYYY-MM-DD'), 2000.00, 1);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (9, 'Vidal', 'Pierre', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2019-04-22','YYYY-MM-DD'), 2800.00, 1);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (10, 'Morel', 'Laura', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2019-07-15','YYYY-MM-DD'), 2100.00, 2);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (11, 'Simon', 'Antoine', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2020-01-20','YYYY-MM-DD'), 1900.00, 2);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (12, 'Michel', 'Celine', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2020-08-10','YYYY-MM-DD'), 1800.00, 6);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (13, 'Laurent', 'Hugo', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2021-03-05','YYYY-MM-DD'), 1800.00, 6);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (14, 'David', 'Ines', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2021-09-12','YYYY-MM-DD'), 1800.00, 7);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (15, 'Roux', 'Julien', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2022-02-01','YYYY-MM-DD'), 1700.00, 8);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (16, 'Faure', 'Camille', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2022-06-15','YYYY-MM-DD'), 1700.00, 8);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (17, 'Giraud', 'Nicolas', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2023-01-10','YYYY-MM-DD'), 1700.00, 8);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (18, 'Blanc', 'Oceane', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2023-04-20','YYYY-MM-DD'), 2600.00, 1);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (19, 'Chevalier', 'Raphael', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2023-09-01','YYYY-MM-DD'), 1800.00, 6);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (27, 'Martin', 'Leo', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2024-05-01','YYYY-MM-DD'), 2100, 2);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (28, 'Dubois', 'Nora', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2024-05-01','YYYY-MM-DD'), 2100, 2);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (29, 'Leroux', 'Mei', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2024-05-01','YYYY-MM-DD'), 2100, 2);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (30, 'Renaud', 'Axel', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2024-05-01','YYYY-MM-DD'), 2100, 2);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (31, 'Bernier', 'Noa', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2024-05-01','YYYY-MM-DD'), 2100, 2);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (32, 'Moreau', 'Lina', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2024-05-01','YYYY-MM-DD'), 2100, 2);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (33, 'Garin', 'Yanis', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2024-05-01','YYYY-MM-DD'), 2100, 2);

INSERT INTO Personnel (id_personnel, nom_personnel, prenom_personnel, mot_de_passe, date_entree, salaire, id_manager)
VALUES (34, 'Rossi', 'Marco', '$2b$12$nrNExogpZF1EZMZQAspPXeBCdkkGMsYblDLFjtKsv5HJt/wBaNZP2', TO_DATE('2024-05-01','YYYY-MM-DD'), 3200, 1);


/* ============================================================================
Historique_emploi
============================================================================ */
INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (1, TO_DATE('2010-01-10','YYYY-MM-DD'), NULL, 1, 1);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (2, TO_DATE('2012-03-15','YYYY-MM-DD'), TO_DATE('2018-12-31','YYYY-MM-DD'), 2, 3);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (3, TO_DATE('2019-01-01','YYYY-MM-DD'), NULL, 2, 2);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (4, TO_DATE('2013-06-01','YYYY-MM-DD'), NULL, 3, 3);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (5, TO_DATE('2014-09-01','YYYY-MM-DD'), TO_DATE('2019-08-31','YYYY-MM-DD'), 4, 3);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (6, TO_DATE('2019-09-01','YYYY-MM-DD'), NULL, 4, 4);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (7, TO_DATE('2015-02-14','YYYY-MM-DD'), TO_DATE('2020-01-31','YYYY-MM-DD'), 5, 5);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (8, TO_DATE('2020-02-01','YYYY-MM-DD'), NULL, 5, 3);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (9, TO_DATE('2016-05-20','YYYY-MM-DD'), NULL, 6, 5);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (10, TO_DATE('2017-01-10','YYYY-MM-DD'), NULL, 7, 6);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (11, TO_DATE('2018-11-03','YYYY-MM-DD'), TO_DATE('2021-12-31','YYYY-MM-DD'), 8, 8);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (12, TO_DATE('2022-01-01','YYYY-MM-DD'), NULL, 8, 7);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (13, TO_DATE('2019-04-22','YYYY-MM-DD'), NULL, 9, 9);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (14, TO_DATE('2019-07-15','YYYY-MM-DD'), NULL, 10, 3);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (15, TO_DATE('2020-01-20','YYYY-MM-DD'), NULL, 11, 3);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (16, TO_DATE('2020-08-10','YYYY-MM-DD'), NULL, 12, 6);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (17, TO_DATE('2021-03-05','YYYY-MM-DD'), NULL, 13, 6);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (18, TO_DATE('2021-09-12','YYYY-MM-DD'), NULL, 14, 7);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (19, TO_DATE('2022-02-01','YYYY-MM-DD'), NULL, 15, 8);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (20, TO_DATE('2022-06-15','YYYY-MM-DD'), NULL, 16, 8);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (21, TO_DATE('2023-01-10','YYYY-MM-DD'), NULL, 17, 8);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (23, TO_DATE('2023-09-01','YYYY-MM-DD'), NULL, 19, 6);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (24, TO_DATE('2024-01-01','YYYY-MM-DD'), NULL, 20, 1);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (25, TO_DATE('2024-01-01','YYYY-MM-DD'), NULL, 21, 3);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (26, TO_DATE('2024-01-01','YYYY-MM-DD'), NULL, 22, 8);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (27, TO_DATE('2024-01-10','YYYY-MM-DD'), NULL, 23, 1);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (28, TO_DATE('2024-02-15','YYYY-MM-DD'), NULL, 24, 1);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (31, TO_DATE('2024-05-01','YYYY-MM-DD'), NULL, 27, 4);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (32, TO_DATE('2024-05-01','YYYY-MM-DD'), NULL, 28, 4);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (33, TO_DATE('2024-05-01','YYYY-MM-DD'), NULL, 29, 4);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (34, TO_DATE('2024-05-01','YYYY-MM-DD'), NULL, 30, 4);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (35, TO_DATE('2024-05-01','YYYY-MM-DD'), NULL, 31, 4);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (36, TO_DATE('2024-05-01','YYYY-MM-DD'), NULL, 32, 4);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (37, TO_DATE('2024-05-01','YYYY-MM-DD'), NULL, 33, 4);

INSERT INTO Historique_emploi (id_historique_emploi, date_debut, date_fin, id_personnel, id_role)
VALUES (38, TO_DATE('2024-05-01','YYYY-MM-DD'), NULL, 34, 5);


/* ============================================================================
Zone
============================================================================ */
INSERT INTO Zone (id_zone, nom_zone, id_historique_emploi) VALUES (1, 'Felins', 3);
INSERT INTO Zone (id_zone, nom_zone, id_historique_emploi) VALUES (2, 'Pachydermes', 6);
INSERT INTO Zone (id_zone, nom_zone, id_historique_emploi) VALUES (3, 'Rapaces', 9);
INSERT INTO Zone (id_zone, nom_zone, id_historique_emploi) VALUES (4, 'Ours', 10);
INSERT INTO Zone (id_zone, nom_zone, id_historique_emploi) VALUES (5, 'Savane', 15);


/* ============================================================================
Boutique
============================================================================ */
INSERT INTO Boutique (id_boutique, type_boutique, id_personnel, id_zone) VALUES (1, 'Souvenirs', 8, 1);
INSERT INTO Boutique (id_boutique, type_boutique, id_personnel, id_zone) VALUES (2, 'Snack', 14, 2);
INSERT INTO Boutique (id_boutique, type_boutique, id_personnel, id_zone) VALUES (3, 'Souvenirs', 8, 3);
INSERT INTO Boutique (id_boutique, type_boutique, id_personnel, id_zone) VALUES (4, 'Snack', 22, 5);


/* ============================================================================
Chiffre_affaires
============================================================================ */
INSERT INTO Chiffre_affaires (id_ca, id_boutique, date_ca, montant) VALUES (1, 1, TO_DATE('2025-12-15','YYYY-MM-DD'), 1500.00);
INSERT INTO Chiffre_affaires (id_ca, id_boutique, date_ca, montant) VALUES (2, 1, TO_DATE('2026-01-10','YYYY-MM-DD'), 1750.00);
INSERT INTO Chiffre_affaires (id_ca, id_boutique, date_ca, montant) VALUES (3, 1, TO_DATE('2026-02-01','YYYY-MM-DD'), 1600.00);
INSERT INTO Chiffre_affaires (id_ca, id_boutique, date_ca, montant) VALUES (4, 1, TO_DATE('2026-03-01','YYYY-MM-DD'), 1820.00);
INSERT INTO Chiffre_affaires (id_ca, id_boutique, date_ca, montant) VALUES (5, 2, TO_DATE('2025-12-15','YYYY-MM-DD'), 3200.00);
INSERT INTO Chiffre_affaires (id_ca, id_boutique, date_ca, montant) VALUES (6, 2, TO_DATE('2026-01-10','YYYY-MM-DD'), 2900.00);
INSERT INTO Chiffre_affaires (id_ca, id_boutique, date_ca, montant) VALUES (7, 2, TO_DATE('2026-02-01','YYYY-MM-DD'), 3100.00);
INSERT INTO Chiffre_affaires (id_ca, id_boutique, date_ca, montant) VALUES (8, 2, TO_DATE('2026-03-01','YYYY-MM-DD'), 3400.00);
INSERT INTO Chiffre_affaires (id_ca, id_boutique, date_ca, montant) VALUES (9, 3, TO_DATE('2025-12-15','YYYY-MM-DD'), 800.00);
INSERT INTO Chiffre_affaires (id_ca, id_boutique, date_ca, montant) VALUES (10, 3, TO_DATE('2026-01-10','YYYY-MM-DD'), 950.00);
INSERT INTO Chiffre_affaires (id_ca, id_boutique, date_ca, montant) VALUES (11, 3, TO_DATE('2026-02-01','YYYY-MM-DD'), 870.00);
INSERT INTO Chiffre_affaires (id_ca, id_boutique, date_ca, montant) VALUES (12, 3, TO_DATE('2026-03-01','YYYY-MM-DD'), 910.00);
INSERT INTO Chiffre_affaires (id_ca, id_boutique, date_ca, montant) VALUES (13, 4, TO_DATE('2025-12-15','YYYY-MM-DD'), 1200.00);
INSERT INTO Chiffre_affaires (id_ca, id_boutique, date_ca, montant) VALUES (14, 4, TO_DATE('2026-01-10','YYYY-MM-DD'), 1350.00);
INSERT INTO Chiffre_affaires (id_ca, id_boutique, date_ca, montant) VALUES (15, 4, TO_DATE('2026-02-01','YYYY-MM-DD'), 1420.00);
INSERT INTO Chiffre_affaires (id_ca, id_boutique, date_ca, montant) VALUES (16, 4, TO_DATE('2026-03-01','YYYY-MM-DD'), 1550.00);


/* ============================================================================
Travailler
============================================================================ */
INSERT INTO Travailler (id_personnel, id_boutique) VALUES (8, 1);
INSERT INTO Travailler (id_personnel, id_boutique) VALUES (15, 1);
INSERT INTO Travailler (id_personnel, id_boutique) VALUES (16, 1);
INSERT INTO Travailler (id_personnel, id_boutique) VALUES (14, 2);
INSERT INTO Travailler (id_personnel, id_boutique) VALUES (17, 2);
INSERT INTO Travailler (id_personnel, id_boutique) VALUES (8, 3);
INSERT INTO Travailler (id_personnel, id_boutique) VALUES (15, 3);
INSERT INTO Travailler (id_personnel, id_boutique) VALUES (12, 1);
INSERT INTO Travailler (id_personnel, id_boutique) VALUES (13, 2);
INSERT INTO Travailler (id_personnel, id_boutique) VALUES (19, 3);
INSERT INTO Travailler (id_personnel, id_boutique) VALUES (22, 4);


/* ============================================================================
Enclos
============================================================================ */
INSERT INTO Enclos (id_enclos, surface, latitude, longitude, particularites, id_zone)
VALUES (1, 500.00, 46.123400, 1.876500, 'Vegetation dense', 1);

INSERT INTO Enclos (id_enclos, surface, latitude, longitude, particularites, id_zone)
VALUES (2, 800.00, 46.124000, 1.877000, 'Point d eau', 2);

INSERT INTO Enclos (id_enclos, surface, latitude, longitude, particularites, id_zone)
VALUES (3, 200.00, 46.125000, 1.878000, 'Voliere haute', 3);

INSERT INTO Enclos (id_enclos, surface, latitude, longitude, particularites, id_zone)
VALUES (4, 600.00, 46.126000, 1.879000, 'Amenagement special pour grizzly', 4);

INSERT INTO Enclos (id_enclos, surface, latitude, longitude, particularites, id_zone)
VALUES (5, 950.00, 46.127000, 1.880000, 'Grande plaine ouverte', 5);


/* ============================================================================
Reparation
============================================================================ */
INSERT INTO Reparation (id_reparation, date_reparation, nature, id_enclos, id_prestataire, id_personnel)
VALUES (1, TO_DATE('2025-11-10','YYYY-MM-DD'), 'Reparation cloture electrique', 1, 1, 6);

INSERT INTO Reparation (id_reparation, date_reparation, nature, id_enclos, id_prestataire, id_personnel)
VALUES (2, TO_DATE('2025-12-05','YYYY-MM-DD'), 'Installation eclairage enclos', 2, 2, 7);

INSERT INTO Reparation (id_reparation, date_reparation, nature, id_enclos, id_prestataire, id_personnel)
VALUES (3, TO_DATE('2026-01-12','YYYY-MM-DD'), 'Reparation toiture voliere', 3, 3, 6);

INSERT INTO Reparation (id_reparation, date_reparation, nature, id_enclos, id_prestataire, id_personnel)
VALUES (4, TO_DATE('2026-02-20','YYYY-MM-DD'), 'Remplacement vitre enclos ours', 4, 1, 7);

INSERT INTO Reparation (id_reparation, date_reparation, nature, id_enclos, id_prestataire, id_personnel)
VALUES (5, TO_DATE('2026-03-05','YYYY-MM-DD'), 'Controle systeme eau', 2, 5, 19);

INSERT INTO Reparation (id_reparation, date_reparation, nature, id_enclos, id_prestataire, id_personnel)
VALUES (6, TO_DATE('2026-03-08','YYYY-MM-DD'), 'Renforcement cloture savane', 5, 4, 12);


/* ============================================================================
Specialiser
============================================================================ */
INSERT INTO Specialiser (id_personnel, id_espece) VALUES (3, 1);
INSERT INTO Specialiser (id_personnel, id_espece) VALUES (3, 2);
INSERT INTO Specialiser (id_personnel, id_espece) VALUES (4, 2);
INSERT INTO Specialiser (id_personnel, id_espece) VALUES (5, 3);
INSERT INTO Specialiser (id_personnel, id_espece) VALUES (10, 3);
INSERT INTO Specialiser (id_personnel, id_espece) VALUES (10, 5);
INSERT INTO Specialiser (id_personnel, id_espece) VALUES (11, 4);
INSERT INTO Specialiser (id_personnel, id_espece) VALUES (21, 1);
INSERT INTO Specialiser (id_personnel, id_espece) VALUES (21, 6);
INSERT INTO Specialiser (id_personnel, id_espece) VALUES (21, 7);

INSERT INTO Specialiser (id_personnel, id_espece) VALUES (27, 1);
INSERT INTO Specialiser (id_personnel, id_espece) VALUES (27, 2);
INSERT INTO Specialiser (id_personnel, id_espece) VALUES (28, 2);
INSERT INTO Specialiser (id_personnel, id_espece) VALUES (29, 3);
INSERT INTO Specialiser (id_personnel, id_espece) VALUES (30, 4);
INSERT INTO Specialiser (id_personnel, id_espece) VALUES (31, 5);
INSERT INTO Specialiser (id_personnel, id_espece) VALUES (32, 6);
INSERT INTO Specialiser (id_personnel, id_espece) VALUES (32, 7);
INSERT INTO Specialiser (id_personnel, id_espece) VALUES (33, 7);


/* ============================================================================
Animal
============================================================================ */
INSERT INTO Animal (rfid, nom_animal, date_naissance, poids, regime_alimentaire, zoo, id_espece, id_enclos, id_personnel)
VALUES ('RFID001', 'Simba', TO_DATE('2018-05-10','YYYY-MM-DD'), 190.50, 'Carnivore', 'Babentruk', 1, 1, 3);

INSERT INTO Animal (rfid, nom_animal, date_naissance, poids, regime_alimentaire, zoo, id_espece, id_enclos, id_personnel)
VALUES ('RFID002', 'Nala', TO_DATE('2019-08-22','YYYY-MM-DD'), 126.00, 'Carnivore', 'Babentruk', 1, 1, 3);

INSERT INTO Animal (rfid, nom_animal, date_naissance, poids, regime_alimentaire, zoo, id_espece, id_enclos, id_personnel)
VALUES ('RFID003', 'Dumbo', TO_DATE('2010-03-22','YYYY-MM-DD'), 4200.00, 'Herbivore', 'Babentruk', 2, 2, 4);

INSERT INTO Animal (rfid, nom_animal, date_naissance, poids, regime_alimentaire, zoo, id_espece, id_enclos, id_personnel)
VALUES ('RFID004', 'Bambou', TO_DATE('2015-07-14','YYYY-MM-DD'), 95.00, 'Herbivore', 'Babentruk', 3, 2, 10);

INSERT INTO Animal (rfid, nom_animal, date_naissance, poids, regime_alimentaire, zoo, id_espece, id_enclos, id_personnel)
VALUES ('RFID005', 'Zeus', TO_DATE('2017-11-30','YYYY-MM-DD'), 5.20, 'Carnivore', 'Babentruk', 4, 3, 11);

INSERT INTO Animal (rfid, nom_animal, date_naissance, poids, regime_alimentaire, zoo, id_espece, id_enclos, id_personnel)
VALUES ('RFID006', 'Kodiak', TO_DATE('2012-04-05','YYYY-MM-DD'), 320.00, 'Omnivore', 'Babentruk', 5, 4, 10);

INSERT INTO Animal (rfid, nom_animal, date_naissance, poids, regime_alimentaire, zoo, id_espece, id_enclos, id_personnel)
VALUES ('RFID007', 'Rex', TO_DATE('2005-01-15','YYYY-MM-DD'), 210.00, 'Carnivore', 'Zoo de Berlin', 1, 1, 3);

INSERT INTO Animal (rfid, nom_animal, date_naissance, poids, regime_alimentaire, zoo, id_espece, id_enclos, id_personnel)
VALUES ('RFID008', 'Sara', TO_DATE('2006-03-20','YYYY-MM-DD'), 140.00, 'Carnivore', 'Zoo de Madrid', 1, 1, 3);

INSERT INTO Animal (rfid, nom_animal, date_naissance, poids, regime_alimentaire, zoo, id_espece, id_enclos, id_personnel)
VALUES ('RFID009', 'Kira', TO_DATE('2021-06-18','YYYY-MM-DD'), 780.00, 'Herbivore', 'Babentruk', 6, 5, 21);

INSERT INTO Animal (rfid, nom_animal, date_naissance, poids, regime_alimentaire, zoo, id_espece, id_enclos, id_personnel)
VALUES ('RFID010', 'Flash', TO_DATE('2022-02-11','YYYY-MM-DD'), 320.00, 'Herbivore', 'Babentruk', 7, 5, 21);

INSERT INTO Animal (rfid, nom_animal, date_naissance, poids, regime_alimentaire, zoo, id_espece, id_enclos, id_personnel)
VALUES ('RFID011', 'Mufasa', TO_DATE('2016-02-01','YYYY-MM-DD'), 200.00, 'Carnivore', 'Babentruk', 1, 1, 27);

INSERT INTO Animal (rfid, nom_animal, date_naissance, poids, regime_alimentaire, zoo, id_espece, id_enclos, id_personnel)
VALUES ('RFID012', 'Tantor', TO_DATE('2011-04-10','YYYY-MM-DD'), 4100.00, 'Herbivore', 'Babentruk', 2, 2, 28);

INSERT INTO Animal (rfid, nom_animal, date_naissance, poids, regime_alimentaire, zoo, id_espece, id_enclos, id_personnel)
VALUES ('RFID013', 'Ling', TO_DATE('2018-09-20','YYYY-MM-DD'), 96.00, 'Herbivore', 'Babentruk', 3, 2, 29);

INSERT INTO Animal (rfid, nom_animal, date_naissance, poids, regime_alimentaire, zoo, id_espece, id_enclos, id_personnel)
VALUES ('RFID014', 'Aquila', TO_DATE('2019-01-08','YYYY-MM-DD'), 6.10, 'Carnivore', 'Babentruk', 4, 3, 30);

INSERT INTO Animal (rfid, nom_animal, date_naissance, poids, regime_alimentaire, zoo, id_espece, id_enclos, id_personnel)
VALUES ('RFID015', 'Bjorn', TO_DATE('2014-12-12','YYYY-MM-DD'), 300.00, 'Omnivore', 'Babentruk', 5, 4, 31);

INSERT INTO Animal (rfid, nom_animal, date_naissance, poids, regime_alimentaire, zoo, id_espece, id_enclos, id_personnel)
VALUES ('RFID016', 'Longneck', TO_DATE('2020-06-01','YYYY-MM-DD'), 790.00, 'Herbivore', 'Babentruk', 6, 5, 32);

INSERT INTO Animal (rfid, nom_animal, date_naissance, poids, regime_alimentaire, zoo, id_espece, id_enclos, id_personnel)
VALUES ('RFID017', 'Stripe', TO_DATE('2021-02-11','YYYY-MM-DD'), 330.00, 'Herbivore', 'Babentruk', 7, 5, 33);


/* ============================================================================
Parent_fils
============================================================================ */
INSERT INTO Parent_fils (id_animal_parent, id_animal_fils) VALUES ('RFID007', 'RFID001');
INSERT INTO Parent_fils (id_animal_parent, id_animal_fils) VALUES ('RFID008', 'RFID001');
INSERT INTO Parent_fils (id_animal_parent, id_animal_fils) VALUES ('RFID007', 'RFID002');


/* ============================================================================
Parrainage
============================================================================ */
INSERT INTO Parrainage (id_parrainage, date_debut_parrainage, date_fin_parrainage, niveau, rfid, id_visiteur)
VALUES (1, TO_DATE('2025-01-01','YYYY-MM-DD'), TO_DATE('2025-12-31','YYYY-MM-DD'), 'Or', 'RFID001', 1);

INSERT INTO Parrainage (id_parrainage, date_debut_parrainage, date_fin_parrainage, niveau, rfid, id_visiteur)
VALUES (2, TO_DATE('2025-03-01','YYYY-MM-DD'), TO_DATE('2026-03-01','YYYY-MM-DD'), 'Argent', 'RFID003', 2);

INSERT INTO Parrainage (id_parrainage, date_debut_parrainage, date_fin_parrainage, niveau, rfid, id_visiteur)
VALUES (3, TO_DATE('2025-06-01','YYYY-MM-DD'), NULL, 'Bronze', 'RFID004', 3);

INSERT INTO Parrainage (id_parrainage, date_debut_parrainage, date_fin_parrainage, niveau, rfid, id_visiteur)
VALUES (4, TO_DATE('2025-07-15','YYYY-MM-DD'), TO_DATE('2026-07-15','YYYY-MM-DD'), 'Or', 'RFID002', 4);

INSERT INTO Parrainage (id_parrainage, date_debut_parrainage, date_fin_parrainage, niveau, rfid, id_visiteur)
VALUES (5, TO_DATE('2025-09-01','YYYY-MM-DD'), NULL, 'Argent', 'RFID006', 5);

INSERT INTO Parrainage (id_parrainage, date_debut_parrainage, date_fin_parrainage, niveau, rfid, id_visiteur)
VALUES (6, TO_DATE('2026-01-10','YYYY-MM-DD'), NULL, 'Bronze', 'RFID009', 6);

INSERT INTO Parrainage (id_parrainage, date_debut_parrainage, date_fin_parrainage, niveau, rfid, id_visiteur)
VALUES (7, TO_DATE('2026-02-05','YYYY-MM-DD'), TO_DATE('2027-02-05','YYYY-MM-DD'), 'Or', 'RFID010', 7);


/* ============================================================================
Offrir
============================================================================ */
INSERT INTO Offrir (id_parrainage, id_prestation) VALUES (1, 1);
INSERT INTO Offrir (id_parrainage, id_prestation) VALUES (1, 2);
INSERT INTO Offrir (id_parrainage, id_prestation) VALUES (1, 3);
INSERT INTO Offrir (id_parrainage, id_prestation) VALUES (1, 4);
INSERT INTO Offrir (id_parrainage, id_prestation) VALUES (1, 5);

INSERT INTO Offrir (id_parrainage, id_prestation) VALUES (2, 1);
INSERT INTO Offrir (id_parrainage, id_prestation) VALUES (2, 2);
INSERT INTO Offrir (id_parrainage, id_prestation) VALUES (2, 3);

INSERT INTO Offrir (id_parrainage, id_prestation) VALUES (3, 1);

INSERT INTO Offrir (id_parrainage, id_prestation) VALUES (4, 1);
INSERT INTO Offrir (id_parrainage, id_prestation) VALUES (4, 2);
INSERT INTO Offrir (id_parrainage, id_prestation) VALUES (4, 3);
INSERT INTO Offrir (id_parrainage, id_prestation) VALUES (4, 4);
INSERT INTO Offrir (id_parrainage, id_prestation) VALUES (4, 5);

INSERT INTO Offrir (id_parrainage, id_prestation) VALUES (5, 1);
INSERT INTO Offrir (id_parrainage, id_prestation) VALUES (5, 2);
INSERT INTO Offrir (id_parrainage, id_prestation) VALUES (5, 3);

INSERT INTO Offrir (id_parrainage, id_prestation) VALUES (6, 1);
INSERT INTO Offrir (id_parrainage, id_prestation) VALUES (6, 2);

INSERT INTO Offrir (id_parrainage, id_prestation) VALUES (7, 1);
INSERT INTO Offrir (id_parrainage, id_prestation) VALUES (7, 2);
INSERT INTO Offrir (id_parrainage, id_prestation) VALUES (7, 3);
INSERT INTO Offrir (id_parrainage, id_prestation) VALUES (7, 4);
INSERT INTO Offrir (id_parrainage, id_prestation) VALUES (7, 5);


/* ============================================================================
Historique_soins
============================================================================ */
INSERT INTO Historique_soins (id_historique_soins, type_soigneur, date_soin, id_soin, id_personnel, rfid)
VALUES (1, 'Attitre', TO_DATE('2025-11-01','YYYY-MM-DD'), 1, 3, 'RFID001');

INSERT INTO Historique_soins (id_historique_soins, type_soigneur, date_soin, id_soin, id_personnel, rfid)
VALUES (2, 'Remplacant', TO_DATE('2025-11-20','YYYY-MM-DD'), 2, 27, 'RFID001');

INSERT INTO Historique_soins (id_historique_soins, type_soigneur, date_soin, id_soin, id_personnel, rfid)
VALUES (3, 'Attitre', TO_DATE('2025-12-10','YYYY-MM-DD'), 3, 4, 'RFID003');

INSERT INTO Historique_soins (id_historique_soins, type_soigneur, date_soin, id_soin, id_personnel, rfid)
VALUES (5, 'Attitre', TO_DATE('2026-01-10','YYYY-MM-DD'), 5, 11, 'RFID005');

INSERT INTO Historique_soins (id_historique_soins, type_soigneur, date_soin, id_soin, id_personnel, rfid)
VALUES (6, 'Remplacant', TO_DATE('2026-01-25','YYYY-MM-DD'), 1, 31, 'RFID006');

INSERT INTO Historique_soins (id_historique_soins, type_soigneur, date_soin, id_soin, id_personnel, rfid)
VALUES (7, 'Attitre', TO_DATE('2026-02-05','YYYY-MM-DD'), 2, 3, 'RFID002');

INSERT INTO Historique_soins (id_historique_soins, type_soigneur, date_soin, id_soin, id_personnel, rfid)
VALUES (8, 'Veterinaire', TO_DATE('2026-02-12','YYYY-MM-DD'), 4, 34, 'RFID003');

INSERT INTO Historique_soins (id_historique_soins, type_soigneur, date_soin, id_soin, id_personnel, rfid)
VALUES (9, 'Attitre', TO_DATE('2026-02-20','YYYY-MM-DD'), 6, 21, 'RFID009');

INSERT INTO Historique_soins (id_historique_soins, type_soigneur, date_soin, id_soin, id_personnel, rfid)
VALUES (10, 'Attitre', TO_DATE('2026-03-02','YYYY-MM-DD'), 5, 21, 'RFID010');

INSERT INTO Historique_soins (id_historique_soins, type_soigneur, date_soin, id_soin, id_personnel, rfid)
VALUES (11, 'Attitre', TO_DATE('2026-03-05','YYYY-MM-DD'), 7, 3, 'RFID001');

INSERT INTO Historique_soins (id_historique_soins, type_soigneur, date_soin, id_soin, id_personnel, rfid)
VALUES (12, 'Remplacant', TO_DATE('2026-03-06','YYYY-MM-DD'), 7, 27, 'RFID001');

INSERT INTO Historique_soins (id_historique_soins, type_soigneur, date_soin, id_soin, id_personnel, rfid)
VALUES (15, 'Attitre', TO_DATE('2026-03-09','YYYY-MM-DD'), 9, 27, 'RFID011');

INSERT INTO Historique_soins (id_historique_soins, type_soigneur, date_soin, id_soin, id_personnel, rfid)
VALUES (16, 'Remplacant', TO_DATE('2026-03-10','YYYY-MM-DD'), 3, 3, 'RFID011');

INSERT INTO Historique_soins (id_historique_soins, type_soigneur, date_soin, id_soin, id_personnel, rfid)
VALUES (17, 'Veterinaire', TO_DATE('2026-03-11','YYYY-MM-DD'), 8, 34, 'RFID011');

INSERT INTO Historique_soins (id_historique_soins, type_soigneur, date_soin, id_soin, id_personnel, rfid)
VALUES (18, 'Attitre', TO_DATE('2026-03-12','YYYY-MM-DD'), 1, 28, 'RFID012');

INSERT INTO Historique_soins (id_historique_soins, type_soigneur, date_soin, id_soin, id_personnel, rfid)
VALUES (19, 'Remplacant', TO_DATE('2026-03-13','YYYY-MM-DD'), 3, 27, 'RFID012');

INSERT INTO Historique_soins (id_historique_soins, type_soigneur, date_soin, id_soin, id_personnel, rfid)
VALUES (20, 'Attitre', TO_DATE('2026-03-14','YYYY-MM-DD'), 5, 29, 'RFID013');

INSERT INTO Historique_soins (id_historique_soins, type_soigneur, date_soin, id_soin, id_personnel, rfid)
VALUES (22, 'Attitre', TO_DATE('2026-03-16','YYYY-MM-DD'), 5, 30, 'RFID014');

INSERT INTO Historique_soins (id_historique_soins, type_soigneur, date_soin, id_soin, id_personnel, rfid)
VALUES (23, 'Attitre', TO_DATE('2026-03-17','YYYY-MM-DD'), 2, 31, 'RFID015');

INSERT INTO Historique_soins (id_historique_soins, type_soigneur, date_soin, id_soin, id_personnel, rfid)
VALUES (24, 'Remplacant', TO_DATE('2026-03-18','YYYY-MM-DD'), 7, 10, 'RFID015');

INSERT INTO Historique_soins (id_historique_soins, type_soigneur, date_soin, id_soin, id_personnel, rfid)
VALUES (25, 'Attitre', TO_DATE('2026-03-19','YYYY-MM-DD'), 1, 32, 'RFID016');

INSERT INTO Historique_soins (id_historique_soins, type_soigneur, date_soin, id_soin, id_personnel, rfid)
VALUES (26, 'Remplacant', TO_DATE('2026-03-20','YYYY-MM-DD'), 1, 21, 'RFID017');


/* ============================================================================
Nourrissage
============================================================================ */
INSERT INTO Nourrissage (id_nourrissage, date_nourrissage, dose_nourrissage, nom_aliment, remarques_nourrissage, id_personnel, rfid)
VALUES (1, TO_DATE('2026-03-01 08:00:00','YYYY-MM-DD HH24:MI:SS'), 5.00, 'Viande rouge', 'RAS', 3, 'RFID001');

INSERT INTO Nourrissage (id_nourrissage, date_nourrissage, dose_nourrissage, nom_aliment, remarques_nourrissage, id_personnel, rfid)
VALUES (2, TO_DATE('2026-03-01 08:15:00','YYYY-MM-DD HH24:MI:SS'), 4.50, 'Viande rouge', 'Bon appetit', 3, 'RFID002');

INSERT INTO Nourrissage (id_nourrissage, date_nourrissage, dose_nourrissage, nom_aliment, remarques_nourrissage, id_personnel, rfid)
VALUES (3, TO_DATE('2026-03-01 09:00:00','YYYY-MM-DD HH24:MI:SS'), 50.00, 'Foin', 'RAS', 4, 'RFID003');

INSERT INTO Nourrissage (id_nourrissage, date_nourrissage, dose_nourrissage, nom_aliment, remarques_nourrissage, id_personnel, rfid)
VALUES (4, TO_DATE('2026-03-01 09:30:00','YYYY-MM-DD HH24:MI:SS'), 8.00, 'Bambou', 'Mange peu', 10, 'RFID004');

INSERT INTO Nourrissage (id_nourrissage, date_nourrissage, dose_nourrissage, nom_aliment, remarques_nourrissage, id_personnel, rfid)
VALUES (5, TO_DATE('2026-03-01 10:00:00','YYYY-MM-DD HH24:MI:SS'), 0.50, 'Rongeurs', 'RAS', 11, 'RFID005');

INSERT INTO Nourrissage (id_nourrissage, date_nourrissage, dose_nourrissage, nom_aliment, remarques_nourrissage, id_personnel, rfid)
VALUES (6, TO_DATE('2026-03-01 10:30:00','YYYY-MM-DD HH24:MI:SS'), 15.00, 'Baies et poisson', 'RAS', 10, 'RFID006');

INSERT INTO Nourrissage (id_nourrissage, date_nourrissage, dose_nourrissage, nom_aliment, remarques_nourrissage, id_personnel, rfid)
VALUES (7, TO_DATE('2026-03-02 08:00:00','YYYY-MM-DD HH24:MI:SS'), 5.00, 'Viande rouge', 'RAS', 3, 'RFID001');

INSERT INTO Nourrissage (id_nourrissage, date_nourrissage, dose_nourrissage, nom_aliment, remarques_nourrissage, id_personnel, rfid)
VALUES (8, TO_DATE('2026-03-02 09:00:00','YYYY-MM-DD HH24:MI:SS'), 52.00, 'Foin', 'Tres faim', 4, 'RFID003');

INSERT INTO Nourrissage (id_nourrissage, date_nourrissage, dose_nourrissage, nom_aliment, remarques_nourrissage, id_personnel, rfid)
VALUES (9, TO_DATE('2026-03-02 10:00:00','YYYY-MM-DD HH24:MI:SS'), 22.00, 'Feuilles', 'RAS', 21, 'RFID009');

INSERT INTO Nourrissage (id_nourrissage, date_nourrissage, dose_nourrissage, nom_aliment, remarques_nourrissage, id_personnel, rfid)
VALUES (10, TO_DATE('2026-03-02 10:30:00','YYYY-MM-DD HH24:MI:SS'), 18.00, 'Herbe fraiche', 'RAS', 21, 'RFID010');
COMMIT;
