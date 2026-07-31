<?php

declare(strict_types=1);

namespace App\Pim\Lov;

final class LieuLovCatalog
{
    /** @var array<string, array<string, string>> */
    private const CHOICES = [
        'GENERALE_TYPOLOGIE' => [
            'GENERALE_TYPOLOGIE_1' => 'Hôtel 2 étoiles',
            'GENERALE_TYPOLOGIE_2' => 'Hôtel 3 étoiles',
            'GENERALE_TYPOLOGIE_3' => 'Hôtel 4 étoiles',
            'GENERALE_TYPOLOGIE_4' => 'Hôtel 5 étoiles',
            'GENERALE_TYPOLOGIE_5' => 'Palace',
            'GENERALE_TYPOLOGIE_6' => 'Châteaux / Domaines',
            'GENERALE_TYPOLOGIE_7' => 'Comme à la maison',
            'GENERALE_TYPOLOGIE_8' => 'Domaine viticoles / vignobles',
            'GENERALE_TYPOLOGIE_9' => 'Ecolodge',
            'GENERALE_TYPOLOGIE_10' => 'Golfs',
            'GENERALE_TYPOLOGIE_11' => 'Lieu ESAT',
            'GENERALE_TYPOLOGIE_12' => 'Lieu insolite / Atypique',
            'GENERALE_TYPOLOGIE_13' => 'Appartement / Loft',
            'GENERALE_TYPOLOGIE_14' => 'Auditorium',
            'GENERALE_TYPOLOGIE_15' => 'Bar / Pub',
            'GENERALE_TYPOLOGIE_16' => 'Base de Loisirs',
            'GENERALE_TYPOLOGIE_17' => 'Bowlings',
            'GENERALE_TYPOLOGIE_18' => 'Casino',
            'GENERALE_TYPOLOGIE_19' => 'Centre d’affaire / Conférence',
            'GENERALE_TYPOLOGIE_20' => 'Centre de congrès / Convention / Parc des expositions',
            'GENERALE_TYPOLOGIE_21' => 'Circuits Automobiles / Karting',
            'GENERALE_TYPOLOGIE_22' => 'Club / Discothèque',
            'GENERALE_TYPOLOGIE_23' => 'Coworking',
            'GENERALE_TYPOLOGIE_24' => 'Hippodrome',
            'GENERALE_TYPOLOGIE_25' => 'Maison d’hôte / Gîte / Ferme',
            'GENERALE_TYPOLOGIE_26' => 'Musée / Lieu Culturel',
            'GENERALE_TYPOLOGIE_27' => 'Parc à thème / Attraction',
            'GENERALE_TYPOLOGIE_28' => 'Péniche / Yacht / Bateau',
            'GENERALE_TYPOLOGIE_29' => 'Rooftop',
            'GENERALE_TYPOLOGIE_30' => 'Stade / Complexe sportif',
            'GENERALE_TYPOLOGIE_31' => 'Théâtre / Salle de spectacle / Concert',
            'GENERALE_TYPOLOGIE_32' => 'Villa / Maison privée',
            'GENERALE_TYPOLOGIE_33' => 'Village vacances / Camping',
            'GENERALE_TYPOLOGIE_34' => 'Centre de formation',
            'GENERALE_TYPOLOGIE_35' => 'Lieu académique',
            'GENERALE_TYPOLOGIE_36' => 'Cinéma',
            'GENERALE_TYPOLOGIE_37' => 'Cabaret',
            'GENERALE_TYPOLOGIE_38' => 'Salle de réception',
            'GENERALE_TYPOLOGIE_39' => 'Salle sèche',
            'GENERALE_TYPOLOGIE_40' => 'Autres Types de Lieux',
        ],
        'GENERALE_CHAINES_GROUPE_HOT' => [
            'GENERALE_CHAINES_GROUPE_HOT_1' => 'Adagio',
            'GENERALE_CHAINES_GROUPE_HOT_2' => 'All suites Appart Hôtel',
            'GENERALE_CHAINES_GROUPE_HOT_3' => 'Appart\'City',
            'GENERALE_CHAINES_GROUPE_HOT_4' => 'Azureva',
            'GENERALE_CHAINES_GROUPE_HOT_5' => 'B&B',
            'GENERALE_CHAINES_GROUPE_HOT_6' => 'Baya',
            'GENERALE_CHAINES_GROUPE_HOT_7' => 'Best Western',
            'GENERALE_CHAINES_GROUPE_HOT_8' => 'Breizh café',
            'GENERALE_CHAINES_GROUPE_HOT_9' => 'Brit Hotel',
            'GENERALE_CHAINES_GROUPE_HOT_10' => 'Buro Club',
            'GENERALE_CHAINES_GROUPE_HOT_11' => 'Campanile',
            'GENERALE_CHAINES_GROUPE_HOT_12' => 'Cap France',
            'GENERALE_CHAINES_GROUPE_HOT_13' => 'Casino Joa',
            'GENERALE_CHAINES_GROUPE_HOT_14' => 'Châteauform',
            'GENERALE_CHAINES_GROUPE_HOT_15' => 'CinémaCGR',
            'GENERALE_CHAINES_GROUPE_HOT_16' => 'Club Belambra',
            'GENERALE_CHAINES_GROUPE_HOT_17' => 'Club Med Business',
            'GENERALE_CHAINES_GROUPE_HOT_18' => 'COMET',
            'GENERALE_CHAINES_GROUPE_HOT_19' => 'Comfort Hôtel',
            'GENERALE_CHAINES_GROUPE_HOT_20' => 'Ethic Etapes',
            'GENERALE_CHAINES_GROUPE_HOT_21' => 'GL events',
            'GENERALE_CHAINES_GROUPE_HOT_22' => 'Golden Tulip',
            'GENERALE_CHAINES_GROUPE_HOT_23' => 'Greet Hotel',
            'GENERALE_CHAINES_GROUPE_HOT_24' => 'Groupe barrière',
            'GENERALE_CHAINES_GROUPE_HOT_25' => 'Groupe Bertrand',
            'GENERALE_CHAINES_GROUPE_HOT_26' => 'Groupe Partouche',
            'GENERALE_CHAINES_GROUPE_HOT_27' => 'Hilton',
            'GENERALE_CHAINES_GROUPE_HOT_28' => 'Holiday Inn',
            'GENERALE_CHAINES_GROUPE_HOT_29' => 'Hotel Inn Design',
            'GENERALE_CHAINES_GROUPE_HOT_30' => 'Ibis',
            'GENERALE_CHAINES_GROUPE_HOT_31' => 'Ibis Budget',
            'GENERALE_CHAINES_GROUPE_HOT_32' => 'Ibis Styles',
            'GENERALE_CHAINES_GROUPE_HOT_33' => 'Keeze',
            'GENERALE_CHAINES_GROUPE_HOT_34' => 'Kinepolis',
            'GENERALE_CHAINES_GROUPE_HOT_35' => 'Kyriad',
            'GENERALE_CHAINES_GROUPE_HOT_36' => 'Lavorel Hotels',
            'GENERALE_CHAINES_GROUPE_HOT_37' => 'Logis Hotels',
            'GENERALE_CHAINES_GROUPE_HOT_38' => 'Mama Shelter',
            'GENERALE_CHAINES_GROUPE_HOT_39' => 'Marriott',
            'GENERALE_CHAINES_GROUPE_HOT_40' => 'Mercure',
            'GENERALE_CHAINES_GROUPE_HOT_41' => 'Mgallery',
            'GENERALE_CHAINES_GROUPE_HOT_42' => 'Miléade',
            'GENERALE_CHAINES_GROUPE_HOT_43' => 'Mitwit',
            'GENERALE_CHAINES_GROUPE_HOT_44' => 'Novotel',
            'GENERALE_CHAINES_GROUPE_HOT_45' => 'Oceania',
            'GENERALE_CHAINES_GROUPE_HOT_46' => 'Odalys',
            'GENERALE_CHAINES_GROUPE_HOT_47' => 'Pathé',
            'GENERALE_CHAINES_GROUPE_HOT_48' => 'Pierre et vacances',
            'GENERALE_CHAINES_GROUPE_HOT_49' => 'Popinns',
            'GENERALE_CHAINES_GROUPE_HOT_50' => 'Premium Hospitality',
            'GENERALE_CHAINES_GROUPE_HOT_51' => 'Pullman',
            'GENERALE_CHAINES_GROUPE_HOT_52' => 'Radisson Blu',
            'GENERALE_CHAINES_GROUPE_HOT_53' => 'Regus',
            'GENERALE_CHAINES_GROUPE_HOT_54' => 'So Villas',
            'GENERALE_CHAINES_GROUPE_HOT_55' => 'Sodexo',
            'GENERALE_CHAINES_GROUPE_HOT_56' => 'Sofitel',
            'GENERALE_CHAINES_GROUPE_HOT_57' => 'Sowell Hotels',
            'GENERALE_CHAINES_GROUPE_HOT_58' => 'Teritoria',
            'GENERALE_CHAINES_GROUPE_HOT_59' => 'The Originals',
            'GENERALE_CHAINES_GROUPE_HOT_60' => 'VVF Villages',
            'GENERALE_CHAINES_GROUPE_HOT_61' => 'Wojo',
        ],
        'DISPO_JOUR_OUVERTURE' => [
            'DISPO_JOUR_OUVERTURE_1' => 'Lundi',
            'DISPO_JOUR_OUVERTURE_2' => 'Mardi',
            'DISPO_JOUR_OUVERTURE_3' => 'Mercredi',
            'DISPO_JOUR_OUVERTURE_4' => 'Jeudi',
            'DISPO_JOUR_OUVERTURE_5' => 'Vendredi',
            'DISPO_JOUR_OUVERTURE_6' => 'Samedi',
            'DISPO_JOUR_OUVERTURE_7' => 'Dimanche',
        ],
        'GENERALE_EVENEMENTS_PREDILECTION' => [
            'GENERALE_EVENEMENTS_PREDILECTION_1' => 'Séminaire',
            'GENERALE_EVENEMENTS_PREDILECTION_2' => 'Convention / Congrès',
            'GENERALE_EVENEMENTS_PREDILECTION_3' => 'Réunion / Comité de direction',
            'GENERALE_EVENEMENTS_PREDILECTION_4' => 'Formation',
            'GENERALE_EVENEMENTS_PREDILECTION_5' => 'Team building',
            'GENERALE_EVENEMENTS_PREDILECTION_6' => 'Lancement de produit',
            'GENERALE_EVENEMENTS_PREDILECTION_7' => 'Soirée / Réception',
            'GENERALE_EVENEMENTS_PREDILECTION_8' => 'Événement hybride',
        ],
        'COND_PAIE_ACC_SIGNATURE' => [
            'COND_PAIE_ACC_SIGNATURE_1' => 'Signature à J-61',
            'COND_PAIE_ACC_SIGNATURE_2' => 'J-60 à J-30',
            'COND_PAIE_ACC_SIGNATURE_3' => 'J-29 à J-8',
            'COND_PAIE_ACC_SIGNATURE_4' => 'Après J-7',
        ],
        'COND_PAIE_ANN_SIGNATURE' => [
            'COND_PAIE_ANN_SIGNATURE_1' => 'Signature à J-181',
            'COND_PAIE_ANN_SIGNATURE_2' => 'J-180 à J-91',
            'COND_PAIE_ANN_SIGNATURE_3' => 'J-90 à J-61',
            'COND_PAIE_ANN_SIGNATURE_4' => 'J-60 à J-45',
            'COND_PAIE_ANN_SIGNATURE_5' => 'J-44 à J-30',
            'COND_PAIE_ANN_SIGNATURE_6' => 'J-29 à J-15',
            'COND_PAIE_ANN_SIGNATURE_7' => 'J-14 à J-8',
            'COND_PAIE_ANN_SIGNATURE_8' => 'J-7 à J-4',
            'COND_PAIE_ANN_SIGNATURE_9' => 'J-3 à Jour J',
        ],
        'DATE_PAIEMENT_SOLD' => [
            'DATE_PAIEMENT_SOLD_1' => '7 Jours',
            'DATE_PAIEMENT_SOLD_2' => '15 Jours',
            'DATE_PAIEMENT_SOLD_3' => '30 Jours',
            'DATE_PAIEMENT_SOLD_4' => '45 Jours',
            'DATE_PAIEMENT_SOLD_5' => '60 Jours',
        ],
        'TA_THEMATIQUE' => [
            'TA_THEMATIQUE_1' => 'Bien-être',
            'TA_THEMATIQUE_2' => 'Golf',
            'TA_THEMATIQUE_3' => 'Eco Responsable',
            'TA_THEMATIQUE_4' => 'Gastronomique',
            'TA_THEMATIQUE_5' => 'Oenotourisme',
            'TA_THEMATIQUE_6' => 'Ski',
            'TA_THEMATIQUE_7' => 'Château',
            'TA_THEMATIQUE_8' => 'Comme à la maison',
        ],
        'TA_CADRE_ENV' => [
            'TA_CADRE_ENV_1' => 'Mer',
            'TA_CADRE_ENV_2' => 'Au Vert',
            'TA_CADRE_ENV_3' => 'Campagne',
            'TA_CADRE_ENV_4' => 'Montagne',
            'TA_CADRE_ENV_5' => 'Lac',
            'TA_CADRE_ENV_6' => 'Centre Ville',
        ],
        'TA_AMBIANCE' => [
            'TA_AMBIANCE_1' => 'Atypique / Insolite',
            'TA_AMBIANCE_2' => 'Intimiste',
            'TA_AMBIANCE_3' => 'Cosy',
            'TA_AMBIANCE_4' => 'Moderne / Design',
            'TA_AMBIANCE_5' => 'Innovant / Startup',
            'TA_AMBIANCE_6' => 'Industrielle',
            'TA_AMBIANCE_7' => 'Authentique / Historique',
            'TA_AMBIANCE_8' => 'Animé',
            'TA_AMBIANCE_9' => 'Vue imprenable',
        ],
        'EQUIPEMENTS' => [
            'EQUIPEMENTS_1' => 'Machine à café',
            'EQUIPEMENTS_2' => 'Vue',
            'EQUIPEMENTS_3' => 'Télévision',
            'EQUIPEMENTS_4' => 'Balcon',
            'EQUIPEMENTS_5' => 'Climatisation',
            'EQUIPEMENTS_6' => 'Bureau',
            'EQUIPEMENTS_7' => 'Baignoire',
        ],
        'SERVICES' => [
            'SERVICES_1' => 'Voiturier',
            'SERVICES_2' => 'Navette Gratuite',
            'SERVICES_3' => 'Vestiaire / Bagagerie',
            'SERVICES_4' => 'Service pressing',
            'SERVICES_5' => 'Réception 24/24',
            'SERVICES_6' => 'Borne de recharge pour véhicules électriques',
            'SERVICES_7' => 'Room service',
        ],
        'TECHNIQUE_REUNION' => [
            'TECHNIQUE_REUNION_1' => 'Wi-fi Gratuit',
            'TECHNIQUE_REUNION_2' => 'Connexion internet filaire',
            'TECHNIQUE_REUNION_3' => 'Paper Board',
            'TECHNIQUE_REUNION_4' => 'Vidéo-projecteur / TV',
            'TECHNIQUE_REUNION_5' => 'Technicien AV sur place',
            'TECHNIQUE_REUNION_6' => 'Ecran LCD',
            'TECHNIQUE_REUNION_7' => 'Micro',
            'TECHNIQUE_REUNION_8' => 'Sonorisation',
            'TECHNIQUE_REUNION_9' => 'Blocs-notes & stylo',
            'TECHNIQUE_REUNION_10' => 'Climatisation Salles',
            'TECHNIQUE_REUNION_11' => 'Cabine de traduction',
            'TECHNIQUE_REUNION_12' => 'Visio conférence',
            'TECHNIQUE_REUNION_13' => 'Click share',
            'TECHNIQUE_REUNION_14' => 'Fontaines à eau',
            'TECHNIQUE_REUNION_15' => 'Imprimante / Photocopieuse',
        ],
        'INSTALLATION' => [
            'INSTALLATION_1' => 'Amphithéatre',
            'INSTALLATION_2' => 'Fumoir',
            'INSTALLATION_3' => 'Jardin / Parc',
            'INSTALLATION_4' => 'Rooftop',
            'INSTALLATION_5' => 'Terrasse / Cour intérieure',
            'INSTALLATION_6' => 'Balcon',
            'INSTALLATION_7' => 'Cave de dégustation',
            'INSTALLATION_8' => 'Plage privée',
            'INSTALLATION_9' => 'Espace de coworking',
            'INSTALLATION_10' => 'Parking',
            'INSTALLATION_11' => 'Business Center',
            'INSTALLATION_12' => 'Ascenseur',
        ],
        'BIEN_ETRE' => [
            'BIEN_ETRE_1' => 'Thalasso',
            'BIEN_ETRE_2' => 'Piscine intérieure',
            'BIEN_ETRE_3' => 'Piscine extérieure',
            'BIEN_ETRE_4' => 'Spa et centre de bien-être',
            'BIEN_ETRE_5' => 'Sauna',
            'BIEN_ETRE_6' => 'Hammam',
            'BIEN_ETRE_7' => 'Jaccuzi / Bain à remous',
            'BIEN_ETRE_8' => 'Centre de remise en forme',
            'BIEN_ETRE_9' => 'Fitness / Salle de sport',
            'BIEN_ETRE_10' => 'Massage',
            'BIEN_ETRE_11' => 'Espace détente',
        ],
        'ACHAT_RESPONSABLE' => [
            'ACHAT_RESPONSABLE_1' => 'Cuisine avec des produits de saison',
            'ACHAT_RESPONSABLE_2' => 'Cuisine avec des produits français ( ou privilégiant les circuits courts)',
            'ACHAT_RESPONSABLE_3' => 'Cuisine avec des produits labelisés BIO',
            'ACHAT_RESPONSABLE_4' => 'Achat de produits fabriqués en France (hors alimentation)',
            'ACHAT_RESPONSABLE_5' => 'Privilégie la réparation au le changement de votre matériel',
            'ACHAT_RESPONSABLE_6' => 'Fruits et légumes issus du potager in situ',
            'ACHAT_RESPONSABLE_7' => 'Non utilisation de capsules café ou de thé',
            'ACHAT_RESPONSABLE_8' => 'Travail avec des traiteurs engagés et respectueux de l\'environnement',
            'ACHAT_RESPONSABLE_9' => 'Utilisation de goodies éco concu, recyclé et recyclables',
        ],
        'IMPACT_ENV' => [
            'IMPACT_ENV_1' => 'Limiter la consomation d\'eau',
            'IMPACT_ENV_2' => 'Robinets à détection automatique',
            'IMPACT_ENV_3' => 'Mousseurs / Réducteurs de débit sur les robinets des sanitaires',
            'IMPACT_ENV_4' => 'Chasses d\'eau double débit avec réservoir limité.',
            'IMPACT_ENV_5' => 'Système de récupération d\'eau de pluie',
            'IMPACT_ENV_6' => 'Limiter la consomation d\'énergie',
            'IMPACT_ENV_7' => 'Adapter la température des chambres (recommandation à 19 degrés)',
            'IMPACT_ENV_8' => 'Limiter l’amplitude des boîtiers de commande de climatisation à +/-2°C.',
            'IMPACT_ENV_9' => 'Relier la gestion des appareils électriques et électroniques à la carte magnétique',
            'IMPACT_ENV_10' => 'Privilégier les ampoules basse consommation',
            'IMPACT_ENV_11' => 'Equiper les parties communes de détecteurs de présence',
            'IMPACT_ENV_12' => 'Détecteur d\'ouverture de fenetre couplé au chauffage',
            'IMPACT_ENV_13' => 'Tri séléctif  et recyclage des déchets',
            'IMPACT_ENV_14' => 'Déchets Verts',
            'IMPACT_ENV_15' => 'Verre',
            'IMPACT_ENV_16' => 'Papiers-Cartons, PMC (emballages plastiques, métalliques et cartons à boisson)',
            'IMPACT_ENV_17' => 'Electriques / Electronique',
            'IMPACT_ENV_18' => 'Huiles / Petits chimiques',
            'IMPACT_ENV_19' => 'Bouchons en liège',
            'IMPACT_ENV_20' => 'Mégots',
            'IMPACT_ENV_21' => 'Collaboration avec des producteurs locaux',
            'IMPACT_ENV_22' => 'Sensibilisation des clients à l\'impact environemental (charte en chambre, … )',
            'IMPACT_ENV_23' => 'Démarche zéro déchet',
            'IMPACT_ENV_24' => 'Sensibilisation des collaborateurs',
        ],
        'IMPACT_SOCIAL' => [
            'IMPACT_SOCIAL_1' => 'Travail avec des établissement ESAT / STPA',
            'IMPACT_SOCIAL_2' => 'Politique de diversité et d\'inclusion envers les employés',
            'IMPACT_SOCIAL_3' => 'Emploi de travailleurs en situation de handicap (si oui précisez le %)',
        ],
        'VOLUME_ACHAT_CAT_ESAT_STPA' => [
            'VOLUME_ACHAT_CAT_ESAT_STPA_1' => 'Conditionnement, logistique et transport',
            'VOLUME_ACHAT_CAT_ESAT_STPA_2' => 'Les espaces verts et paysagers',
            'VOLUME_ACHAT_CAT_ESAT_STPA_3' => 'Nettoyage et entretien',
            'VOLUME_ACHAT_CAT_ESAT_STPA_4' => 'Production industrielle (travail du bois, éléctricité, métallurgie, mécanique, textile, …)',
            'VOLUME_ACHAT_CAT_ESAT_STPA_5' => 'Restauration, hébergement et services touristiques',
            'VOLUME_ACHAT_CAT_ESAT_STPA_6' => 'Prestations administratives (gestion back office, service client)',
            'VOLUME_ACHAT_CAT_ESAT_STPA_7' => 'Services généraux',
            'VOLUME_ACHAT_CAT_ESAT_STPA_8' => 'Production alimentaire',
            'VOLUME_ACHAT_CAT_ESAT_STPA_9' => 'Communication et marketing',
            'VOLUME_ACHAT_CAT_ESAT_STPA_10' => 'Construction et bâtiment',
            'VOLUME_ACHAT_CAT_ESAT_STPA_11' => 'Impression, reprographie et marquage',
            'VOLUME_ACHAT_CAT_ESAT_STPA_12' => 'Artisanat (artisanat d\'art, coffrets cadeaux, création de goodies, restauration de meubles, …)',
            'VOLUME_ACHAT_CAT_ESAT_STPA_13' => 'Énergie, environnement, gestion des déchets',
            'VOLUME_ACHAT_CAT_ESAT_STPA_14' => 'Prestations intellectuelles (informatique et services numériques, …)',
        ],
        'MOBILITE' => [
            'MOBILITE_1' => 'Proche de transports en commun (si oui précisez lesquels)',
            'MOBILITE_2' => 'Mise à disposition de moyens de mobilité douce avec accessoires de sécurité associés (station d\'accueil de vélos et/ou de trotinettes)',
            'MOBILITE_3' => 'Offre d\'un pass de transports en commun valable pour la durée de l’événement',
            'MOBILITE_4' => 'Utilisation de véhicules à faibles émissions de gaz à effets de serre (électriques, hybrides, …)',
            'MOBILITE_5' => 'Accès PMR',
        ],
        'CERTIFICATION' => [
            'CERTIFICATION_1' => 'Bâtiment HQE, NF Habitat, ou BBCA',
            'CERTIFICATION_2' => 'Clef Verte',
            'CERTIFICATION_3' => 'Certification Green Food',
            'CERTIFICATION_4' => 'Certification Bon pour le Climat',
            'CERTIFICATION_5' => 'Etoile Verte Michelin',
            'CERTIFICATION_6' => 'EcoVadis',
            'CERTIFICATION_7' => 'Bureau Veritas',
            'CERTIFICATION_8' => 'ISO 26000',
            'CERTIFICATION_9' => 'ISO 14001',
            'CERTIFICATION_10' => 'Autre',
        ],
        'TYPE_CUISINE' => [
            'TYPE_CUISINE_1' => 'Gastronomique',
            'TYPE_CUISINE_2' => 'Bistrot',
            'TYPE_CUISINE_3' => 'Fusion',
            'TYPE_CUISINE_4' => 'Internationale',
            'TYPE_CUISINE_5' => 'Traditionnelle',
            'TYPE_CUISINE_6' => 'Cuisine événementielle',
            'TYPE_CUISINE_7' => 'Étoile Michelin',
            'TYPE_CUISINE_8' => 'Bistronomique',
            'TYPE_CUISINE_9' => 'Economique',
            'TYPE_CUISINE_10' => 'Afghan',
            'TYPE_CUISINE_11' => 'Africain',
            'TYPE_CUISINE_12' => 'Afternoon Tea',
            'TYPE_CUISINE_13' => 'Algérien',
            'TYPE_CUISINE_14' => 'Allemand',
            'TYPE_CUISINE_15' => 'Alsacien',
            'TYPE_CUISINE_16' => 'Américain',
            'TYPE_CUISINE_17' => 'Anglais',
            'TYPE_CUISINE_18' => 'Argentin',
            'TYPE_CUISINE_19' => 'Arménien',
            'TYPE_CUISINE_20' => 'Asiatique',
            'TYPE_CUISINE_21' => 'Auvergnat',
            'TYPE_CUISINE_22' => 'Basque',
            'TYPE_CUISINE_23' => 'Bouchon lyonnais',
            'TYPE_CUISINE_24' => 'Brésilien',
            'TYPE_CUISINE_25' => 'Cambodgien',
            'TYPE_CUISINE_26' => 'Canadien',
            'TYPE_CUISINE_27' => 'Chinois',
            'TYPE_CUISINE_28' => 'Colombien',
            'TYPE_CUISINE_29' => 'Coréen',
            'TYPE_CUISINE_30' => 'Corse',
            'TYPE_CUISINE_31' => 'Créole',
            'TYPE_CUISINE_32' => 'Crêperie',
            'TYPE_CUISINE_33' => 'Cubain',
            'TYPE_CUISINE_34' => 'Cuisine des îles',
            'TYPE_CUISINE_35' => 'Cuisine du monde',
            'TYPE_CUISINE_36' => 'Cuisine traditionnelle',
            'TYPE_CUISINE_37' => 'Cuisine contemporaine',
            'TYPE_CUISINE_38' => 'Egyptien',
            'TYPE_CUISINE_39' => 'Espagnol',
            'TYPE_CUISINE_40' => 'Ethiopien',
            'TYPE_CUISINE_41' => 'Europe de l\'Est',
            'TYPE_CUISINE_42' => 'Français',
            'TYPE_CUISINE_43' => 'Franco-belge',
            'TYPE_CUISINE_44' => 'Fruits de mer',
            'TYPE_CUISINE_45' => 'Fusion',
            'TYPE_CUISINE_46' => 'Grec',
            'TYPE_CUISINE_47' => 'Hawaien',
            'TYPE_CUISINE_48' => 'Indien',
            'TYPE_CUISINE_49' => 'Iranien',
            'TYPE_CUISINE_50' => 'Israelien',
            'TYPE_CUISINE_51' => 'Italien',
            'TYPE_CUISINE_52' => 'Japonais',
            'TYPE_CUISINE_53' => 'Latino',
            'TYPE_CUISINE_54' => 'Libanais',
            'TYPE_CUISINE_55' => 'Marocain',
            'TYPE_CUISINE_56' => 'Méditérranéen',
            'TYPE_CUISINE_57' => 'Mexicain',
            'TYPE_CUISINE_58' => 'Oriental',
            'TYPE_CUISINE_59' => 'Pakistanais',
            'TYPE_CUISINE_60' => 'Péruvien',
            'TYPE_CUISINE_61' => 'Portugais',
            'TYPE_CUISINE_62' => 'Provençal',
            'TYPE_CUISINE_63' => 'Russe',
            'TYPE_CUISINE_64' => 'Savoyard',
            'TYPE_CUISINE_65' => 'Scandinave',
            'TYPE_CUISINE_66' => 'Steakhouse',
            'TYPE_CUISINE_67' => 'Sud-Ouest',
            'TYPE_CUISINE_68' => 'Syrien',
            'TYPE_CUISINE_69' => 'Thaïlandais',
            'TYPE_CUISINE_70' => 'Tunisien',
            'TYPE_CUISINE_71' => 'Turc',
            'TYPE_CUISINE_72' => 'Vegan',
            'TYPE_CUISINE_73' => 'Végétarien',
            'TYPE_CUISINE_74' => 'Vénézuelien',
            'TYPE_CUISINE_75' => 'Vietnamien',
            'TYPE_CUISINE_76' => 'Tunisien',
            'TYPE_CUISINE_77' => 'Turc',
            'TYPE_CUISINE_78' => 'Vegan',
            'TYPE_CUISINE_79' => 'Végétarien',
            'TYPE_CUISINE_80' => 'Vénézuelien',
            'TYPE_CUISINE_81' => 'Vietnamien',
        ],
        'SERVICE_RESTAURATION' => [
            'SERVICE_RESTAURATION_1' => 'Bar',
            'SERVICE_RESTAURATION_2' => 'Collation',
            'SERVICE_RESTAURATION_3' => 'Petit-déjeuner',
            'SERVICE_RESTAURATION_4' => 'Déjeuner / Dîner',
            'SERVICE_RESTAURATION_5' => 'Déjeuner plateau repas',
            'SERVICE_RESTAURATION_6' => 'Déjeuner assis buffet',
            'SERVICE_RESTAURATION_7' => 'Déjeuner assis à l\'assiette',
            'SERVICE_RESTAURATION_8' => 'Déjeuner cocktail dînatoire',
            'SERVICE_RESTAURATION_9' => 'Pause et petit déjeuner',
            'SERVICE_RESTAURATION_10' => 'Restauration en Terrasse',
            'SERVICE_RESTAURATION_11' => 'Restauration en Rooftop',
            'SERVICE_RESTAURATION_12' => 'Restauration en Jardin / Cours',
            'SERVICE_RESTAURATION_13' => 'Cave à vins / Espace dégustation',
            'SERVICE_RESTAURATION_14' => 'Café d’accueil',
            'SERVICE_RESTAURATION_15' => 'Room service',
            'SERVICE_RESTAURATION_16' => 'Barbecue',
            'SERVICE_RESTAURATION_17' => 'Show cooking',
        ],
        'INFO_LEGALE_TVA' => [
            'INFO_LEGALE_TVA_1' => 'Débit',
            'INFO_LEGALE_TVA_2' => 'Encaissement',
        ],
        'MICE_STATUT' => [
            'MICE_STATUT_1' => 'Aucune visibilité',
            'MICE_STATUT_2' => 'Essentiel',
            'MICE_STATUT_3' => 'Cœur de cible',
            'MICE_STATUT_4' => 'Premium',
        ],
        'MODE_PAIEMENT_CARTE_LISTE' => [
            'MODE_PAIEMENT_CARTE_LISTE_1' => 'Amex',
            'MODE_PAIEMENT_CARTE_LISTE_2' => 'Visa / MasterCard',
        ],
        'SITE_PREMIUM' => [
            'SITE_PREMIUM_1' => 'WEB Séminaire Business France',
            'SITE_PREMIUM_2' => 'Guide SBF',
            'SITE_PREMIUM_3' => 'Hôtel & Séminaire',
            'SITE_PREMIUM_4' => 'BPmeetings FR',
            'SITE_PREMIUM_5' => 'BPmeetings US',
            'SITE_PREMIUM_6' => 'BPmeetings UK',
            'SITE_PREMIUM_7' => 'BPmeetings ES',
            'SITE_PREMIUM_8' => 'BPmeetings IT',
            'SITE_PREMIUM_9' => 'BPmeetings NL',
            'SITE_PREMIUM_10' => 'BPmeetings DE',
            'SITE_PREMIUM_11' => 'Destination CHANTILLY',
            'SITE_PREMIUM_12' => 'Séminaire PARIS',
            'SITE_PREMIUM_13' => 'Séminaire MONTAGNE',
            'SITE_PREMIUM_14' => 'Séminaire MER',
            'SITE_PREMIUM_15' => 'Séminaire CHATEAU',
            'SITE_PREMIUM_16' => 'VOUCHERS',
            'SITE_PREMIUM_17' => 'Séminaire PACA',
            'SITE_PREMIUM_18' => 'Lyon',
            'SITE_PREMIUM_19' => 'Lille',
            'SITE_PREMIUM_20' => 'Toulouse',
            'SITE_PREMIUM_21' => 'Reims',
            'SITE_PREMIUM_22' => 'Nantes',
            'SITE_PREMIUM_23' => 'Bordeaux',
            'SITE_PREMIUM_24' => 'Strasbourg / Colmar',
            'SITE_PREMIUM_25' => 'Annecy',
            'SITE_PREMIUM_26' => 'Tours',
            'SITE_PREMIUM_27' => 'Rennes',
            'SITE_PREMIUM_28' => 'Roissy CDG',
            'SITE_PREMIUM_29' => 'Séminaire au vert',
            'SITE_PREMIUM_30' => 'Versailles',
            'SITE_PREMIUM_31' => 'Marne-la-Vallée',
            'SITE_PREMIUM_32' => 'Normandie',
            'SITE_PREMIUM_33' => 'Corse',
            'SITE_PREMIUM_34' => 'Montpellier',
            'SITE_PREMIUM_35' => 'Biarritz',
            'SITE_PREMIUM_36' => 'Fontainebleau Séminaire',
            'SITE_PREMIUM_37' => 'Bretagne',
        ],
    ];

    /** @return array<string, string> */
    public static function choicesFor(string $attributeCode): array
    {
        return self::CHOICES[$attributeCode] ?? [];
    }

    /** @return array<string, array<string, string>> */
    public static function allChoices(): array
    {
        return self::CHOICES;
    }

    public static function attributeId(string $attributeCode): int
    {
        if (!isset(self::CHOICES[$attributeCode])) {
            throw new \InvalidArgumentException(sprintf('Unknown LOV attribute "%s".', $attributeCode));
        }

        return self::stableIntegerId('attribute:'.$attributeCode);
    }

    public static function valueId(string $attributeCode, string $valueCode): int
    {
        self::assertValid($attributeCode, $valueCode);

        // These stable IDs must remain globally unique across every LOV because
        // FicheAttributValeur uses (fiche_id, value_id) as its composite key.
        return self::stableIntegerId('value:'.$attributeCode.':'.$valueCode);
    }

    public static function valueCode(int $valueId): string
    {
        foreach (self::CHOICES as $attributeCode => $choices) {
            foreach ($choices as $valueCode => $_label) {
                if (self::valueId($attributeCode, $valueCode) === $valueId) {
                    return $valueCode;
                }
            }
        }

        throw new \InvalidArgumentException(sprintf('Unknown LOV value id "%d".', $valueId));
    }

    public static function assertValid(string $attributeCode, ?string $value): void
    {
        if (null === $value) {
            return;
        }

        if (!isset(self::CHOICES[$attributeCode][$value])) {
            throw new \InvalidArgumentException(sprintf('Unknown LOV code "%s" for attribute "%s".', $value, $attributeCode));
        }
    }

    /** @param list<string> $values */
    public static function assertValidMany(string $attributeCode, array $values): void
    {
        foreach ($values as $value) {
            self::assertValid($attributeCode, $value);
        }
    }

    private static function stableIntegerId(string $value): int
    {
        $unpacked = unpack('Nid', substr(hash('sha256', $value, true), 0, 4));
        if (false === $unpacked) {
            throw new \LogicException('Unable to generate a stable LOV identifier.');
        }

        return $unpacked['id'] & 0x7FFFFFFF;
    }
}
