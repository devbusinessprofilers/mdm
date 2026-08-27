import { Controller } from '@hotwired/stimulus';
import { Chart, registerables } from 'chart.js';

/* stimulusFetch: 'lazy' */

/*
 * Écran /admin/performance : quatre graphiques temporels (débit par file,
 * profondeur des files, charge par worker, mémoire) et cartes des workers,
 * alimentés par l'endpoint JSON /admin/performance/data et rafraîchis par
 * polling. Les charts sont mutés en place (chart.update('none')) : jamais
 * détruits ni rechargés, pas de clignotement.
 */

Chart.register(...registerables);

const PALETTE = ['#4f46a5', '#0ea5a4', '#f59e0b', '#ef4444', '#8b5cf6', '#10b981', '#64748b', '#ec4899'];

const ETAT_CLASSES = {
    actif: 'bg-success',
    occupe: 'bg-success',
    retard: 'bg-peach',
    inactif: 'bg-error',
    planifie: 'bg-neutral-400',
    inconnu: 'bg-neutral-300',
};

const ETAT_LIBELLES = {
    actif: 'Actif',
    occupe: 'Occupé (message long)',
    retard: 'En retard',
    inactif: 'Inactif',
    planifie: 'Planifié',
    inconnu: 'Jamais vu',
};

export default class extends Controller {
    static values = { url: String, interval: Number, fenetre: Number };
    static targets = ['debit', 'profondeur', 'charge', 'memoire', 'horodatage'];

    connect() {
        this.charts = {};
        this.creerChart('debit', this.debitTarget, 'msg / min');
        this.creerChart('profondeur', this.profondeurTarget, 'messages en file');
        this.creerChart('charge', this.chargeTarget, '% de charge', 100);
        this.creerChart('memoire', this.memoireTarget, 'Mo');
        this.rafraichir();
        const delay = this.intervalValue > 0 ? this.intervalValue : 10000;
        this.timer = window.setInterval(() => this.rafraichir(), delay);
    }

    disconnect() {
        window.clearInterval(this.timer);
        Object.values(this.charts).forEach((chart) => chart.destroy());
        this.charts = {};
    }

    changerFenetre(event) {
        const minutes = Number(event.params.minutes);
        if (minutes > 0) {
            this.fenetreValue = minutes;
            this.element.querySelectorAll('[data-performance-minutes-param]').forEach((bouton) => {
                bouton.setAttribute('aria-current', Number(bouton.dataset.performanceMinutesParam) === minutes ? 'true' : 'false');
            });
            this.rafraichir();
        }
    }

    async rafraichir() {
        if (document.hidden || !this.hasUrlValue) {
            return;
        }
        try {
            const url = `${this.urlValue}?fenetre=${this.fenetreValue || 15}`;
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store',
            });
            if (!response.ok) {
                return;
            }
            this.appliquer(await response.json());
        } catch {
            // Erreur réseau transitoire : on retentera au prochain tick.
        }
    }

    appliquer(donnees) {
        const labels = donnees.series.labels.map((ts) =>
            new Date(ts * 1000).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }));
        this.majChart('debit', labels, donnees.series.debit);
        this.majChart('profondeur', labels, donnees.series.profondeur);
        this.majChart('charge', labels, donnees.series.charge);
        this.majChart('memoire', labels, donnees.series.memoire);
        donnees.workers.forEach((worker) => this.majCarte(worker));
        if (this.hasHorodatageTarget) {
            this.horodatageTarget.textContent = `Actualisé à ${new Date(donnees.generatedAt).toLocaleTimeString('fr-FR')}`;
        }
    }

    creerChart(nom, canvas, uniteY, maxY = undefined) {
        this.charts[nom] = new Chart(canvas, {
            type: 'line',
            data: { labels: [], datasets: [] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                interaction: { mode: 'nearest', axis: 'x', intersect: false },
                elements: { point: { radius: 0, hitRadius: 8 }, line: { tension: 0.25, borderWidth: 2 } },
                scales: {
                    x: { ticks: { maxTicksLimit: 8, autoSkip: true }, grid: { display: false } },
                    y: { beginAtZero: true, max: maxY, title: { display: true, text: uniteY } },
                },
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } } },
                spanGaps: true,
            },
        });
    }

    majChart(nom, labels, seriesParSujet) {
        const chart = this.charts[nom];
        if (!chart) {
            return;
        }
        chart.data.labels = labels;
        const sujets = Object.keys(seriesParSujet).sort();
        // Datasets réconciliés par sujet : mutation en place quand il existe,
        // ajout sinon, retrait de ceux qui ont disparu.
        chart.data.datasets = sujets.map((sujet, i) => {
            const existant = chart.data.datasets.find((d) => d.label === sujet);
            const couleur = existant?.borderColor ?? PALETTE[i % PALETTE.length];
            return {
                ...(existant ?? {}),
                label: sujet,
                data: seriesParSujet[sujet],
                borderColor: couleur,
                backgroundColor: couleur,
            };
        });
        chart.update('none');
    }

    majCarte(worker) {
        const carte = this.element.querySelector(`[data-perf-worker="${worker.name}"]`);
        if (!carte) {
            return;
        }
        const pastille = carte.querySelector('[data-perf-champ="pastille"]');
        if (pastille) {
            pastille.className = `shrink-0 w-2.5 h-2.5 rounded-full ${ETAT_CLASSES[worker.etat] ?? 'bg-neutral-300'}`;
            pastille.title = ETAT_LIBELLES[worker.etat] ?? worker.etat;
        }
        this.texte(carte, 'etat', ETAT_LIBELLES[worker.etat] ?? worker.etat);
        this.texte(carte, 'charge', worker.chargePct === null ? '—' : `${worker.chargePct} %`);
        this.texte(carte, 'debit', worker.msgParMin === null ? '—' : `${worker.msgParMin} msg/min`);
        this.texte(carte, 'memoire', worker.memoryBytes === null ? '—' : `${(worker.memoryBytes / 1048576).toFixed(0)} Mo`);
        this.texte(carte, 'uptime', worker.uptimeS === null ? '—' : this.duree(worker.uptimeS));
        this.texte(carte, 'traites', String(worker.totaux.handled));
        this.texte(carte, 'echecs', String(worker.totaux.failed));
        this.texte(carte, 'encours', worker.enCours
            ? `${worker.enCours.classe} · ${this.duree(worker.enCours.depuisS)}`
            : (worker.etat === 'inconnu' ? 'jamais vu — redémarrer les workers ?' : 'au repos'));
    }

    texte(carte, champ, valeur) {
        const cible = carte.querySelector(`[data-perf-champ="${champ}"]`);
        if (cible) {
            cible.textContent = valeur;
        }
    }

    duree(secondes) {
        if (secondes < 60) {
            return `${secondes} s`;
        }
        if (secondes < 3600) {
            return `${Math.floor(secondes / 60)} min`;
        }
        const heures = Math.floor(secondes / 3600);
        return `${heures} h ${Math.floor((secondes % 3600) / 60).toString().padStart(2, '0')}`;
    }
}
