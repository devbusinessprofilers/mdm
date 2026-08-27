<?php

namespace App;

use App\Dam\Message\ScanDamAnomalies;
use App\Etl\Message\FlushSalesforceExports;
use App\Etl\Message\RefreshFichesSalesforce;
use App\Etl\Message\SendSalesforceSallesBatch;
use App\Pim\Message\EnvoyerRelancesPlanifiees;
use App\Pim\Message\RemindIncompleteFiches;
use App\Shared\Alert\Message\CheckFailedQueue;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            ->stateful($this->cache) // ensure missed tasks are executed
            ->processOnlyLastMissedRun(true) // ensure only last missed task is run

            ->add(RecurringMessage::every('15 minutes', new CheckFailedQueue()))

            // Redispatch : le scan (lourd) s'exécute dans le worker dam, pas
            // dans le consumer cron du scheduler.
            ->add(RecurringMessage::every('1 hour', new RedispatchMessage(new ScanDamAnomalies(), 'dam')))

            // Relances hebdomadaires des fiches incomplètes, en deux temps
            // exécutés par le worker pim : préparation du lot à 8h (vérifiable
            // dans /admin/relances-completude), envoi des lignes restantes à
            // 14h si l'envoi automatique est actif.
            ->add(RecurringMessage::cron('0 8 * * 1', new RedispatchMessage(new RemindIncompleteFiches(), 'pim'), new \DateTimeZone('Europe/Paris')))
            ->add(RecurringMessage::cron('0 14 * * 1', new RedispatchMessage(new EnvoyerRelancesPlanifiees(), 'pim'), new \DateTimeZone('Europe/Paris')))

            // Refresh Salesforce quotidien des fiches (RSE, évaluation client,
            // procédure judiciaire, contrats, statut partenaire), exécuté par
            // le worker etl : SOQL + écritures par lots, trop lourd pour le
            // consumer cron. Les fiches modifiées repartent vers la
            // marketplace par la sync habituelle.
            ->add(RecurringMessage::cron('0 3 * * *', new RedispatchMessage(new RefreshFichesSalesforce(), 'etl'), new \DateTimeZone('Europe/Paris')))

            // Synchro Salesforce sortante (CSV e-mail, système de transition) :
            // envoi Produits au fil de l'eau (coalescé) toutes les minutes, et
            // envoi Salles groupé une fois par nuit. Le worker etl porte ces
            // envois ; désactivés tant que la synchro n'est pas configurée.
            ->add(RecurringMessage::every('1 minute', new RedispatchMessage(new FlushSalesforceExports(), 'etl')))
            ->add(RecurringMessage::cron('0 4 * * *', new RedispatchMessage(new SendSalesforceSallesBatch(), 'etl'), new \DateTimeZone('Europe/Paris')))

            // Purges quotidiennes, exécutées directement par le consumer cron
            // du scheduler (quelques DELETE bornés) : sans elles, l'outbox et
            // les jetons de compte croissent sans limite.
            ->add(RecurringMessage::cron('20 4 * * *', new RunCommandMessage('app:outbox:purge'), new \DateTimeZone('Europe/Paris')))
            ->add(RecurringMessage::cron('25 4 * * *', new RunCommandMessage('app:account:purge-expired-tokens'), new \DateTimeZone('Europe/Paris')))
            // Monitoring /admin/performance : échantillons (7 j), heartbeats
            // morts (48 h) et logs persistés (15 j).
            ->add(RecurringMessage::cron('30 4 * * *', new RunCommandMessage('app:performance:purge'), new \DateTimeZone('Europe/Paris')))

            // Contrôle mensuel d'état d'activité des lieux (Sirene) : cadence des
            // radiations d'entreprises. No-op tant que sirene.verif_statut_actif
            // est désactivé. Portée par défaut (lieux avec SIRET stocké) = léger,
            // exécutable dans le consumer cron ; le rapprochement par nom
            // (--inclure-sans-siret) reste un lancement manuel.
            ->add(RecurringMessage::cron('0 5 1 * *', new RunCommandMessage('app:pim:verifier-statut-etablissements --appliquer'), new \DateTimeZone('Europe/Paris')))

            // add your own tasks here
            // see https://symfony.com/doc/current/scheduler.html#attaching-recurring-messages-to-a-schedule
        ;
    }
}
