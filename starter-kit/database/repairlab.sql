CREATE TABLE CATEGORIE_EQUIPEMENT(
   id_categorie VARCHAR(50) ,
   libelle VARCHAR(50) ,
   PRIMARY KEY(id_categorie)
);

CREATE TABLE UTILISATEUR(
   id_utilisateur VARCHAR(50) ,
   nom VARCHAR(50) ,
   email VARCHAR(50) ,
   mot_de_passe_hash VARCHAR(50) ,
   role VARCHAR(50) ,
   PRIMARY KEY(id_utilisateur)
);

CREATE TABLE EQUIPEMENT(
   id_equipement VARCHAR(50) ,
   nom VARCHAR(50) ,
   statut_technique VARCHAR(50) ,
   localisation VARCHAR(50) ,
   description VARCHAR(50) ,
   visible_public BOOLEAN,
   id_categorie VARCHAR(50)  NOT NULL,
   PRIMARY KEY(id_equipement),
   FOREIGN KEY(id_categorie) REFERENCES CATEGORIE_EQUIPEMENT(id_categorie)
);

CREATE TABLE MAINTENANCE(
   id_maintenance VARCHAR(50) ,
   date_debut DATETIME,
   date_fin DATETIME,
   motif VARCHAR(50) ,
   id_equipement VARCHAR(50)  NOT NULL,
   PRIMARY KEY(id_maintenance),
   FOREIGN KEY(id_equipement) REFERENCES EQUIPEMENT(id_equipement)
);

CREATE TABLE CONTROLE(
   id_controle VARCHAR(50) ,
   date_controle DATETIME,
   compte_rendu VARCHAR(50) ,
   id_utilisateur VARCHAR(50)  NOT NULL,
   id_equipement VARCHAR(50)  NOT NULL,
   PRIMARY KEY(id_controle),
   FOREIGN KEY(id_utilisateur) REFERENCES UTILISATEUR(id_utilisateur),
   FOREIGN KEY(id_equipement) REFERENCES EQUIPEMENT(id_equipement)
);

CREATE TABLE INCIDENT(
   id_incident VARCHAR(50) ,
   titre VARCHAR(50) ,
   description VARCHAR(50) ,
   date_signalement DATETIME,
   date_resolution DATETIME,
   statut VARCHAR(50) ,
   est_technique BOOLEAN,
   est_bloquant BOOLEAN,
   id_equipement VARCHAR(50)  NOT NULL,
   id_utilisateur VARCHAR(50)  NOT NULL,
   PRIMARY KEY(id_incident),
   FOREIGN KEY(id_equipement) REFERENCES EQUIPEMENT(id_equipement),
   FOREIGN KEY(id_utilisateur) REFERENCES UTILISATEUR(id_utilisateur)
);

CREATE TABLE RESERVATION(
   id_reservation VARCHAR(50) ,
   date_debut DATE,
   date_fin DATE,
   statut VARCHAR(50) ,
   id_utilisateur VARCHAR(50)  NOT NULL,
   id_equipement VARCHAR(50)  NOT NULL,
   PRIMARY KEY(id_reservation),
   FOREIGN KEY(id_utilisateur) REFERENCES UTILISATEUR(id_utilisateur),
   FOREIGN KEY(id_equipement) REFERENCES EQUIPEMENT(id_equipement)
);

CREATE TABLE HISTORIQUE_INCIDENT(
   id_historique VARCHAR(50) ,
   ancien_etat VARCHAR(50) ,
   nouvel_etat VARCHAR(50) ,
   date_changement DATETIME,
   motif VARCHAR(50) ,
   id_incident VARCHAR(50)  NOT NULL,
   id_utilisateur VARCHAR(50)  NOT NULL,
   PRIMARY KEY(id_historique),
   FOREIGN KEY(id_incident) REFERENCES INCIDENT(id_incident),
   FOREIGN KEY(id_utilisateur) REFERENCES UTILISATEUR(id_utilisateur)
);

CREATE TABLE INTERVENTION(
   id_intervention VARCHAR(50) ,
   etat VARCHAR(50) ,
   diagnostic VARCHAR(50) ,
   action VARCHAR(50) ,
   resultat_test VARCHAR(50) ,
   date_debut DATETIME,
   date_fin DATETIME,
   id_incident VARCHAR(50)  NOT NULL,
   PRIMARY KEY(id_intervention),
   FOREIGN KEY(id_incident) REFERENCES INCIDENT(id_incident)
);

CREATE TABLE CRENEAU_PRIORITAIRE(
   id_creneau VARCHAR(50) ,
   date_debut DATETIME,
   date_fin DATETIME,
   libelle_atelier VARCHAR(50) ,
   id_reservation VARCHAR(50)  NOT NULL,
   id_equipement VARCHAR(50)  NOT NULL,
   PRIMARY KEY(id_creneau),
   UNIQUE(id_reservation),
   FOREIGN KEY(id_reservation) REFERENCES RESERVATION(id_reservation),
   FOREIGN KEY(id_equipement) REFERENCES EQUIPEMENT(id_equipement)
);

CREATE TABLE participer(
   id_utilisateur VARCHAR(50) ,
   id_intervention VARCHAR(50) ,
   PRIMARY KEY(id_utilisateur, id_intervention),
   FOREIGN KEY(id_utilisateur) REFERENCES UTILISATEUR(id_utilisateur),
   FOREIGN KEY(id_intervention) REFERENCES INTERVENTION(id_intervention)
);
