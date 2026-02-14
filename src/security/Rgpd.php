<?php
declare(strict_types=1);

namespace ZenCamp\Security;

/**
 * Conformité RGPD — outils applicatifs.
 *
 * Couvre : recueil et preuve du consentement (art. 7), droit d'accès et
 * portabilité (art. 15 et 20), droit à l'effacement (art. 17), sécurité et
 * traçabilité (art. 32), minimisation et limitation de conservation
 * (art. 5), chiffrement des données de santé (art. 9).
 */
final class Rgpd
{
    /** Durées de conservation retenues (référentiel CNIL). */
    public const CONSERVATION = [
        'compte_inactif'      => '3 ans après la dernière activité',
        'donnees_facturation' => '10 ans (art. L123-22 code de commerce)',
        'donnees_sante'       => '90 jours après la fin du séjour',
        'journaux_connexion'  => '12 mois',
        'prospection'         => '3 ans après le dernier contact',
        'cookies_mesure'      => '13 mois',
    ];

    // -----------------------------------------------------------------
    // Consentement (art. 7)
    // -----------------------------------------------------------------

    /**
     * Enregistre un consentement. Table append-only : on n'écrase jamais,
     * on ajoute une ligne. C'est ce qui constitue la PREUVE exigée par
     * l'art. 7.1 — le responsable de traitement doit pouvoir démontrer que
     * la personne a consenti, et quand.
     */
    public static function enregistrerConsentement(
        int $idUtilisateur,
        string $type,
        bool $accorde,
        string $versionTexte = 'v1.2',
        string $preuve = ''
    ): void {
        Database::query(
            'INSERT INTO consentement
                 (id_utilisateur, type_consentement, accorde, version_texte,
                  adresse_ip, preuve)
             VALUES (:uid, :type, :accorde, :version, INET6_ATON(:ip), :preuve)',
            [
                'uid'     => $idUtilisateur,
                'type'    => $type,
                'accorde' => $accorde ? 1 : 0,
                'version' => $versionTexte,
                'ip'      => BruteForce::ipClient(),
                'preuve'  => $preuve,
            ]
        );
    }

    /**
     * État courant d'un consentement : la ligne la plus récente fait foi.
     * Le retrait doit être aussi simple que le recueil (art. 7.3).
     */
    public static function aConsenti(int $idUtilisateur, string $type): bool
    {
        $row = Database::fetchOne(
            'SELECT accorde
             FROM   consentement
             WHERE  id_utilisateur = :uid AND type_consentement = :type
             ORDER BY date_action DESC
             LIMIT 1',
            ['uid' => $idUtilisateur, 'type' => $type]
        );

        return $row !== null && (bool) $row['accorde'];
    }

    // -----------------------------------------------------------------
    // Droit d'accès et portabilité (art. 15 et 20)
    // -----------------------------------------------------------------

    /**
     * Export de toutes les données d'un utilisateur, dans un format
     * structuré et lisible par machine, comme l'exige l'art. 20.
     *
     * @return array<string,mixed>
     */
    public static function exporterDonnees(int $idUtilisateur): array
    {
        self::journaliser($idUtilisateur, $idUtilisateur, 'export_rgpd', 'utilisateur');

        $export = [
            'genere_le' => date('c'),
            'identite'  => Database::fetchOne(
                'SELECT id_utilisateur, nom, prenom, email, telephone,
                        date_naissance, date_inscription, derniere_connexion
                 FROM   utilisateur WHERE id_utilisateur = :uid',
                ['uid' => $idUtilisateur]
            ),
            'adresses' => Database::fetchAll(
                'SELECT libelle, ligne1, ligne2, code_postal, ville, pays, type_adresse
                 FROM   adresse WHERE id_utilisateur = :uid',
                ['uid' => $idUtilisateur]
            ),
            'reservations' => Database::fetchAll(
                'SELECT r.reference, r.date_reservation, r.statut, r.montant_total,
                        se.titre AS sejour, s.date_debut, s.date_fin,
                        lr.nb_participants, lr.sous_total
                 FROM   reservation r
                 JOIN   ligne_reservation lr ON lr.id_reservation = r.id_reservation
                 JOIN   session_sejour s     ON s.id_session      = lr.id_session
                 JOIN   sejour se            ON se.id_sejour      = s.id_sejour
                 WHERE  r.id_utilisateur = :uid
                 ORDER BY r.date_reservation DESC',
                ['uid' => $idUtilisateur]
            ),
            'participants' => Database::fetchAll(
                'SELECT p.nom, p.prenom, p.date_naissance, p.regime_alimentaire
                 FROM   participant p
                 JOIN   ligne_reservation lr ON lr.id_ligne      = p.id_ligne
                 JOIN   reservation r        ON r.id_reservation = lr.id_reservation
                 WHERE  r.id_utilisateur = :uid',
                ['uid' => $idUtilisateur]
            ),
            'paiements' => Database::fetchAll(
                'SELECT p.date_paiement, p.montant, p.moyen_paiement, p.statut
                 FROM   paiement p
                 JOIN   reservation r ON r.id_reservation = p.id_reservation
                 WHERE  r.id_utilisateur = :uid',
                ['uid' => $idUtilisateur]
            ),
            'avis' => Database::fetchAll(
                'SELECT a.note, a.titre, a.commentaire, a.date_avis, se.titre AS sejour
                 FROM   avis a
                 JOIN   sejour se ON se.id_sejour = a.id_sejour
                 WHERE  a.id_utilisateur = :uid',
                ['uid' => $idUtilisateur]
            ),
            'consentements' => Database::fetchAll(
                'SELECT type_consentement, accorde, version_texte, date_action, preuve
                 FROM   consentement WHERE id_utilisateur = :uid
                 ORDER BY date_action',
                ['uid' => $idUtilisateur]
            ),
            'connexions' => Database::fetchAll(
                'SELECT date_creation, date_expiration, revoquee,
                        INET6_NTOA(adresse_ip) AS adresse_ip
                 FROM   session_utilisateur WHERE id_utilisateur = :uid
                 ORDER BY date_creation DESC LIMIT 50',
                ['uid' => $idUtilisateur]
            ),
        ];

        // Le hash du mot de passe et le secret TOTP ne sont JAMAIS exportés :
        // ce ne sont pas des données personnelles utiles à la personne, et
        // les exporter créerait un risque en cas d'interception.
        return $export;
    }

    /** Sert l'export en JSON téléchargeable (format portable, art. 20). */
    public static function telechargerExport(int $idUtilisateur): void
    {
        $donnees = self::exporterDonnees($idUtilisateur);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="mes-donnees-zencamp.json"');
        header('X-Content-Type-Options: nosniff');

        echo json_encode(
            $donnees,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }

    // -----------------------------------------------------------------
    // Droit à l'effacement (art. 17)
    // -----------------------------------------------------------------

    /**
     * Anonymisation plutôt que suppression.
     *
     * L'art. 17.3.b prévoit une exception au droit à l'effacement lorsqu'une
     * obligation légale impose la conservation : les pièces comptables
     * doivent être gardées 10 ans. On supprime donc l'identité, en
     * conservant les montants et les dates, désormais non rattachables à
     * une personne — ils sortent du champ du RGPD.
     */
    public static function anonymiser(int $idUtilisateur): void
    {
        Database::query(
            'CALL sp_anonymiser_utilisateur(:uid)',
            ['uid' => $idUtilisateur]
        );

        Session::revoquerToutesLesSessions($idUtilisateur);
    }

    /**
     * Enregistre une demande d'exercice de droit et calcule l'échéance
     * légale : un mois à compter de la réception (art. 12.3).
     */
    public static function enregistrerDemande(
        ?int $idUtilisateur,
        string $email,
        string $typeDemande
    ): int {
        Database::query(
            'INSERT INTO demande_rgpd
                 (id_utilisateur, email_demandeur, type_demande, date_echeance)
             VALUES (:uid, :email, :type, CURDATE() + INTERVAL 1 MONTH)',
            ['uid' => $idUtilisateur, 'email' => $email, 'type' => $typeDemande]
        );

        return (int) Database::get()->lastInsertId();
    }

    // -----------------------------------------------------------------
    // Traçabilité (art. 32)
    // -----------------------------------------------------------------

    /**
     * Journalise un accès à des données personnelles.
     *
     * @param array<string,mixed> $details contexte SANS donnée sensible
     */
    public static function journaliser(
        ?int $auteur,
        ?int $cible,
        string $action,
        ?string $table = null,
        ?int $idEnregistrement = null,
        array $details = []
    ): void {
        Database::query(
            'INSERT INTO journal_acces
                 (id_utilisateur, id_cible, action, table_concernee,
                  id_enregistrement, adresse_ip, details)
             VALUES (:auteur, :cible, :action, :table, :id_enr,
                     INET6_ATON(:ip), :details)',
            [
                'auteur'  => $auteur,
                'cible'   => $cible,
                'action'  => $action,
                'table'   => $table,
                'id_enr'  => $idEnregistrement,
                'ip'      => BruteForce::ipClient(),
                'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    // -----------------------------------------------------------------
    // Données de santé (art. 9)
    // -----------------------------------------------------------------

    /**
     * Chiffre une donnée de santé (allergie, contre-indication) en
     * AES-256-GCM. La clé vit dans une variable d'environnement, hors base :
     * une exfiltration de la base seule ne donne accès à rien.
     *
     * GCM est un mode AUTHENTIFIÉ : toute altération du chiffré est détectée
     * au déchiffrement, ce qui empêche la falsification.
     */
    public static function chiffrerDonneeSante(int $idParticipant, string $donnee): void
    {
        $cle = self::cleChiffrement();
        $nonce = random_bytes(12); // unique par enregistrement — jamais réutilisé
        $tag = '';

        $chiffre = openssl_encrypt(
            $donnee, 'aes-256-gcm', $cle, OPENSSL_RAW_DATA, $nonce, $tag
        );

        if ($chiffre === false) {
            throw new \RuntimeException('Échec du chiffrement de la donnée de santé.');
        }

        Database::query(
            'INSERT INTO participant_donnee_sensible
                 (id_participant, donnee_chiffree, nonce, tag)
             VALUES (:pid, :data, :nonce, :tag)
             ON DUPLICATE KEY UPDATE
                 donnee_chiffree = VALUES(donnee_chiffree),
                 nonce = VALUES(nonce), tag = VALUES(tag)',
            [
                'pid'   => $idParticipant,
                'data'  => $chiffre,
                'nonce' => $nonce,
                'tag'   => $tag,
            ]
        );
    }

    /** Déchiffre, et journalise l'accès : c'est une donnée sensible. */
    public static function lireDonneeSante(int $idParticipant, ?int $auteur): ?string
    {
        $row = Database::fetchOne(
            'SELECT donnee_chiffree, nonce, tag
             FROM   participant_donnee_sensible
             WHERE  id_participant = :pid',
            ['pid' => $idParticipant]
        );

        if ($row === null) {
            return null;
        }

        self::journaliser(
            $auteur, null, 'lecture_donnee_sante',
            'participant_donnee_sensible', $idParticipant
        );

        $clair = openssl_decrypt(
            $row['donnee_chiffree'], 'aes-256-gcm', self::cleChiffrement(),
            OPENSSL_RAW_DATA, $row['nonce'], $row['tag']
        );

        // false = tag invalide : la donnée a été altérée en base.
        if ($clair === false) {
            error_log("[RGPD] Tag GCM invalide pour participant {$idParticipant}");
            return null;
        }

        return $clair;
    }

    private static function cleChiffrement(): string
    {
        $cle = getenv('ZENCAMP_CLE_CHIFFREMENT');

        if ($cle === false || $cle === '') {
            throw new \RuntimeException(
                'ZENCAMP_CLE_CHIFFREMENT absente de l environnement.'
            );
        }

        // La clé est stockée en base64 : 32 octets une fois décodée.
        return base64_decode($cle, true) ?: '';
    }
}
