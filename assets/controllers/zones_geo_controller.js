import { Controller } from '@hotwired/stimulus';

/*
 * Zones d'intervention mobiles (maquette portail) : les régions proposées
 * dépendent des pays cochés, les départements des régions cochées. Posé sur
 * la carte ; les trois sélecteurs (composant Select) sont repérés par le
 * niveau porté par leur <select> natif (data-zones-geo-niveau). Les tables
 * région → pays et département → région viennent de ZonesGeographiques.
 */
export default class extends Controller {
    static values = { regions: Object, departements: Object };

    connect() {
        // Les composants Select se branchent après la carte : on laisse un tour.
        requestAnimationFrame(() => this.maj());
    }

    maj() {
        const pays = this.valeurs('pays');
        const regions = this.composant('region');
        if (regions) {
            regions.restreindre(pays.length === 0 ? null : Object.keys(this.regionsValue).filter((code) => pays.includes(this.regionsValue[code])));
        }
        const regionsCochees = this.valeurs('region');
        const departements = this.composant('departement');
        if (departements) {
            departements.restreindre(regionsCochees.length === 0 ? null : Object.keys(this.departementsValue).filter((code) => regionsCochees.includes(this.departementsValue[code])));
        }
    }

    valeurs(niveau) {
        const select = this.element.querySelector(`select[data-zones-geo-niveau="${niveau}"]`);

        return select ? Array.from(select.selectedOptions).map((option) => option.value) : [];
    }

    composant(niveau) {
        const select = this.element.querySelector(`select[data-zones-geo-niveau="${niveau}"]`);
        const racine = select?.closest('[data-controller~="select"]');

        return racine ? this.application.getControllerForElementAndIdentifier(racine, 'select') : null;
    }
}
