<?php
/**
 * Trigger du module CustomDemo
 */

require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';

class InterfaceCustomDemoTriggers extends DolibarrTriggers
{
    public function __construct($db)
    {
        $this->db = $db;
        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = 'customdemo';
        $this->description = "Triggers du module CustomDemo";
        $this->version = '1.0.0';
        $this->picto = 'technic';
    }

public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        // 1. Toujours vérifier en PREMIER si le module est activé
        if (empty($conf->customdemo->enabled)) {
            return 0;
        }

        // 2. Traitement centralisé des événements
        switch ($action) {
            case 'ORDER_VALIDATE':
                // Exercice 3 : Blocage si total TTC >= 500 €
                if ($object->total_ttc >= 500) {
                    $this->errors[] = "Validation bloquée : Le montant total (" . $object->total_ttc . " €) est supérieur ou égal à 500 €.";
                    return -1; // Annule l'action dans Dolibarr
                }

                // Exercice 2 : Ajout de la note si < 500 €
                $date = dol_print_date(dol_now(), 'dayhour');
                $ligneNote = "Validé par trigger le " . $date;

                if (!empty($object->note_private)) {
                    $object->note_private .= "\n" . $ligneNote;
                } else {
                    $object->note_private = $ligneNote;
                }

                $object->update_note($object->note_private, '_private');
                break;

            case 'PRODUCT_PRICE_MODIFY':
                $fk_price = (int) $object->id;
                $prix_regulier = (float) $object->price;

                $sql = "UPDATE " . MAIN_DB_PREFIX . "customfields_price ";
                $sql .= "SET old_price = prix_regulier, ";
                $sql .= "prix_regulier = " . $prix_regulier . " ";
                $sql .= "WHERE fk_price = " . $fk_price;

                $resql = $this->db->query($sql);

                if ($resql) {
                    setEventMessages('Mise à jour de la table customfields effectuée', null, 'mesgs');
                } else {
                    setEventMessages('Erreur SQL : ' . $this->db->lasterror(), null, 'errors');
                    return -1;
                }
                break;

            case 'PROPAL_CREATE':
                require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
                $product = new Product($this->db);
                $product->ref = !empty($object->note_private) ? $object->note_private : 'PROD-' . time();
                $product->label = $product->ref . '-libelle';
                $product->price = 900;

                $res = $product->create($user);
                if ($res > 0) {
                    setEventMessages('Le produit ' . $product->getNomUrl(0) . ' a été créé avec succès', null, 'mesgs');
                } else {
                    $this->errors[] = 'Un problème est survenu lors de la création du produit : ' . $product->error;
                    return -1;
                }
                break;

            case 'COMPANY_CREATE':
                dol_syslog("Trigger CustomDemo déclenché à la création d'un tiers");
                break;
        }

        return 0;
    }

            
}