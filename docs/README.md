# Documentation du MDM

## Exploitation

- [installation-poste-dev.md](exploitation/installation-poste-dev.md) — installer l'environnement complet sur un nouveau poste (Docker, fixtures)
- [configuration.md](exploitation/configuration.md) — paramètres applicatifs (`/admin/parametres`) et variables d'environnement, avec leur recouvrement
- [secrets.md](exploitation/secrets.md) — gestion et rotation des secrets (`.env.local`, variables Upsun)
- [deploiement-upsun.md](exploitation/deploiement-upsun.md) — déploiement en production (hooks, workers, mounts, procédures)
- [messenger.md](exploitation/messenger.md) — runbook des workers et files Symfony Messenger (transports, échecs, tâches planifiées)

## Architecture et fonctionnel

- [resume-fonctionnel.md](architecture/resume-fonctionnel.md) — tour d'horizon fonctionnel de l'application (fiches, workflow, rôles, modules)
- [external-site-api.md](architecture/external-site-api.md) — contrat de l'API REST v1 exposée aux sites partenaires (JWT, endpoints, champs)
- [normalisation-adresses.md](architecture/normalisation-adresses.md) — normalisation INSEE et vérification des adresses (BAN, Geoapify)
- [sync-marketplace-referentiel-lov.md](architecture/sync-marketplace-referentiel-lov.md) — synchronisation du dictionnaire LOV et des fiches vers la marketplace, reprise Salesforce
- [import-legacy.md](architecture/import-legacy.md) — reprise des données de l'ancien système (commandes, mappings CSV)

Le `cahier-des-charges-etat-mdm.html` à la racine est un document de travail
(état d'avancement annoté du cahier des charges), non suivi par git.
