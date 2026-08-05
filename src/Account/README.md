# Module Account

Le module `Account` porte l'authentification, les comptes internes, les rôles BP,
les invitations et les droits d'accès aux fiches. Il gère aussi les contacts
prestataires et leurs affiliations, sans créer les comptes de la Marketplace
externe.

## Fonctionnalités livrées

- connexion Symfony par email et mot de passe, limitation des tentatives et
  refus des comptes désactivés ;
- hiérarchie `ROLE_BP_EDITOR`, `ROLE_BP_VALIDATOR`, `ROLE_ADMIN` et
  `ROLE_SUPER_ADMIN` ;
- administration des utilisateurs internes, activation/désactivation et
  modification du rôle ;
- invitation interne valable 24 heures, envoyée de manière asynchrone via
  l'outbox et la file `mail` ;
- renvoi d'une invitation interne avec invalidation des anciens liens ;
- mot de passe oublié et reset administrateur par lien signé valable une
  heure, à usage unique et limité par email et adresse IP ;
- suppression d'un utilisateur interne par révocation et anonymisation, sans
  casser les références historiques ;
- gestion transactionnelle des collaborateurs et affiliations d'une fiche,
  avec verrou pessimiste et trois destinataires de demandes au maximum ;
- contrôle central des actions sur une fiche par `FicheVoter` ;
- authentification stateless des sites externes par JWT RS256 pour les routes
  API configurées.

## Règles d'accès

Un éditeur BP peut consulter, modifier et soumettre une fiche. Un validateur
BP, un administrateur ou un super-administrateur peut en plus valider, publier,
archiver, supprimer et gérer les affiliations. Un compte désactivé ne peut
effectuer aucune de ces actions.

Les JWT externes doivent avoir une signature RS256 valide et fournir les claims
`iss`, `aud`, `sub`, `iat`, `exp` et `jti`. Les valeurs attendues et la clé
publique sont injectées par la configuration ; aucun secret ne doit être commité.

Les mots de passe concernent uniquement les `User` internes. Les
`FicheCollaborateur` représentent les prestataires et ne possèdent aucun mot de
passe dans le PIM.

Une invitation est valable 24 heures et un reset une heure. Créer un nouveau
lien invalide les précédents. Un reset ne réactive pas un compte désactivé. Les
jetons expirés depuis plus de 30 jours se purgent avec :

```bash
php bin/console app:account:purge-expired-tokens
```

## Composants principaux

- `Entity/User.php` et `Entity/AccountInvitation.php` : comptes et invitations ;
- `Entity/PasswordResetRequest.php` : demandes de reset à usage unique ;
- `Service/InternalUserManager.php` : administration des comptes internes ;
- `Service/FicheAffiliationManager.php` : affiliations et destinataires ;
- `Security/FicheVoter.php` : autorisations sur les fiches ;
- `Security/ExternalSiteJwtAuthenticator.php` : authentification API externe ;
- `Message/InternalUserInvited.php` : notification asynchrone d'invitation.

Les entités d'affiliation restent dans `Pim/Entity`, car leur cycle de vie est
rattaché à celui d'une fiche. `Account` orchestre leurs règles d'accès.
