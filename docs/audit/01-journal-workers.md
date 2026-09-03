# Audit 1 — journal des traitements et workers (résumé consolidable)

## Réponse à la question centrale
Pas de fonction d'écriture unique. Le journal (/outils) est une VUE DE LECTURE : `journal()` = 9 SELECT indépendants (un par famille) fusionnés/triés en PHP (JournalTraitementsRepository.php:72-253) ; `echecs()` = 8 SELECT (l.274-410). Chaque worker écrit dans SA table/entité de run avec SON vocabulaire.
- Seul « journal » qui écrit : VisibiliteGeoJournal (1 famille/10), appelé par aucun handler.
- Statut synthétisé en PHP pour enrichissement (l.155), visibilite (l.197 'termine' constant), salesforce (l.234-238) ; erreur inventée pour traduction (l.119 texte fixe alors que last_error existe et est lu par echecs() l.320).

## Tableau familles
| Famille | Entité | Statut | Transition | Temps | Erreur |
| import | FicheImportJob | enum ImportJobStatus FR (en_attente,en_cours,termine,termine_avec_erreurs,echoue) | entité start/finish/fail via StartFicheImportHandler, ProcessFicheImportBatchHandler, FicheImportFailureSubscriber | created/started/finished/updated | failure_message+error_count |
| ocr | DocumentExtraction | enum ExtractionStatus EN (queued,processing,ready,partially_reviewed,reviewed,failed) | entité via ExtractDocumentHandler + OcrFailureSubscriber | idem + attempts | error_message |
| traduction | FicheTranslation | enum TranslationStatus mixte (cases EN valeurs FR) ; en_cours JAMAIS affecté | via TranslatePublishedFicheHandler + TranslationFailureSubscriber | updated_at seul | last_error |
| media | MediaAsset | enum MediaStatus EN | MediaProcessingService, DeleteMediaHandler, MediaFailureSubscriber | updated/processed/deleted | error_message |
| enrichissement | FicheEnrichmentRun | AUCUN statut, dérivé de finished_at | EnrichirFicheHandler::terminerRun ; AUCUN subscriber d'échec → crash = « en_attente » à jamais | requested/finished | aucune (JSON resultat) |
| export | ReferentielExport | CONSTANTES string (pas d'enum) en_attente,en_cours,terminee,echoue,expiree | GenererReferentielExportHandler (catch Throwable) | requested/finished/expires | erreur |
| visibilite | VisibiliteGeoRun | AUCUN statut | VisibiliteGeoJournal, pas de handler | executed_at | aucune |
| marketplace | FicheMarketplaceSync | enum MarketplaceSyncStatus EN (synced,failed,removed) ; constructeur init à Failed (l.50) | 3 handlers + MarketplaceSyncFailureSubscriber | synced/updated | last_error |
| salesforce | FicheSalesforceExport | AUCUN statut, dérivé | FlushSalesforceExportsHandler, SendSalesforceSallesBatchHandler | dirty/sent/salles_sent/retry | last_error+failure_count |
| outbox | OutboxMessage | enum OutboxStatus | OutboxRepository DBAL | … | last_error+attempts |

5 mots pour « échec » (echoue, termine_avec_erreurs, failed, en_erreur, +synthétisé), 4 pour « terminé ». STATUTS_ERREUR (l.51) + dictionnaire etat_jetons 22 entrées (outils.html.twig:52-74) absorbent la dispersion.
Familles absentes du journal malgré une entité de run avec fail() : Vision (ImageEnhancement, ImageRecognition), relances planifiées, snapshots dashboard.

## Handlers (42) — incohérences
1. Deux stratégies d'échec concurrentes : try/catch+fail() dans handler (6) vs subscriber WorkerMessageFailedEvent (5) ; 3 cumulent, 6 familles n'ont ni l'un ni l'autre.
2. 5 subscribers d'échec = 5 copies divergentes : Media/Ocr protégés (resetManager + try/catch), Translation/FicheImport/MarketplaceSync NON protégés (flush sur EM fermé → exception dans le listener) ; garde Ulid::isValid absente dans 2.
3. Bloc rethrow MarketplaceApiException copié 4× (SyncFicheMarketplace 77-87, RemoveFicheFromMarketplace 55-68, PruneMarketplacePhotos 76-90, SyncLovDictionary 33-41).
4. Bloc retryable→RecoverableMessageHandlingException copié 4× (EnhanceImage 78-82, RecognizeImage 73-77, CleanupBoxFile 25-27, ExtractDocument 101-107).
5. Copie locale S3 (tempnam/readStream/stream_copy/finally) réécrite 3× (MediaUploaded 114-145, ExtractDocument 48-60, EnhanceImage 43-63).
6. Chargement fiche find(Ulid::fromString)+instanceof répété dans 7 handlers, sans Ulid::isValid (id malformé = 5 retries + DLQ).
7. flush() explicite dans 18 handlers alors que ReceivedDoctrineTransactionMiddleware.php:40 flush déjà.
8. Idempotence non uniforme : IdempotencyMiddleware n'agit que si EventIdStamp, posé seulement par OutboxPublisher::enqueue. 10 dispatchs directs ($bus->dispatch dans SalesforceWebhookController:60, QualiteController:152, DashboardController:46, FailedMessageActions:76, EnvoyerRelancesPlanifieesHandler:44, RelanceCompletudeAdminController:152, FicheEnrichirController:54, ReferentielController:274, MediasController:196) + RedispatchMessage scheduler sans reçu. FailedMessageActions::reessayer renvoie le message nu sans stamp. ARCHITECTURE.md affirme l'idempotence universelle → vrai pour ~30/42.
9. Logger : 17 handlers, canaux incohérents ; 25 sans trace.
10. RecomputeDashboardCommand.php:35 appelle le handler directement hors bus.

## Repository journal
- journal()/echecs() : mapping écrit 2× pour 7 familles avec divergences (quand : created_at vs COALESCE(finished_at,…) ; erreur traduction texte fixe vs last_error ; liens différents) ; visibilite & enrichissement absents de echecs() et de FilesATraiterRepository::comptes().
- Critère « échec » écrit 3× : STATUTS_ERREUR l.51, WHERE de echecs(), FilesATraiterRepository.php:32-38 ; alignement manuel documenté l.257-260 ; recopié en littéral outils.html.twig:13.
- Libellés dupliqués : etat_jetons twig, etl/import/masse.html.twig:74-79 vs ImportJobStatus::label(), traitements_en_echec.html.twig:31 valeur brute ; FAMILLES réexporté vers 3 contrôleurs.
- 17 requêtes SQL concaténées, jointure pim_fiche répétée 8×, INNER vs LEFT JOIN incohérents ; lienFiche() (l.528-537) duplique routage gamme→route ; resumeEnrichissement (l.469-497) duplique les codes de EnrichirFicheHandler.php:105-170.

## Shared
- Outbox/transaction cohérent (ordre middleware OK).
- 8 listeners non ordonnés sur WorkerMessageFailedEvent (même préambule willRetry ×7) ; si un subscriber non protégé lève, l'alerte mail ne part pas.
- WorkerNameResolver::KNOWN recopie docker-compose.yml:92-106 ; QueueSampler::KNOWN_QUEUES recopie messenger.yaml.
- messenger_messages lu par 7 classes avec SQL indépendant (JournalTraitementsRepository:438, HealthReporter:34, CheckFailedQueueHandler:28, QueueSampler:58, PerformanceDataProvider, MetricsExporter, EventMonitoringRepository).

## Config messenger
- 8 transports + 2 scheduler, tous consommés ; 42/42 messages↔handlers OK.
- messenger.yaml:127-128 routes Notifier ChatMessage/SmsMessage sans usage (mortes) ; retry_strategy copiée 7× ; ComputeDashboardStats 3 chemins d'exécution sans idempotence ; --failure-limit=10 + DomainException PublishDocumentHandler:49,53 peut arrêter worker-dam.

## Propositions
Élevé : E1 AbstractFailureListener/FailureRecorder (M, faible) ; E2 table de mapping FamilleJournal → journal/echecs/comptes/Twig dérivés (M, moyen) ; E3 enum partagée EtatTraitement en lecture seule (M, faible).
Moyen : M1 doc + test d'archi des dispatchs directs (S) ; M2 conversion d'exception dans les clients (S) ; M3 copierVersFichierTemporaire (S) ; M4 familles manquantes après E2 (S) ; M5 FilesMessengerRepository unique (M) ; M6 ComputeDashboardStats via RedispatchMessage (S).
Faible : F1 supprimer flush redondants ; F2 FicheRepository::findParIdString ; F3 composant EtatJeton ; F4 enum ResultatSourceEnrichissement ; F5 routes Notifier mortes + ancre YAML ; F6 canaux logs ; F7 ARCHITECTURE.md.
Ordre : E1 → E3 → E2 → M2/M3/F2 → M1/F7.
