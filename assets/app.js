import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
/*
 * Ordre volontaire : les tokens définissent les variables CSS, les composants
 * s'appuient dessus, les écrans surchargent en dernier.
 */
import './styles/tokens.css';
import './styles/components.css';
import './styles/app.css';
import './styles/auth.css';
import './styles/app-shell.css';
import './styles/workspace.css';
import './styles/referentiel.css';
import './styles/edition-rapide.css';
import './styles/creation.css';
import './styles/fiche.css';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');
