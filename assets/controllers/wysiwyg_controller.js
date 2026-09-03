import { Controller } from '@hotwired/stimulus';

/*
 * TinyMCE n'est pas un module de l'importmap, et le portail le chargeait par une
 * balise <script> inline — interdite ici par la policy de templates. On l'injecte
 * donc à la volée depuis /tinymce/tinymce.min.js (servi tel quel, hors asset map).
 * La promesse est mémorisée au niveau du module : plusieurs éditeurs sur une même
 * page partagent un seul chargement. TinyMCE déduit l'emplacement de ses skins et
 * greffons du `src` de ce script.
 */
let tinymceLoader = null;

function loadTinymce() {
    if (window.tinymce) {
        return Promise.resolve(window.tinymce);
    }

    if (null === tinymceLoader) {
        tinymceLoader = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = '/tinymce/tinymce.min.js';
            script.referrerPolicy = 'origin';
            script.addEventListener('load', () => resolve(window.tinymce));
            script.addEventListener('error', () => {
                tinymceLoader = null;
                reject(new Error('Impossible de charger TinyMCE.'));
            });
            document.head.appendChild(script);
        });
    }

    return tinymceLoader;
}

export default class extends Controller {
    static targets = ['wysiwyg', 'compteur'];
    // compteur : affiche « x / N » sous l'éditeur (0 = pas de compteur).
    static values = { maxHeight: Number, maxLength: Number, compteur: Number };

    connect() {
        loadTinymce().then((tinymce) => {
            // L'élément peut avoir été retiré du DOM pendant le chargement.
            if (!this.element.isConnected) {
                return;
            }
            this.tinymce = tinymce;
            this.init();
        });
    }

    init() {
        const locale = document.documentElement.lang || 'fr';

        // @see: https://www.tiny.cloud/docs/tinymce/latest/ui-localization/
        const lang = 'fr' === locale ? 'fr-FR' : 'en';

        const options = {
            // Une instance auto-hébergée sans clé passe en lecture seule depuis
            // TinyMCE 7 ; `gpl` déclare l'usage sous GNU GPL v2+, l'une des deux
            // licences du paquet auto-hébergé (le portail, lui, passe par le CDN).
            license_key: 'gpl',
            target: this.wysiwygTarget,
            language: lang,
            menubar: false,
            statusbar: false,
            // Le champ est stocké en texte brut (paragraphes, retours à la
            // ligne, puces) : la barre d'outils n'offre que ce qui survit à
            // l'enregistrement, et les accents sortent en UTF-8, pas en
            // entités nommées (&eacute;).
            plugins: ['lists', 'emoticons', 'wordcount'],
            toolbar: 'undo redo bullist emoticons',
            entity_encoding: 'raw',
            content_style: 'body { font-size: 0.75rem; margin: 8px; }',
            setup: (editor) => {
                editor.on('keydown', (e) => this.onKeyDown(e, editor));
                editor.on('input', () => this.onChange(editor));
                editor.on('PastePreProcess', (e) => this.onPaste(e, editor));
                editor.on('init change input undo redo SetContent', () => this.compter(editor));
            },
        }

        if (this.maxHeightValue > 0) {
            options.max_height = this.maxHeightValue;
        }

        this.tinymce.init(options);
    }

    onChange(editor) {
        editor.save();
    }

    compter(editor) {
        if (this.compteurValue <= 0 || !this.hasCompteurTarget) {
            return;
        }
        const longueur = editor.plugins.wordcount.body.getCharacterCount();
        this.compteurTarget.textContent = `${longueur} / ${this.compteurValue}`;
        this.compteurTarget.classList.toggle('text-error', longueur > this.compteurValue);
    }

    onPaste(e, editor) {
        if (this.maxLengthValue === 0) {
            return;
        }

        const count = editor.plugins.wordcount.body.getCharacterCount();
        const remaining = this.maxLengthValue - count;

        if (remaining <= 0) {
            e.content = '';
            return;
        }

        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = e.content;
        const pastedText = tempDiv.textContent || tempDiv.innerText || '';

        if (pastedText.length > remaining) {
            e.content = pastedText.substring(0, remaining);
        }
    }

    onKeyDown(e, editor) {
        if (this.maxLengthValue === 0) {
            return;
        }

        const count = editor.plugins.wordcount.body.getCharacterCount();
        // Match only printable characters
        if (count >= this.maxLengthValue && e.key.length === 1 && !e.ctrlKey && !e.metaKey) {
            e.preventDefault();
        }
    }

    disconnect() {
        if (!this.tinymce) {
            return;
        }
        const editor = this.tinymce.get(this.wysiwygTarget.id);
        if (editor) {
            editor.remove();
        }
    }
}
