<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260312222530 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE achat DROP FOREIGN KEY `fk_idClient`');
        $this->addSql('ALTER TABLE activite DROP FOREIGN KEY `fk_activite_guide`');
        $this->addSql('ALTER TABLE activite DROP FOREIGN KEY `fk_activite_profile`');
        $this->addSql('ALTER TABLE actualites DROP FOREIGN KEY `fk_offre5`');
        $this->addSql('ALTER TABLE actualites DROP FOREIGN KEY `fk_user6`');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY `fk_participant_conversation1`');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY `fk_participant_user1`');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY `fk_message_receiver`');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY `fk_message_sender`');
        $this->addSql('ALTER TABLE offre DROP FOREIGN KEY `fk_Agence`');
        $this->addSql('ALTER TABLE offre_service DROP FOREIGN KEY `fk_offre`');
        $this->addSql('ALTER TABLE offre_service DROP FOREIGN KEY `fk_offre1`');
        $this->addSql('ALTER TABLE offre_service DROP FOREIGN KEY `fk_offre2`');
        $this->addSql('ALTER TABLE offre_service DROP FOREIGN KEY `fk_service2`');
        $this->addSql('ALTER TABLE offre_service DROP FOREIGN KEY `fk_service4`');
        $this->addSql('ALTER TABLE participantconversation DROP FOREIGN KEY `fk_participant_conversation`');
        $this->addSql('ALTER TABLE participantconversation DROP FOREIGN KEY `fk_participant_user`');
        $this->addSql('ALTER TABLE profile DROP FOREIGN KEY `fk_profile_user`');
        $this->addSql('ALTER TABLE purchases DROP FOREIGN KEY `purchases_ibfk_1`');
        $this->addSql('ALTER TABLE purchases DROP FOREIGN KEY `purchases_ibfk_2`');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY `fk_user5`');
        $this->addSql('ALTER TABLE reservations DROP FOREIGN KEY `fk_services`');
        $this->addSql('ALTER TABLE reservations DROP FOREIGN KEY `fk_user8`');
        $this->addSql('ALTER TABLE service DROP FOREIGN KEY `fk_user3`');
        $this->addSql('ALTER TABLE services DROP FOREIGN KEY `fk_user9`');
        $this->addSql('ALTER TABLE todo DROP FOREIGN KEY `todo_ibfk_1`');
        $this->addSql('DROP TABLE achat');
        $this->addSql('DROP TABLE activite');
        $this->addSql('DROP TABLE actualites');
        $this->addSql('DROP TABLE admin');
        $this->addSql('DROP TABLE agence');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE clients1');
        $this->addSql('DROP TABLE conversation');
        $this->addSql('DROP TABLE guide');
        $this->addSql('DROP TABLE hotel');
        $this->addSql('DROP TABLE lignepanier');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE messages');
        $this->addSql('DROP TABLE offre');
        $this->addSql('DROP TABLE offre_service');
        $this->addSql('DROP TABLE participantconversation');
        $this->addSql('DROP TABLE profile');
        $this->addSql('DROP TABLE purchases');
        $this->addSql('DROP TABLE reservation');
        $this->addSql('DROP TABLE reservations');
        $this->addSql('DROP TABLE service');
        $this->addSql('DROP TABLE services');
        $this->addSql('DROP TABLE shop');
        $this->addSql('DROP TABLE todo');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE vol');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE achat (idAchat INT AUTO_INCREMENT NOT NULL, dateAchat DATETIME NOT NULL, montantTotal NUMERIC(10, 2) NOT NULL, statut VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT \'\'\'En attente\'\'\' COLLATE `utf8mb4_general_ci`, idClient INT NOT NULL, nbPlaces INT DEFAULT 1, idActivite INT NOT NULL, INDEX fk_idClient (idClient), PRIMARY KEY (idAchat)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE activite (idActivite INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, description TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, lieu VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, dateActivite DATETIME NOT NULL, dureParJour INT NOT NULL, prix NUMERIC(10, 0) NOT NULL, idGuide INT NOT NULL, id_profile INT DEFAULT NULL, image VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, statut VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT \'\'\'Actif\'\'\' NOT NULL COLLATE `utf8mb4_general_ci`, placesDisponibles INT DEFAULT 0 NOT NULL, categorie VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, dateCreation DATETIME DEFAULT \'current_timestamp()\' NOT NULL, INDEX fk_activite_guide (idGuide), INDEX idx_activite_profile (id_profile), PRIMARY KEY (idActivite)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE actualites (idActualite INT AUTO_INCREMENT NOT NULL, idOffre INT NOT NULL, idAgence INT NOT NULL, bannerUrl VARCHAR(500) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, titre VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, createdAt DATETIME DEFAULT \'current_timestamp()\' NOT NULL, isActive TINYINT DEFAULT 1 NOT NULL, endsAt DATETIME DEFAULT \'NULL\', clickCount INT DEFAULT 0 NOT NULL, INDEX fk_user6 (idAgence), INDEX fk_offre5 (idOffre), PRIMARY KEY (idActualite)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE admin (idUser INT NOT NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE agence (idUser INT NOT NULL, nomAgence VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, description TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, validationAdmin TINYINT NOT NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE client (idUser INT NOT NULL, adresse VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, dateNaissance DATE NOT NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE clients1 (idUser INT NOT NULL, nom VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, prenom VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, email VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, telephone VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE conversation (idConversation INT AUTO_INCREMENT NOT NULL, type ENUM(\'PRIVEE\', \'GROUPE\') CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, dateCreation DATETIME NOT NULL, titre VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, PRIMARY KEY (idConversation)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE guide (idUser INT NOT NULL, disponibilite TINYINT NOT NULL, nom VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, prenom VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, email VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, telephone VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE hotel (idService INT NOT NULL, nombreEtoiles INT NOT NULL, localisation VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, typeChambre ENUM(\'SIMPLE\', \'DOUBLE\', \'SUITE\', \'FAMILIALE\') CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE lignepanier (idReservation INT NOT NULL, idOffre INT NOT NULL, prixUnitaire NUMERIC(10, 2) NOT NULL, agencyStatus VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT \'\'\'ENATTENTE\'\'\' NOT NULL COLLATE `utf8mb4_general_ci`, agencyDecisionAt DATETIME DEFAULT \'NULL\', refusalReason VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE message (idMessage INT AUTO_INCREMENT NOT NULL, contenu TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, dateEnvoi DATETIME NOT NULL, lu TINYINT NOT NULL, idConversation INT NOT NULL, idExpediteur INT NOT NULL, typeMessage ENUM(\'TEXTE\', \'IMAGE\', \'FICHIER\', \'LOCATION\', \'AUDIO\') CHARACTER SET utf8mb4 DEFAULT \'\'\'TEXTE\'\'\' COLLATE `utf8mb4_general_ci`, urlFichier VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, reaction VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, isDeleted TINYINT DEFAULT 0 NOT NULL, INDEX fk_participant_conversation1 (idConversation), INDEX fk_participant_user1 (idExpediteur), PRIMARY KEY (idMessage)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE messages (id INT AUTO_INCREMENT NOT NULL, sender_id INT NOT NULL, receiver_id INT DEFAULT NULL, message TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, timestamp DATETIME NOT NULL, is_read TINYINT DEFAULT 0, conversation_id VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, INDEX sender_id (sender_id), INDEX receiver_id (receiver_id), INDEX conversation_id (conversation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE offre (idOffre INT AUTO_INCREMENT NOT NULL, titre VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, description TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, prixOriginal NUMERIC(10, 0) DEFAULT \'NULL\', prixPromo NUMERIC(10, 2) NOT NULL, dateDebut DATE NOT NULL, dateFin DATE NOT NULL, idAgence INT NOT NULL, status ENUM(\'ACTIVE\', \'ARCHIVED\') CHARACTER SET utf8mb4 DEFAULT \'\'\'ACTIVE\'\'\' NOT NULL COLLATE `utf8mb4_general_ci`, imageUrl VARCHAR(500) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, INDEX fk_Agence (idAgence), PRIMARY KEY (idOffre)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE offre_service (idOffre INT NOT NULL, idService INT NOT NULL, quantite INT NOT NULL, prixOverride INT NOT NULL, INDEX fk_service4 (idService), INDEX IDX_4D46E9F2B842C572 (idOffre), PRIMARY KEY (idOffre, idService)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE participantconversation (idParticipant INT AUTO_INCREMENT NOT NULL, idConversation INT NOT NULL, idUtilisateur INT NOT NULL, dateAjout DATETIME NOT NULL, estActif TINYINT DEFAULT 1, dateSortie DATETIME DEFAULT \'NULL\', INDEX fk_participant_user (idUtilisateur), INDEX fk_participant_conversation (idConversation), PRIMARY KEY (idParticipant)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE profile (id INT AUTO_INCREMENT NOT NULL, image LONGBLOB DEFAULT NULL, member_premium VARCHAR(25) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, language VARCHAR(25) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, id_user INT NOT NULL, coins INT DEFAULT 0 NOT NULL, INDEX idx_id_user (id_user), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE purchases (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, shop_id INT NOT NULL, quantity INT DEFAULT 1 NOT NULL, total_coins INT NOT NULL, buyer_name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, buyer_email VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, buyer_address TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, status VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT \'\'\'pending\'\'\' COLLATE `utf8mb4_general_ci`, purchase_date DATETIME DEFAULT \'current_timestamp()\' NOT NULL, INDEX user_id (user_id), INDEX shop_id (shop_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE reservation (idReservation INT AUTO_INCREMENT NOT NULL, dateReservation DATETIME NOT NULL, statut ENUM(\'ENATTENTE\', \'CONFIRME\', \'ANNULE\', \'PANIER\') CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, modePaiement VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, montantTotal NUMERIC(10, 2) NOT NULL, idClient INT NOT NULL, INDEX fk_user5 (idClient), PRIMARY KEY (idReservation)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE reservations (idReservation INT AUTO_INCREMENT NOT NULL, dateReservation DATE NOT NULL, statut ENUM(\'acceptee\', \'en attente\', \'non acceptee\') CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, modePaiement VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, idService INT NOT NULL, nom VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, seatNb INT DEFAULT NULL, iduser INT NOT NULL, INDEX fk_services (idService), INDEX fk_user8 (iduser), PRIMARY KEY (idReservation)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE service (idService INT AUTO_INCREMENT NOT NULL, nom VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, description TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, prix NUMERIC(10, 2) NOT NULL, disponibilite TINYINT NOT NULL, capacite INT NOT NULL, idAgence INT NOT NULL, INDEX fk_user3 (idAgence), PRIMARY KEY (idService)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE services (idService INT AUTO_INCREMENT NOT NULL, description TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, prix DOUBLE PRECISION NOT NULL, disponibilite ENUM(\'true\', \'false\') CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, capacite INT NOT NULL, nombreEtoiles INT DEFAULT NULL, localisation VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, typeChambre ENUM(\'SINGLE\', \'DOUBLE\', \'SUITE\', \'FAMILIALE\') CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, numeroVol VARCHAR(25) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, villeDepart VARCHAR(70) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, villeArrivee VARCHAR(70) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, dateDepart DATE DEFAULT \'NULL\', dateArrive DATE DEFAULT \'NULL\', imgUrl VARCHAR(500) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, type ENUM(\'hotel\', \'vol\') CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, iduser INT NOT NULL, nom VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, INDEX iduser (iduser), PRIMARY KEY (idService)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE shop (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, description TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, price_coins INT NOT NULL, quantity INT DEFAULT 0 NOT NULL, image LONGBLOB DEFAULT NULL, category VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, created_at DATETIME DEFAULT \'current_timestamp()\' NOT NULL, updated_at DATETIME DEFAULT \'current_timestamp()\' NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE todo (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, description TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, status VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT \'\'\'To Do\'\'\' COLLATE `utf8mb4_general_ci`, user_id INT NOT NULL, created_at DATETIME DEFAULT \'current_timestamp()\', updated_at DATETIME DEFAULT \'current_timestamp()\', priority INT DEFAULT 2, category VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, INDEX user_id (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(25) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, last_name VARCHAR(25) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, email VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, password VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, date DATE NOT NULL, role VARCHAR(25) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, username VARCHAR(25) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, status VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, two_factor_enabled TINYINT DEFAULT 0, two_factor_code VARCHAR(10) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, two_factor_expiry DATETIME DEFAULT \'NULL\', face_data LONGBLOB DEFAULT NULL, fingerprint_data LONGBLOB DEFAULT NULL, fingerprint_slot_id INT DEFAULT NULL, telephone INT DEFAULT NULL, UNIQUE INDEX email_unique (email), UNIQUE INDEX username_unique (username), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE vol (idService INT NOT NULL, numeroVol VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, villeDepart VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, villeArrivee VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, dateDepart DATETIME NOT NULL, dateArrivee DATETIME NOT NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE achat ADD CONSTRAINT `fk_idClient` FOREIGN KEY (idClient) REFERENCES user (id)');
        $this->addSql('ALTER TABLE activite ADD CONSTRAINT `fk_activite_guide` FOREIGN KEY (idGuide) REFERENCES user (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE activite ADD CONSTRAINT `fk_activite_profile` FOREIGN KEY (id_profile) REFERENCES profile (id) ON UPDATE CASCADE ON DELETE SET NULL');
        $this->addSql('ALTER TABLE actualites ADD CONSTRAINT `fk_offre5` FOREIGN KEY (idOffre) REFERENCES offre (idOffre) ON UPDATE CASCADE');
        $this->addSql('ALTER TABLE actualites ADD CONSTRAINT `fk_user6` FOREIGN KEY (idAgence) REFERENCES user (id) ON UPDATE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT `fk_participant_conversation1` FOREIGN KEY (idConversation) REFERENCES conversation (idConversation) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT `fk_participant_user1` FOREIGN KEY (idExpediteur) REFERENCES user (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT `fk_message_receiver` FOREIGN KEY (receiver_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT `fk_message_sender` FOREIGN KEY (sender_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE offre ADD CONSTRAINT `fk_Agence` FOREIGN KEY (idAgence) REFERENCES user (id) ON UPDATE CASCADE');
        $this->addSql('ALTER TABLE offre_service ADD CONSTRAINT `fk_offre` FOREIGN KEY (idOffre) REFERENCES user (id) ON UPDATE CASCADE');
        $this->addSql('ALTER TABLE offre_service ADD CONSTRAINT `fk_offre1` FOREIGN KEY (idOffre) REFERENCES offre (idOffre) ON UPDATE CASCADE');
        $this->addSql('ALTER TABLE offre_service ADD CONSTRAINT `fk_offre2` FOREIGN KEY (idOffre) REFERENCES offre (idOffre) ON UPDATE CASCADE');
        $this->addSql('ALTER TABLE offre_service ADD CONSTRAINT `fk_service2` FOREIGN KEY (idService) REFERENCES service (idService) ON UPDATE CASCADE');
        $this->addSql('ALTER TABLE offre_service ADD CONSTRAINT `fk_service4` FOREIGN KEY (idService) REFERENCES service (idService) ON UPDATE CASCADE');
        $this->addSql('ALTER TABLE participantconversation ADD CONSTRAINT `fk_participant_conversation` FOREIGN KEY (idConversation) REFERENCES conversation (idConversation) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE participantconversation ADD CONSTRAINT `fk_participant_user` FOREIGN KEY (idUtilisateur) REFERENCES user (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE profile ADD CONSTRAINT `fk_profile_user` FOREIGN KEY (id_user) REFERENCES user (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE purchases ADD CONSTRAINT `purchases_ibfk_1` FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE purchases ADD CONSTRAINT `purchases_ibfk_2` FOREIGN KEY (shop_id) REFERENCES shop (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT `fk_user5` FOREIGN KEY (idClient) REFERENCES user (id) ON UPDATE CASCADE');
        $this->addSql('ALTER TABLE reservations ADD CONSTRAINT `fk_services` FOREIGN KEY (idService) REFERENCES services (idService) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservations ADD CONSTRAINT `fk_user8` FOREIGN KEY (iduser) REFERENCES user (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service ADD CONSTRAINT `fk_user3` FOREIGN KEY (idAgence) REFERENCES user (id) ON UPDATE CASCADE');
        $this->addSql('ALTER TABLE services ADD CONSTRAINT `fk_user9` FOREIGN KEY (iduser) REFERENCES user (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE todo ADD CONSTRAINT `todo_ibfk_1` FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
